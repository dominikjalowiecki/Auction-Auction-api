<?php

/**
 * CRONTAB:
 * Run every 30 minutes
 */

require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/../helperFunctions.php');
require_once(__DIR__ . '/../../vendor/autoload.php');

use App\DatabaseConnector;
use PHPMailer\PHPMailer\PHPMailer;

$db_connection = DatabaseConnector::getConnection();

# Get all non processed tasks of type AUCTIONS_REPORT
$query = "
        SELECT
            t.id_task,
            u.id_user,
            u.first_name,
            u.last_name,
            u.email,
            DATE(t.created_at) task_created_date
        FROM
            task t
        JOIN
            user u
        USING(id_user)
        WHERE
            task_type = 'AUCTIONS_REPORT' AND
            is_processed = False;
    ";
$tasks = $db_connection->query($query)->fetchAll(PDO::FETCH_ASSOC);

# Update all processed tasks
$in_values = nestedArraysKeyValuesToString($tasks, 'id_task');
if ($in_values !== "") {
    $query = "
            UPDATE task
            SET
                is_processed = True
            WHERE
                id_task IN ($in_values);
        ";
    $db_connection->query($query);
}

# Get all closed auctions of users which requested reports
$items = array();
$in_values = nestedArraysKeyValuesToString($tasks, 'id_user');
if ($in_values !== "") {
    $query = "
            SELECT
                i.id_item,
                i.name item_name,
                i.description,
                c.name category_name,
                i.starting_price,
                i.ending_price,
                i.starting_time,
                i.ending_time,
                i.id_creator id_user
            FROM
                item i
            JOIN
                category c
            USING(id_category)
            WHERE
                i.id_creator IN ($in_values) AND
                is_closed = True
            ORDER BY
                i.id_creator ASC,
                i.starting_time ASC;
        ";
    $items = $db_connection->query($query)->fetchAll(PDO::FETCH_ASSOC);
}

foreach ($tasks as $task) {
    # Get auctions of specified user
    $auctions = binarySearchGetArrays($items, 'id_user', $task['id_user']);

    # Create CSV file with auctions
    $fpath = sys_get_temp_dir() . '/' . 'auctions_report_' . $task['id_user'] . '_' . $task['task_created_date'] . '.csv';
    $fp = fopen($fpath, 'w');

    fputcsv($fp, ['ID Item', 'Name', 'Description', 'Category', 'Starting Price', 'Ending Price', 'Created At (UTC)', 'Ended At (UTC)']);
    foreach ($auctions as $auction) {
        unset($auction['id_user']);
        fputcsv($fp, $auction);
    }

    fclose($fp);

    # Send email with attachment
    $mail = new PHPMailer();

    if (CONFIG['USE_SMTP']) {
        $mail->isSMTP();
        $mail->Host       = CONFIG['SMTP_HOST'];
        $mail->SMTPAuth   = true;
        $mail->Username   = CONFIG['SMTP_USERNAME'];
        $mail->Password   = CONFIG['SMTP_PASSWORD'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
    }

    $mail->setFrom(CONFIG['SMTP_USERNAME'], 'Serwis Auction Auction');
    $mail->addAddress($task['email'], $task['first_name'] . ' ' . $task['last_name']);
    $mail->addAttachment($fpath);

    $mail->Subject = 'Raport aukcji konta w serwisie Auction Auction';

    $mail->Body = 'Oto wyczekiwany raport.';

    $mail->send();
    unlink($fpath);

    # Set task as completed
    $query = "
        UPDATE task
        SET
            is_completed = True
        WHERE
            id_task = {$task['id_task']};
    ";
    $db_connection->query($query);
}
