<?php

namespace App\Model;

require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/../helperFunctions.php');

class ItemModel
{
    private $db_connection;

    public $id_item;
    public $name;
    public $description;
    public $id_creator;
    public $id_category;
    public $starting_price;
    public $ending_time;
    public $data;
    public $pagination = 1;
    public $items_count;
    public $pages;
    public $order_by;
    public $category;
    public $search;

    public const FLAG_SUCCESS = 0x1;
    public const FLAG_FAILURE = 0x2;
    public const FLAG_OVERFLOW = 0x4;
    public const FLAG_ERROR = 0x8;
    public const FLAG_ITEM_NOT_FOUND = 0x10;

    public function __construct($db_connection)
    {
        $this->db_connection = $db_connection;
    }

    public function getItems()
    {
        $is_search = $this->search !== "";

        # Getting row count
        $query = "
            SELECT
                COUNT(*) count
            FROM
                item i
            JOIN
                category c
            USING
                (id_category)
            WHERE
        ";

        $query .= ($is_search) ? "MATCH(i.name) AGAINST(:search IN NATURAL LANGUAGE MODE) AND" : "";

        $query .= "
                i.is_closed = False AND
				i.ending_time > UTC_TIMESTAMP AND
                c.name
            LIKE
                :category;
        ";

        $data = array(
            'category' => $this->category
        );

        if ($is_search)
            $data['search'] = $this->search;

        $stmt = $this->db_connection->prepare($query);
        $stmt->execute($data);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->items_count = $row['count'];
        $pagination = CONFIG['PAGINATION'];
        $this->pages = ceil($this->items_count / $pagination);

        if ($this->pages == 0) {
            $this->pagination = 0;
            return self::FLAG_SUCCESS;
        }

        if ($this->pagination > $this->pages) {
            return self::FLAG_OVERFLOW;
        }

        # Getting details of all available items
        $pagination_start = ($pagination * $this->pagination) - $pagination;

        $query = "
            SELECT
                i.id_item,
                i.name,
                i.description,
                i.id_creator,
		u.username as creator_username,
                u.avatar as creator_avatar,
                c.name as category,
                i.starting_price,
                i.starting_time,
                i.ending_price,
                i.ending_time,
                MAX(b.bid_price) max_bid,
                (
                    SELECT
                        id_bidder
                    FROM
                        bid
                    WHERE
                        id_item = i.id_item
                    ORDER BY
                        bid_price DESC
                    LIMIT
                        1
                ) id_bidder,
                (
                    SELECT
                		im.image_url image
                    FROM
                    	image im
                    JOIN
                    	item_image ii
                    USING
                    	(id_image)
                    WHERE
                    	ii.id_item = i.id_item AND
                    	ii.is_main = True
                    LIMIT
                    	1
                ) image
            FROM
                item i
            JOIN
                category c
            USING
                (id_category)
	    JOIN
                user u
            ON
                i.id_creator = u.id_user
            LEFT JOIN
                bid b
            USING
                (id_item)
            WHERE
        ";

        $query .= ($is_search) ? "MATCH(i.name) AGAINST(:search IN NATURAL LANGUAGE MODE) AND" : "";

        $query .= "
                i.is_closed = False AND
				i.ending_time > UTC_TIMESTAMP AND
                c.name
            LIKE
                :category
            GROUP BY
                i.id_item
        ";

        switch ($this->order_by) {
            case 'lowest':
                $query .= "
                    ORDER BY
                        max_bid ASC,
						starting_price ASC
                ";
                break;
            case 'higest':
                $query .= "
                    ORDER BY
                        max_bid DESC,
						starting_price DESC
                ";
                break;
            case 'oldest':
                $query .= "
                    ORDER BY
                        i.starting_time ASC
                ";
                break;
            default:
                # newest
                $query .= "
                    ORDER BY
                        i.starting_time DESC
                ";
                break;
        }

        $query .= "
            LIMIT
                $pagination
            OFFSET
                $pagination_start;
        ";

        $stmt = $this->db_connection->prepare($query);

        if ($stmt->execute($data)) {
            $this->data = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $no_rows = count($this->data);
            for ($i = 0; $i < $no_rows; $i++) {
                $this->data[$i]["description"] = smartTruncate($this->data[$i]["description"], 75);

                $this->data[$i]["creator"] = array(
                    "id_user" => array_remove($this->data[$i], "id_creator"),
                    "username" => array_remove($this->data[$i], "creator_username"),
                    "avatar" => array_remove($this->data[$i], "creator_avatar")
                );
            }

            return self::FLAG_SUCCESS;
        } else {
            return self::FLAG_FAILURE;
        }
    }

