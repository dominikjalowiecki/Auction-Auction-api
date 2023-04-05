<?php

namespace App\Model;

class BidModel
{
    private $db_connection;

    public $id_item;
    public $id_bidder;
    public $bid_price;

    public const FLAG_SUCCESS = 0x1;
    public const FLAG_FAILURE = 0x2;
    public const FLAG_BID_PRICE_ERROR = 0x4;

    public function __construct($db_connection)
    {
        $this->db_connection = $db_connection;
    }

    public function createBid()
    {
        # Creating new bid
        $query = "
            INSERT INTO bid
                (id_item, id_bidder, bid_price, created_at)
            SELECT
                :id_item, :id_bidder, :bid_price, UTC_TIMESTAMP
            FROM
                DUAL
            WHERE EXISTS
                (
                    SELECT
                        1
                    FROM
                        item
                    WHERE
                        id_item = :id_item AND
                        is_closed = False
                ) AND
                :id_bidder NOT IN (
                    SELECT
                        id_bidder
                    FROM
                        (
                            SELECT
                                id_bidder
                            FROM
                                bid
                            WHERE
                                id_item = :id_item
                            ORDER BY
                                bid_price DESC
                            LIMIT 1
                        ) as t
                ) AND
                NOT EXISTS
                (
                    SELECT
                        1
                    FROM
                        bid b
                    JOIN
                        item i ON b.id_item = i.id_item
                    WHERE
                        b.id_item = :id_item AND
                        b.bid_price >= :bid_price
                ) AND
                NOT EXISTS
                    (
                        SELECT
                            1
                        FROM
                            item
                        WHERE
                            id_item = :id_item AND
                            (
                                id_creator = :id_bidder OR
                                starting_price >= :bid_price
                            )
                    );
        ";

        $data = array(
            'id_item' => $this->id_item,
            'id_bidder' => $this->id_bidder,
            'bid_price' => $this->bid_price
        );

        $stmt = $this->db_connection->prepare($query);

        if ($stmt->execute($data)) {
            if ($stmt->rowCount() > 0) {
                $query = "
                    SELECT
                        id_bidder
                    FROM
                        bid
                    WHERE
                        id_item = :id_item
                    ORDER BY
                        bid_price DESC
                    LIMIT
                        1
                    OFFSET
                        1;
                ";
                $stmt = $this->db_connection->prepare($query);
                $data = array(
                    'id_item' => $this->id_item
                );
                $stmt->execute($data);
                if ($stmt->rowCount()) {
                    $row = $stmt->fetch(\PDO::FETCH_ASSOC);

                    $query = "
                    INSERT INTO notification
                        (id_recipient, title_html, body_html, id_item, created_at)
                    VALUES
                        (:id_recipient, :title_html, :body_html, :id_item, UTC_TIMESTAMP);
                    ";

                    $data = array(
                        "id_recipient" => $row['id_bidder'],
                        "title_html" => "Your offer has been outbidded.",
                        "body_html" => "Someone has outbidded your offer.",
                        "id_item" => $this->id_item
                    );

                    $stmt = $this->db_connection->prepare($query);
                    $stmt->execute($data);
                }

                return self::FLAG_SUCCESS;
            } else {
                return self::FLAG_BID_PRICE_ERROR;
            }
        } else {
            return self::FLAG_FAILURE;
        }
    }
}
