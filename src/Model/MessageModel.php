<?php

namespace App\Model;

class MessageModel
{
    private $db_connection;

    public $id_message;
    public $id_discussion;
    public $id_item;
    public $id_user;
    public $content;

    public const FLAG_SUCCESS = 0x1;
    public const FLAG_FAILURE = 0x2;
    public const FLAG_DISCUSSION_DOESNT_EXIST = 0x4;
    public const FLAG_USER_IS_CREATOR = 0x8;
    public const FLAG_USER_NOT_ALLOWED = 0x10;

    public function __construct($db_connection)
    {
        $this->db_connection = $db_connection;
    }

    public function createMessage()
    {
        # Checking if user is item creator
        $query = "
            SELECT
                d.id_user,
                i.id_creator
            FROM
                discussion d
            JOIN
                item i
            USING(id_item)
            WHERE
                id_discussion = :id_discussion;
        ";

        $data = array(
            'id_discussion' => $this->id_discussion
        );

        $stmt = $this->db_connection->prepare($query);

        if ($stmt->execute($data)) {
            if ($stmt->rowCount()) {
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                if (
                    $row['id_user'] != $this->id_user &&
                    $row['id_creator'] != $this->id_user
                ) {
                    return self::FLAG_USER_NOT_ALLOWED;
                }
            } else {
                return self::FLAG_DISCUSSION_DOESNT_EXIST;
            }
        } else {
            return self::FLAG_FAILURE;
        }

        # Checking if discussion already exists and creating if not
        $query = "
            INSERT INTO message
                (id_discussion, id_sender, content, created_at)
            VALUES
                (:id_discussion, :id_sender, :content, UTC_TIMESTAMP);
        ";

        $data = array(
            'id_discussion' => $this->id_discussion,
            'id_sender' => $this->id_user,
            'content' => $this->content,
        );

        $stmt = $this->db_connection->prepare($query);

        if ($stmt->execute($data)) {
            $this->id_message = $this->db_connection->lastInsertId();
            return self::FLAG_SUCCESS;
        } else {
            return self::FLAG_FAILURE;
        }
    }
}