    public function createItem()
    {
        # Creating new item
        $query = "
            INSERT INTO item
                (name, description, id_creator, id_category, starting_price, starting_time, ending_time, is_closed, is_accepted)
            SELECT
                :name, :description, :id_creator, :id_category, :starting_price, UTC_TIMESTAMP, :ending_time, :is_closed, :is_accepted
            FROM
                DUAL
            WHERE EXISTS
                (
                    SELECT
                        1
                    FROM
                        category
                    WHERE
                        id_category = :id_category
                ) AND
                :ending_time > UTC_TIMESTAMP;
        ";

        $this->ending_time = date('Y-m-d H:i:s', strtotime($this->ending_time));

        $data = array(
            'name' => $this->name,
            'description' => $this->description,
            'id_creator' => $this->id_creator,
            'id_category' => $this->id_category,
            'starting_price' => $this->starting_price,
            'ending_time' => $this->ending_time,
            'is_closed' => 0,
            'is_accepted' => 1
        );

        $stmt = $this->db_connection->prepare($query);

        if ($stmt->execute($data)) {
            if ($stmt->rowCount() > 0) {
                $this->id_item = $this->db_connection->lastInsertId();
                return self::FLAG_SUCCESS;
            } else {
                return self::FLAG_ERROR;
            }
        } else {
            return self::FLAG_FAILURE;
        }
    }

    public function getItem()
    {
        # Getting details of given item
        $query = "
            SELECT
                i.name,
                i.description,
                i.id_creator,
		u.username as creator_username,
                u.avatar as creator_avatar,
		u.last_online as creator_last_online,
                c.name as category,
                i.starting_price,
                i.starting_time,
                i.id_winner,
                i.ending_price,
                i.ending_time,
                i.is_closed,
                MAX(b.bid_price) max_bid,
                (
                    SELECT
                        id_bidder
                    FROM
                        bid
                    WHERE
                        id_item = i.id_item
                    ORDER BY
                        bid_price DESC
                    LIMIT
                        1
                ) id_bidder,
                (
                    SELECT
                		GROUP_CONCAT(im.image_url)
                    FROM
                    	image im
                    JOIN
                    	item_image ii
                    USING
                    	(id_image)
                    WHERE
                    	ii.id_item = i.id_item 
                ) images
            FROM
                item i
	    JOIN
                category c
            USING
                (id_category)
	    JOIN
                user u
            ON
                i.id_creator = u.id_user
            LEFT JOIN
                bid b
            USING
                (id_item)
            WHERE
                id_item = :id_item
            LIMIT
                0,1;
        ";

        $data = array(
            "id_item" => $this->id_item
        );

        $stmt = $this->db_connection->prepare($query);

        if ($stmt->execute($data)) {
            $this->data = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($this->data['name'] != null) {
                $this->data["images"] =  explode(',', $this->data["images"]);

                $this->data["creator"] = array(
                    "id_user" => array_remove($this->data, "id_creator"),
                    "username" => array_remove($this->data, "creator_username"),
                    "avatar" => array_remove($this->data, "creator_avatar"),
                    "last_online" => array_remove($this->data, "creator_last_online")
                );

                return self::FLAG_SUCCESS;
            } else {
                return self::FLAG_ITEM_NOT_FOUND;
            }
        } else {
            return self::FLAG_FAILURE;
        }
    }
}
