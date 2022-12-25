<?php

namespace App\Model;

class AuthenticationModel
{
    private $db_connection;

    public $id_user;
    public $pswc;

    public const FLAG_SUCCESS = 0x1;
    public const FLAG_FAILURE = 0x2;

    public function __construct($db_connection)
    {
        $this->db_connection = $db_connection;
    }

    public function updateLastOnline()
    {
        $query = "
            UPDATE
                user
            SET
                last_online = UTC_TIMESTAMP
            WHERE
                id_user = :id_user;
        ";

        $data = array(
            "id_user" => $this->id_user
        );

        $stmt = $this->db_connection->prepare($query);
        $stmt->execute($data);
    }

    public function getUserDetails()
    {
        $query = "
            SELECT
                pswc
            FROM
                user
            WHERE
                id_user = :id_user;
        ";

        $data = array(
            "id_user" => $this->id_user
        );

        $stmt = $this->db_connection->prepare($query);
        if ($stmt->execute($data)) {
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            $this->pswc = $row['pswc'];

            return self::FLAG_SUCCESS;
        } else {
            return self::FLAG_FAILURE;
        }
    }
}
