<?php

/**
 * CRONTAB:
 * Run every 15 minutes
 */

require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/../helperFunctions.php');
require_once(__DIR__ . '/../../vendor/autoload.php');

use App\DatabaseConnector;

$db_connection = DatabaseConnector::getConnection();

# Get all ended auctions
$query = "
    SELECT
        id_item
    FROM
        item
    WHERE
        is_closed = False AND
        ending_time <= UTC_TIMESTAMP;
";
$rows = $db_connection->query($query)->fetchAll(PDO::FETCH_NUM);

# Edit bidded item
$in_values = nestedArraysKeyValuesToString($rows, 0);
if ($in_values !== "") {
    $query = "
    UPDATE
        item i
    SET
        id_winner = (
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
        ),
        ending_price = (
            SELECT
                bid_price
            FROM
                bid
            WHERE
                id_item = i.id_item
            ORDER BY
                bid_price DESC
            LIMIT
                1
            ),
        is_closed = True
    WHERE
        i.id_item IN ($in_values);
    ";
    $db_connection->query($query);

    # Notify participating users
    $query = "
    INSERT INTO notification
        (id_recipient, title_html, body_html, id_item, created_at)
    SELECT DISTINCT
        b.id_bidder, 'Aukcja zakończona!', 'Aukcja zakończona!', b.id_item, UTC_TIMESTAMP
    FROM
        bid b
    WHERE
        b.id_item IN ($in_values) AND
        b.id_bidder != (
            SELECT
                id_bidder
            FROM
                bid
            WHERE
                id_item = b.id_item
            ORDER BY
                bid_price DESC
            LIMIT
                1
        );
    ";
    $db_connection->query($query);

    # Notify auction creator
    $query = "
    INSERT INTO notification
        (id_recipient, title_html, body_html, id_item, created_at)
    SELECT
        i.id_creator, 'Twoja aukcja się zakończyła!', 'Sprawdź rezultat w panelu.', i.id_item, UTC_TIMESTAMP
    FROM
        item i
    WHERE
        i.id_item IN ($in_values);
    ";
    $db_connection->query($query);

    # Notify bid winner
    $query = "
    INSERT INTO notification
        (id_recipient, title_html, body_html, id_item, created_at)
    SELECT
        b.id_bidder, 'Wygrałeś aukcję!', 'Wygrałeś aukcję!', b.id_item, UTC_TIMESTAMP
    FROM
        bid b
    WHERE
        b.id_item IN ($in_values)
    ORDER BY
        b.bid_price DESC
    LIMIT
        1;
    ";
    $db_connection->query($query);
}
