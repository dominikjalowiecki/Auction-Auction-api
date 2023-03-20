<?php

namespace App\Model;

require_once(__DIR__ . '/../config.php');

class DiscussionModel
{
    private $db_connection;

    public $id_message;
    public $id_discussion;
    public $id_item;
    public $id_creator;
    public $id_user;
    public $content;

    public $data;
    public $pagination = 1;
    public $pages;

    public const FLAG_SUCCESS = 0x1;
    public const FLAG_FAILURE = 0x2;
    public const FLAG_ITEM_DOESNT_EXIST = 0x4;
    public const FLAG_USER_IS_CREATOR = 0x8;
    public const FLAG_DISCUSSION_DOESNT_EXIST = 0x10;
    public const FLAG_USER_NOT_ALLOWED = 0x20;
    public const FLAG_OVERFLOW = 0x40;

    public function __construct($db_connection)
    {
        $this->db_connection = $db_connection;
    }

    public function createDiscussion()
    {
        $this->db_connection->beginTransaction();
        # Checking if user is item creator
        $query = "
            SELECT
                id_creator
            FROM
                item
            WHERE
                id_item = :id_item;
        ";

        $data = array(
            'id_item' => $this->id_item
        );

        $stmt = $this->db_connection->prepare($query);

        if ($stmt->execute($data)) {
            if ($stmt->rowCount() > 0) {
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($row['id_creator'] == $this->id_user) {
                    return self::FLAG_USER_IS_CREATOR;
                }
            } else {
                return self::FLAG_ITEM_DOESNT_EXIST;
            }
        } else {
            return self::FLAG_FAILURE;
        }

        # Checking if discussion already exists and creating her if not
        $query = "
            SELECT
                id_discussion
            FROM
                discussion
            WHERE
                id_item = :id_item AND
                id_user = :id_user
        ";

        $data = array(
            'id_item' => $this->id_item,
            'id_user' => $this->id_user
        );

        $stmt = $this->db_connection->prepare($query);

        if ($stmt->execute($data)) {
            if ($stmt->rowCount()) {
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                $this->id_discussion = $row['id_discussion'];
            } else {
                $query = "
                    INSERT INTO discussion
                        (id_item, id_user)
                    VALUES
                        (:id_item, :id_user);
                ";

                $data = array(
                    'id_item' => $this->id_item,
                    'id_user' => $this->id_user
                );

                $stmt = $this->db_connection->prepare($query);

                if (!$stmt->execute($data)) {
                    $this->db_connection->rollback();
                    return self::FLAG_FAILURE;
                }

                $this->id_discussion = $this->db_connection->lastInsertId();
            }
        } else {
            return self::FLAG_FAILURE;
        }

        $query = "
            INSERT INTO message
                (id_discussion, id_sender, content, created_at)
            VALUES
                (:id_discussion, :id_sender, :content, UTC_TIMESTAMP);
        ";

        $data = array(
            'id_discussion' => $this->id_discussion,
            'id_sender' => $this->id_user,
            'content' => $this->content
        );

        $stmt = $this->db_connection->prepare($query);

        if ($stmt->execute($data)) {
            $this->id_message = $this->db_connection->lastInsertId();
            $this->db_connection->commit();
            return self::FLAG_SUCCESS;
        } else {
            $this->db_connection->rollback();
            return self::FLAG_FAILURE;
        }
    }

    public function getDiscussion()
    {
        $query = "
            SELECT
                d.id_item,
                d.id_user,
                i.id_creator
            FROM
                discussion d
            JOIN
                item i
            USING(id_item)
            WHERE
                d.id_discussion = :id_discussion
        ";

        $data = array(
            'id_discussion' => $this->id_discussion,
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
                $this->id_creator = $row['id_creator'];
                $this->id_item = $row['id_item'];
            } else {
                return self::FLAG_DISCUSSION_DOESNT_EXIST;
            }
        } else {
            return self::FLAG_FAILURE;
        }

        # Getting row count
        $query = "
            SELECT
                COUNT(*) count
            FROM
                message m
            WHERE
                m.id_discussion = :id_discussion
        ";

        $data = array(
            'id_discussion' => $this->id_discussion,
        );

        $stmt = $this->db_connection->prepare($query);
        $stmt->execute($data);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        $pagination = CONFIG['MESSAGES_PAGINATION'];
        $this->pages = ceil($row['count'] / $pagination);

        if ($this->pagination > $this->pages) {
            return self::FLAG_OVERFLOW;
        }

        # Getting details of all available items
        $pagination_start = ($pagination * $this->pagination) - $pagination;

        $query = "
            SELECT
                m.id_sender,
                m.content,
                m.created_at,
                m.is_read,
                (
                    SELECT
                		GROUP_CONCAT(im.image_url)
                    FROM
                    	image im
                    JOIN
                    	message_image mi
                    USING
                    	(id_image)
                    WHERE
                    	mi.id_message = m.id_message
                ) images
            FROM
                message m
            WHERE
                m.id_discussion = :id_discussion
            ORDER BY
                m.created_at DESC
            LIMIT
                $pagination
            OFFSET
                $pagination_start;
        ";

        $stmt = $this->db_connection->prepare($query);

        if ($stmt->execute($data)) {
            $this->data = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $no_rows = count($this->data);
            for ($i = 0; $i < $no_rows; $i++)
                $this->data[$i]["images"] = explode(',', $this->data[$i]["images"]);

            $query = "
                UPDATE
                    message
                SET
                    is_read = True
                WHERE
                    id_discussion = :id_discussion AND
                    id_sender != :id_sender
                ORDER BY
                    created_at DESC
                LIMIT
                    1;
            ";

            $data = array(
                "id_discussion" => $this->id_discussion,
                "id_sender" => $this->id_user
            );

            $stmt = $this->db_connection->prepare($query);
            $stmt->execute($data);

            return self::FLAG_SUCCESS;
        } else {
            return self::FLAG_FAILURE;
        }
    }
}
