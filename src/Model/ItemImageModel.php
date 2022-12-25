<?php

namespace App\Model;

class ItemImageModel
{
    private $db_connection;

    public $id_item_image;
    public $id_user;
    public $id_item;
    public $id_image;
    public $is_main;
    public $path;
    public $data;

    public const FLAG_SUCCESS = 0x1;
    public const FLAG_FAILURE = 0x2;
    public const FLAG_ERROR = 0x4;

    public function __construct($db_connection)
    {
        $this->db_connection = $db_connection;
    }

    public function checkIfLessThanFour()
    {
        $query = "
            SELECT
                COUNT(id_item_image) as count
            FROM
                item_image
            WHERE
                id_item = :id_item;

        ";

        $data = array(
            'id_item' => $this->id_item
        );

        $stmt = $this->db_connection->prepare($query);

        if ($stmt->execute($data)) {
            if ($stmt->fetch()["count"] > 3) return self::FLAG_FAILURE;
            return self::FLAG_SUCCESS;
        } else {
            return self::FLAG_FAILURE;
        }
    }

    public function addImage()
    {
        $query = "
            SELECT
                id_image
            FROM
                image
            WHERE
                image_url = '{$this->path}';
        ";

        if ($stmt = $this->db_connection->query($query)) {
            $row = $stmt->fetch(\PDO::FETCH_NUM);
            $this->id_image = $row[0] ?? False;
        } else {
            return self::FLAG_FAILURE;
        }

        $this->db_connection->beginTransaction();
        if (!$this->id_image) {
            # Creating new image
            $query = "
                INSERT INTO image
                    (image_url)
                VALUES
                    (:image_url);
            ";

            $data = array(
                'image_url' => $this->path
            );

            $stmt = $this->db_connection->prepare($query);

            if ($stmt->execute($data)) {
                $this->id_image = $this->db_connection->lastInsertId();
            } else {
                $this->db_connection->rollback();
                return self::FLAG_FAILURE;
            }
        }

        # Creating new item_image
        $query = "
            INSERT INTO item_image
                (id_item, id_image, is_main)
            SELECT
                :id_item, :id_image, :is_main
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
                        id_creator = :id_creator
                );
        ";

        $data = array(
            'id_item' => $this->id_item,
            'id_image' => $this->id_image,
            'is_main' => $this->is_main,
            'id_creator' => $this->id_user
        );

        $stmt = $this->db_connection->prepare($query);

        if ($stmt->execute($data)) {
            if ($stmt->rowCount() > 0) {
                $this->db_connection->commit();
                return self::FLAG_SUCCESS;
            } else {
                $this->db_connection->rollback();
                return self::FLAG_ERROR;
            }
        } else {
            $this->db_connection->rollback();
            return self::FLAG_FAILURE;
        }
    }
}
