<?php

namespace App\Model;

class ProvinceModel
{
    private $db_connection;

    public $data;

    public const FLAG_SUCCESS = 0x1;
    public const FLAG_FAILURE = 0x2;

    public function __construct($db_connection)
    {
        $this->db_connection = $db_connection;
    }

    public function getProvinces()
    {
        # Getting details of all available provinces
        $query = "
            SELECT
                *
            FROM
                province;
        ";

        $stmt = $this->db_connection->prepare($query);

        if ($stmt->execute()) {
            $this->data = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            return self::FLAG_SUCCESS;
        } else {
            return self::FLAG_FAILURE;
        }
    }
}
