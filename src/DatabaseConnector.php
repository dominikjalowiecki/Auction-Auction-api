<?php

namespace App;

require_once(__DIR__ . '/config.php');

final class DatabaseConnector
{
    private static $db_connection = null;

    private function __construct()
    {
    }

    public static function getConnection(): \PDO
    {
        if (self::$db_connection == null) {
            $dsn = "mysql:host=" . CONFIG['DB_HOST'] . ";port=" . CONFIG['DB_PORT'] . ";dbname=" . CONFIG['DB_NAME'] . ";charset=utf8mb4";
            $user = CONFIG['DB_USER'];
            $password = CONFIG['DB_PASS'];

            try {
                self::$db_connection = new \PDO($dsn, $user, $password);
                self::$db_connection->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_WARNING);
            } catch (\PDOException $e) {
                throw new \PDOException($e->getMessage(), (int) $e->getCode());
            }
        }

        return self::$db_connection;
    }
}
