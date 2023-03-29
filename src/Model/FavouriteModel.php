<?php

namespace App\Model;

class FavouriteModel
{
    private $db_connection;

    public $id_favourite;
    public $id_user;
    public $id_item;

    public const FLAG_SUCCESS = 0x1;
    public const FLAG_FAILURE = 0x2;
    public const FLAG_ALREADY_FAVOURITE = 0x4;
    public const FLAG_ITEM_DOESNT_EXIST = 0x8;
    public const FLAG_ITEM_NOT_FOUND = 0x10;
    public const FLAG_USER_IS_NOT_OWNER = 0x20;

    public function __construct($db_connection)
    {
        $this->db_connection = $db_connection;
    }

    public function addFavourite()
    {
        # Checking if item exists
        $query = "
            SELECT
                1
            FROM
                item
            WHERE
                id_item = :id_item AND
                id_creator != :id_creator AND
                is_closed = False;
        ";

        $data = array(
            'id_item' => $this->id_item,
            'id_creator' => $this->id_user
        );

        $stmt = $this->db_connection->prepare($query);

        $stmt->execute($data);
        if ($stmt->rowCount() == 0) {
            return self::FLAG_ITEM_DOESNT_EXIST;
        }

        # Adding new favourite
        $query = "
            INSERT INTO favourite
                (id_user, id_item)
            SELECT
                :id_user, :id_item
            FROM
                DUAL
            WHERE NOT EXISTS
                (
                    SELECT
                        1
                    FROM
                        favourite
                    WHERE
                        id_user = :id_user AND
                        id_item = :id_item
                );
        ";

        $data = array(
            'id_user' => $this->id_user,
            'id_item' => $this->id_item
        );

        $stmt = $this->db_connection->prepare($query);

        if ($stmt->execute($data)) {
            if ($stmt->rowCount() > 0) {
                $this->id_favourite = $this->db_connection->lastInsertId();
                return self::FLAG_SUCCESS;
            } else {
                return self::FLAG_ALREADY_FAVOURITE;
            }
        } else {
            return self::FLAG_FAILURE;
        }
    }

    public function deleteFavourite()
    {
        # Checking if user have given favourite
        $query = "
            SELECT
                id_user
            FROM
                favourite
            WHERE
                id_favourite = :id_favourite;
        ";

        $data = array(
            'id_favourite' => $this->id_favourite
        );

        $stmt = $this->db_connection->prepare($query);

        if ($stmt->execute($data)) {
            if ($stmt->rowCount()) {
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($this->id_user != $row['id_user']) {
                    return self::FLAG_USER_IS_NOT_OWNER;
                }
            } else {
                return self::FLAG_ITEM_NOT_FOUND;
            }
        } else {
            return self::FLAG_FAILURE;
        }

        # Deleting given favourite
        $query = "
            DELETE FROM
                favourite
            WHERE
                id_favourite = :id_favourite;
        ";

        $stmt = $this->db_connection->prepare($query);

        if ($stmt->execute($data)) {
            return self::FLAG_SUCCESS;
        } else {
            return self::FLAG_FAILURE;
        }
    }
}
