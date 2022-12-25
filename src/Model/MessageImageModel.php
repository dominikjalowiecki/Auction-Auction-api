<?php

namespace App\Model;

class MessageImageModel
{
    private $db_connection;

    public $id_message;
    public $id_user;
    public $id_image;
    public $path;
    public $data;

    public const FLAG_SUCCESS = 0x1;
    public const FLAG_FAILURE = 0x2;
    public const FLAG_MESSAGE_DOESNT_EXIST = 0x4;

    public function __construct($db_connection)
    {
        $this->db_connection = $db_connection;
    }

    public function checkIfLessThanOne()
    {
        $query = "
            SELECT
                COUNT(id_message_image) as count
            FROM
                message_image
            WHERE
                id_message = :id_message;

        ";

        $data = array(
            'id_message' => $this->id_message
        );

        $stmt = $this->db_connection->prepare($query);

        if ($stmt->execute($data)) {
            if ($stmt->fetch()["count"] > 0) return self::FLAG_FAILURE;
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
            INSERT INTO message_image
                (id_message, id_image)
            SELECT
                :id_message, :id_image
            FROM
                DUAL
            WHERE EXISTS
                (
                    SELECT
                        1
                    FROM
                        message
                    WHERE
                        id_message = :id_message AND
                        id_sender = :id_sender
                );
        ";

        $data = array(
            'id_message' => $this->id_message,
            'id_image' => $this->id_image,
            'id_sender' => $this->id_user
        );

        $stmt = $this->db_connection->prepare($query);

        if ($stmt->execute($data)) {
            if ($stmt->rowCount() > 0) {
                $this->db_connection->commit();
                return self::FLAG_SUCCESS;
            } else {
                $this->db_connection->rollback();
                return self::FLAG_MESSAGE_DOESNT_EXIST;
            }
        } else {
            $this->db_connection->rollback();
            return self::FLAG_FAILURE;
        }
    }
}
