<?php

namespace App\Model;

require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/../getIpAddress.php');
require_once(__DIR__ . '/../helperFunctions.php');

class UserModel
{
    private $db_connection;

    public $id_logged_user;
    public $id_user;
    public $username;
    public $first_name;
    public $last_name;
    public $email;
    public $birth_date;
    public $password;
    public $old_password;
    public $pswc;
    public $avatar;
    public $id_country;
    public $id_province;
    public $postcode;
    public $city;
    public $street;
    public $phone;
    public $last_online;
    public $created_at;
    public $id_notification;
    public $data;
    public $pages;
    public $pagination = 1;
    public $order_by;
    public $category;
    public $search;
    public $type;
    public $token;
    public $refresh_token;
    public $items_count;

    public const FLAG_SUCCESS = 0x1;
    public const FLAG_FAILURE = 0x2;
    public const FLAG_USERNAME_FOUND = 0x4;
    public const FLAG_ALREADY_ACTIVATED = 0x8;
    public const FLAG_NOT_FOUND = 0x10;
    public const FLAG_USER_NOT_ACTIVATED = 0x20;
    public const FLAG_INVALID_CREDENTIALS = 0x40;
    public const FLAG_ERROR = 0x80;
    public const FLAG_OVERFLOW = 0x100;
    public const FLAG_EMAIL_FOUND = 0x200;
    public const FLAG_IGNORE = 0x400;


    public function __construct($db_connection)
    {
        $this->db_connection = $db_connection;
    }

    public function getAuctionsReport()
    {
        $query = "
            SELECT
                is_completed,
                created_at
            FROM
                task
            WHERE
                task_type = 'AUCTIONS_REPORT' AND
                id_user = :id_user AND
                DATEDIFF(UTC_TIMESTAMP, created_at) < 1
            ORDER BY
                created_at DESC
            LIMIT
                1;
        ";

        $data = array(
            "id_user" => $this->id_user
        );

        $stmt = $this->db_connection->prepare($query);

        if ($stmt->execute($data)) {
            if (!$stmt->rowCount())
                return self::FLAG_NOT_FOUND;

            $this->data = $stmt->fetch(\PDO::FETCH_ASSOC);

            return self::FLAG_SUCCESS;
        } else {
            return self::FLAG_FAILURE;
        }
    }

    public function createAuctionsReport()
    {
        $query = "
            INSERT INTO task
                (task_type, id_user, created_at)
            SELECT
                'AUCTIONS_REPORT', '{$this->id_user}', UTC_TIMESTAMP
            FROM
                DUAL
            WHERE
                DATEDIFF(
                    UTC_TIMESTAMP,
                    COALESCE(
                        (
                            SELECT
                                created_at
                            FROM
                                task
                            WHERE
                                id_user = '{$this->id_user}'
                            ORDER BY
                                created_at DESC
                            LIMIT 1
                        ),
                        '1970-01-01'
                    )
                ) > 0;
        ";

        if ($stmt = $this->db_connection->query($query)) {
            if (!$stmt->rowCount())
                return self::FLAG_FAILURE;

            return self::FLAG_SUCCESS;
        } else {
            return self::FLAG_FAILURE;
        }
    }

    public function register()
    {
        # Checking if username and email are already in database.
        $query = "
            SELECT
                username, email
            FROM
                user
            WHERE
                username = :username OR
                email = :email
            LIMIT
                0,1;
        ";

        $data = array(
            "username" => $this->username,
            "email" => $this->email,
        );

        $stmt = $this->db_connection->prepare($query);

        if ($stmt->execute($data)) {
            if ($stmt->rowCount()) {
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                $flag = 0;

                if ($row['username'] == $this->username) {
                    $flag = $flag | self::FLAG_USERNAME_FOUND;
                }

                if ($row['email'] == $this->email) {
                    $flag = $flag | self::FLAG_EMAIL_FOUND;
                }
                return $flag;
            }
        } else {
            return self::FLAG_FAILURE;
        }

        # Adding new user to the database.
        $this->birth_date = date('Y-m-d', strtotime($this->birth_date));

        $options = array(
            'cost' => 12,
        );
        $this->password = password_hash($this->password, PASSWORD_DEFAULT, $options);

        $this->avatar = md5($this->email);
        $this->avatar = "https://www.gravatar.com/avatar/" . $this->avatar . "?d=retro";

        $query = "
        INSERT INTO user
            (username, first_name, last_name, email, birth_date, password, avatar, last_online, created_at, reset_password_request)
        VALUES
            (:username, :first_name, :last_name, :email, :birth_date, :password, :avatar, UTC_TIMESTAMP, UTC_TIMESTAMP, UTC_TIMESTAMP);
        ";

        $data = array(
            'username' => $this->username,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'birth_date' => $this->birth_date,
            'password' =>  $this->password,
            'avatar' => $this->avatar,
        );

        $stmt = $this->db_connection->prepare($query);

        if ($stmt->execute($data)) {
            $this->id_user = $this->db_connection->lastInsertId();
            return self::FLAG_SUCCESS;
        }
        return self::FLAG_FAILURE;
    }

    public function activate()
    {
        # Checking if user is already activated
        $query = "
            SELECT
                is_active
            FROM
                user
            WHERE
                id_user = :id_user
            LIMIT
                0,1;
        ";

        $data = array(
            "id_user" => $this->id_user
        );

        $stmt = $this->db_connection->prepare($query);

        if ($stmt->execute($data)) {
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row["is_active"]) {
                return self::FLAG_ALREADY_ACTIVATED;
            }
        } else {
            return self::FLAG_FAILURE;
        }

        # Activating user account
        $query = "
            UPDATE
                user
            SET
                is_active = True
            WHERE
                id_user = :id_user;
        ";

        $data = array(
            "id_user" => $this->id_user
        );

        $stmt = $this->db_connection->prepare($query);

        if ($stmt->execute($data)) {
            $query = "
                INSERT INTO notification
                    (id_recipient, title_html, body_html, href, created_at)
                VALUES
                    (:id_recipient, :title_html, :body_html, :href, UTC_TIMESTAMP);
            ";

            $data = array(
                "id_recipient" => $this->id_user,
                "title_html" => "Zaktualizuj swoje dane!",
                "body_html" => "Zaktualizuj swoje dane!",
                "href" => "link"
            );

            $stmt = $this->db_connection->prepare($query);
            $stmt->execute($data);

            return self::FLAG_SUCCESS;
        }
        return self::FLAG_FAILURE;
    }

    public function login()
    {
        # Getting the details of the logging in user
        $query = "
            SELECT
                id_user,
                username,
                email,
                password,
                pswc,
                is_active
            FROM
                user
            WHERE
                username = :username OR
                email = :email
            LIMIT
                0,1;
        ";

        $data = array(
            'username' => $this->username,
            'email' => $this->email,
        );

        $stmt = $this->db_connection->prepare($query);

        if ($stmt->execute($data)) {
            # Checking if user exists at all
            if ($stmt->rowCount()) {
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);

                # BLOCK LOGIN IF FIFTH LAST IS 30 SEC | throttling
                $query = "
                SELECT
                    created_at cr, UTC_TIMESTAMP ts
                FROM
                    login_attempt
                WHERE
                    id_user = :id_user
                ORDER BY
                    created_at DESC
                LIMIT
                    4,1;
                ";

                $data = array(
                    "id_user" => $row['id_user'],
                );
                $stmt = $this->db_connection->prepare($query);
                if ($stmt->execute($data)) {
                    if ($stmt->rowCount()) {
                        $rowe = $stmt->fetch(\PDO::FETCH_ASSOC);
                        if (strtotime($rowe['ts'] . ' UTC') - strtotime($rowe['cr'] . ' UTC') < 30) {
                            return self::FLAG_FAILURE;
                        }
                    }
                } else {
                    return self::FLAG_FAILURE;
                }
                # Checking if user account is active
                if ($row["is_active"]) {
                    $is_valid = password_verify($this->password, $row['password']) === True ? 1 : 0;
                    # Checking if given password is valid
                    $this->id_user = $row['id_user'];
                    if ($is_valid) {
                        $this->username = $row['username'];
                        $this->email = $row['email'];
                        $this->pswc = $row['pswc'];

                        $query = "
                        INSERT INTO login_attempt
                            (id_user, is_successful, ip_address, user_agent, created_at)
                        VALUES
                            (:id_user, :is_successful, :ip_address, :user_agent, UTC_TIMESTAMP);
                        ";

                        $data = array(
                            "id_user" => $this->id_user,
                            "is_successful" => $is_valid,
                            "ip_address" => getIpAddress(),
                            "user_agent" => $_SERVER['HTTP_USER_AGENT'] ?? null,
                        );

                        $stmt = $this->db_connection->prepare($query);
                        $stmt->execute($data);

                        return self::FLAG_SUCCESS;
                    } else {
                        $query = "
                        INSERT INTO login_attempt
                            (id_user, is_successful, ip_address, user_agent, created_at)
                        VALUES
                            (:id_user, :is_successful, :ip_address, :user_agent, UTC_TIMESTAMP);
                        ";

                        $data = array(
                            "id_user" => $this->id_user,
                            "is_successful" => $is_valid,
                            "ip_address" => getIpAddress(),
                            "user_agent" => $_SERVER['HTTP_USER_AGENT'] ?? null,
                        );

                        $stmt = $this->db_connection->prepare($query);
                        $stmt->execute($data);

                        return self::FLAG_INVALID_CREDENTIALS;
                    }
                } else {
                    return self::FLAG_USER_NOT_ACTIVATED;
                }
            } else {
                return self::FLAG_NOT_FOUND;
            }
        } else {
            return self::FLAG_FAILURE;
        }
    }

    public function changePassword()
    {
        # Checking if given password match the old one
        $query = "
            SELECT
                password
            FROM
                user
            WHERE
                id_user = :id_user
        ";

        $data = array(
            "id_user" => $this->id_user
        );

        $stmt = $this->db_connection->prepare($query);

        if ($stmt->execute($data)) {
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            $is_valid = password_verify($this->old_password, $row['password']);
            if (!$is_valid) {
                return self::FLAG_INVALID_CREDENTIALS;
            }
        } else {
            return self::FLAG_FAILURE;
        }

        # Changing password to the given, new one
        $query = "
            UPDATE
                user
            SET
                password = :password,
                pswc = pswc + 1
            WHERE
                id_user = :id_user;
        ";

        $options = array(
            'cost' => 12,
        );
        $this->password = password_hash($this->password, PASSWORD_DEFAULT, $options);

        $data = array(
            "id_user" => $this->id_user,
            "password" => $this->password
        );

        $stmt = $this->db_connection->prepare($query);

        if ($stmt->execute($data)) {
            return self::FLAG_SUCCESS;
        } else {
            return self::FLAG_FAILURE;
        }
    }

    public function getLoginAttempts()
    {
        # Getting row count
        $query = "
            SELECT
                COUNT(*) count
            FROM
                login_attempt
            WHERE
                id_user = :id_user;
        ";

        $data = array(
            "id_user" => $this->id_user
        );

        $stmt = $this->db_connection->prepare($query);
        $stmt->execute($data);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->items_count = $row['count'];
        $pagination = CONFIG['LOGIN_ATTEMPTS_PAGINATION'];
        $this->pages = ceil($this->items_count / $pagination);

        if ($this->pagination > $this->pages) {
            return self::FLAG_OVERFLOW;
        }

        $pagination_start = ($pagination * $this->pagination) - $pagination;

        # Getting details of current user login attempts
        $query = "
            SELECT
                is_successful,
                ip_address,
                user_agent,
                created_at
            FROM
                login_attempt
            WHERE
                id_user = :id_user
        ";

        switch ($this->order_by) {
            case 'oldest':
                $query .= "
                    ORDER BY
                        created_at ASC
                ";
                break;
            default:
                #newest
                $query .= "
                    ORDER BY
                        created_at DESC
                ";
                break;
        }

        $query .= "
            LIMIT
                $pagination
            OFFSET
                $pagination_start;
        ";

        $stmt = $this->db_connection->prepare($query);

        if ($stmt->execute($data)) {
            $this->data = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            return self::FLAG_SUCCESS;
        } else {
            return self::FLAG_FAILURE;
        }
    }

    public function getNotifications()
    {
        # Getting notifications of current user
        $query = "
            SELECT
                id_notification,
                title_html,
                body_html,
                href,
                id_item,
                created_at
            FROM
                notification
            WHERE
                id_recipient = :id_recipient AND
                is_read = False;
        ";

        $data = array(
            "id_recipient" => $this->id_user
        );

        $stmt = $this->db_connection->prepare($query);

        if ($stmt->execute($data)) {
            $this->data = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            return self::FLAG_SUCCESS;
        } else {
            return self::FLAG_FAILURE;
        }
    }

    public function getFavourites()
    {
        # Getting favourites items of current user
        $query = "
            SELECT
                id_favourite,
                id_item
            FROM
                favourite
            WHERE
                id_user = :id_user; 
        ";

        $data = array(
            "id_user" => $this->id_user
        );

        $stmt = $this->db_connection->prepare($query);

        if ($stmt->execute($data)) {
            $this->data = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            return self::FLAG_SUCCESS;
        } else {
            return self::FLAG_FAILURE;
        }
    }

    public function changeNotification()
    {
        # Update notification "is_read"
        $query = "
            UPDATE
                notification
            SET
                is_read = True
            WHERE
                id_notification = :id_notification AND
                id_recipient = :id_recipient AND
                is_read = False;
        ";

        $data = array(
            "id_notification" => $this->id_notification,
            "id_recipient" => $this->id_user
        );

        $stmt = $this->db_connection->prepare($query);

        if ($stmt->execute($data)) {
            if ($stmt->rowCount()) {
                return self::FLAG_SUCCESS;
            } else {
                return self::FLAG_ERROR;
            }
        } else {
            return self::FLAG_FAILURE;
        }
    }

    public function getMe()
    {
        # Getting current user details
        $query = "
            SELECT
                id_user,
                username,
                first_name,
                last_name,
                email,
                phone,
                birth_date,
                avatar,
                (
                    SELECT
                        name
                    FROM
                        country
                    WHERE
                        id_country = u.id_country
                ) as country,
                (
                    SELECT
                        name
                    FROM
                        province
                    WHERE
                        id_province = u.id_province
                ) as province,
                postcode,
                city,
                street,
                created_at
            FROM
                user u
            WHERE
                id_user = :id_user
            LIMIT
                1;
        ";

        $data = array(
            "id_user" => $this->id_user,
        );

        $stmt = $this->db_connection->prepare($query);

        if ($stmt->execute($data)) {
            $this->data = $stmt->fetch(\PDO::FETCH_ASSOC);

            return self::FLAG_SUCCESS;
        } else {
            return self::FLAG_FAILURE;
        }
    }

    public function changeMe()
    {
        # Changing current user informations
        $query_parts = array();

        $data = array(
            'id_user' => $this->id_user
        );

        if (!empty($this->phone)) {
            $query_parts[] = "phone = :phone";
            $data['phone'] = $this->phone;
        }
        if (!empty($this->id_country)) {
            $query_parts[] = "id_country = :id_country";
            $data['id_country'] = $this->id_country;
        }
        if (!empty($this->id_province)) {
            $query_parts[] = "id_province = :id_province";
            $data['id_province'] = $this->id_province;
        }
        if (!empty($this->postcode)) {
            $query_parts[] = "postcode = :postcode";
            $data['postcode'] = $this->postcode;
        }
        if (!empty($this->city)) {
            $query_parts[] = "city = :city";
            $data['city'] = $this->city;
        }
        if (!empty($this->street)) {
            $query_parts[] = "street = :street";
            $data['street'] = $this->street;
        }

        $query = "
            UPDATE 
                user
            SET
        ";
        $query .= implode(', ', $query_parts);
        $query .= "
            WHERE
                id_user = :id_user
		";
        if (isset($data['id_country'])) {
            $query .= "
				AND EXISTS
					(
						SELECT
							1
						FROM
							country
						WHERE
							id_country = :id_country   
					)
			";
        }
        if (isset($data['id_province'])) {
            $query .= "
				AND EXISTS
					(
						SELECT
							1
						FROM
							province
						WHERE
							id_province = :id_province   
					)
			";
        }
        $query .= ";";

        $stmt = $this->db_connection->prepare($query);

        if ($stmt->execute($data)) {
            if ($stmt->rowCount()) {
                return self::FLAG_SUCCESS;
            } else {
                return self::FLAG_NOT_FOUND;
            }
        } else {
            return self::FLAG_FAILURE;
        }
    }

    public function getUser()
    {
        # Getting details of given user
        $query = "
            SELECT
                username,
                first_name,
                last_name,
                email,
                phone,
                avatar,
                (
                    SELECT
                        name
                    FROM
                        country
                    WHERE
                        id_country = u.id_country
                ) as country,
                (
                    SELECT
                        name
                    FROM
                        province
                    WHERE
                        id_province = u.id_province
                ) as province,
                postcode,
                city,
                street,
                last_online
            FROM
                user u
            WHERE
                id_user = :id_user
            LIMIT
                0,1;
        ";

        $data = array(
            "id_user" => $this->id_user
        );

        $stmt = $this->db_connection->prepare($query);

        if ($stmt->execute($data)) {
            if ($stmt->rowCount()) {
                $query = "
                    SELECT
                        id_item
                    FROM
                        item
                    WHERE
                        (
                            id_creator = :id_logged_user OR id_creator = :id_user
                        ) AND
                        (
                            id_winner = :id_user OR id_winner = :id_logged_user    
                        )
                    LIMIT
                        1;
                ";

                $data = array(
                    "id_logged_user" => $this->id_logged_user,
                    "id_user" => $this->id_user
                );

                $stmte = $this->db_connection->prepare($query);

                if ($stmte->execute($data)) {
                    if ($stmte->rowCount() === 1) {
                        $this->data = $stmt->fetch(\PDO::FETCH_ASSOC);
                    } else {
                        $tmp = $stmt->fetch(\PDO::FETCH_ASSOC);

                        $this->data = array(
                            "username" => $tmp["username"],
                            "avatar" => $tmp["avatar"],
                            "last_online" => $tmp["last_online"]
                        );
                    }

                    return self::FLAG_SUCCESS;
                } else {
                    return self::FLAG_FAILURE;
                }
            } else {
                return self::FLAG_NOT_FOUND;
            }
        } else {
            return self::FLAG_FAILURE;
        }
    }

    public function checkResetPassword()
    {
        # Checking details of given user
        $query = "
            SELECT
                id_user,
                first_name,
                last_name,
                is_active,
                reset_password_request
            FROM
                user
            WHERE
                email = :email
            LIMIT
                0,1;
        ";

        $data = array(
            "email" => $this->email
        );

        $stmt = $this->db_connection->prepare($query);

        if ($stmt->execute($data)) {
            if ($stmt->rowCount()) {
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($row['is_active']) {
                    if ((time() - strtotime($row['reset_password_request'] . ' UTC')) / 60 > 15) # If reset password is issued more than 15 minutes
                    {
                        $this->id_user = $row['id_user'];
                        $this->first_name = $row['first_name'];
                        $this->last_name = $row['last_name'];

                        $query = "
                            UPDATE
                                user
                            SET
                                reset_password_request = UTC_TIMESTAMP
                            WHERE
                                id_user = :id_user;
                        ";

                        $data = array(
                            "id_user" => $this->id_user
                        );

                        $stmt = $this->db_connection->prepare($query);

                        if (!$stmt->execute($data))
                            return self::FLAG_FAILURE;

                        return self::FLAG_SUCCESS;
                    }

                    return self::FLAG_IGNORE;
                } else {
                    return self::FLAG_USER_NOT_ACTIVATED;
                }
            } else {
                return self::FLAG_NOT_FOUND;
            }
        } else {
            return self::FLAG_FAILURE;
        }
    }

    public function resetPassword()
    {
        # Resetting user password
        $query = "
            UPDATE
                user
            SET
                password = :password,
                pswc = pswc + 1
            WHERE
                id_user = :id_user;
        ";

        $options = array(
            'cost' => 12,
        );
        $this->password = password_hash($this->password, PASSWORD_DEFAULT, $options);

        $data = array(
            "password" => $this->password,
            "id_user" => $this->id_user
        );

        $stmt = $this->db_connection->prepare($query);

        if ($stmt->execute($data)) {
            return self::FLAG_SUCCESS;
        } else {
            return self::FLAG_FAILURE;
        }
    }

    public function getItems()
    {
        $is_search = $this->search !== "";

        # Getting row count
        $query = "
            SELECT
                COUNT(*) count
            FROM
                item i
            JOIN
                category c
            USING
                (id_category)
            WHERE
                c.name
            LIKE
                :category AND
        ";
        $query .= ($is_search) ? "MATCH(i.name) AGAINST(:search IN NATURAL LANGUAGE MODE) AND" : "";

        switch ($this->type) {
            case 'participated':
                $query .= "
                        :id_creator IN (
                            SELECT
                                id_bidder
                            FROM
                                bid b
                            WHERE
                                b.id_item = i.id_item
                        ) AND
                        i.is_closed = False;
                ";
                break;
            case 'ended':
                $query .= "
                        i.id_creator = :id_creator AND
                        i.is_closed = True;
                ";
                break;
            case 'won':
                $query .= "
                        i.id_winner = :id_creator AND
                        i.is_closed = True;
                ";
                break;
            default:
                # created
                $query .= "
                    i.id_creator = :id_creator AND
                    i.is_closed = False;
                ";
                break;
        }

        $data = array(
            'category' => $this->category,
            'id_creator' => $this->id_user
        );

        if ($is_search)
            $data['search'] = $this->search;

        $stmt = $this->db_connection->prepare($query);
        $stmt->execute($data);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->items_count = $row['count'];
        $pagination = CONFIG['PAGINATION'];
        $this->pages = ceil($this->items_count / $pagination);

        if ($this->pages == 0) {
            $this->pagination = 0;
            return self::FLAG_SUCCESS;
        }

        if ($this->pagination > $this->pages) {
            return self::FLAG_OVERFLOW;
        }

        # Getting details of all available items
        $pagination_start = ($pagination * $this->pagination) - $pagination;

        $query = "
            SELECT
                i.id_item,
                i.name,
                i.description,
                i.id_creator,
                (
                    SELECT
                        name
                    FROM
                        category
                    WHERE
                        id_category = i.id_category
                ) as category,
                i.starting_price,
                i.starting_time,
                i.id_winner,
                i.ending_price,
                i.ending_time,
                MAX(b.bid_price) max_bid,
                (
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
                ) id_bidder,
                (
                    SELECT
                		im.image_url image
                    FROM
                    	image im
                    JOIN
                    	item_image ii
                    USING
                    	(id_image)
                    WHERE
                    	ii.id_item = i.id_item AND
                    	ii.is_main = True
                    LIMIT
                    	1
                ) image
            FROM
                item i
            JOIN
                category c
            USING
                (id_category)
            LEFT JOIN
                bid b
            USING
                (id_item)
            WHERE
                c.name
            LIKE
                :category  AND
        ";

        $query .= ($is_search) ? "MATCH(i.name) AGAINST(:search IN NATURAL LANGUAGE MODE) AND" : "";


        switch ($this->type) {
            case 'participated':
                $query .= "
                            :id_creator IN (
                                SELECT
                                    id_bidder
                                FROM
                                    bid b
                                WHERE
                                    b.id_item = i.id_item
                            ) AND
                            i.is_closed = False
                    ";
                break;
            case 'ended':
                $query .= "
                            i.id_creator = :id_creator AND
                            i.is_closed = True
                    ";
                break;
            case 'won':
                $query .= "
                            i.id_winner = :id_creator AND
                            i.is_closed = True
                    ";
                break;
            default:
                # created
                $query .= "
                        i.id_creator = :id_creator AND
                        i.is_closed = False
                    ";
                break;
        }

        $query .= "
                GROUP BY
                    i.id_item
            ";

        switch ($this->order_by) {
            case 'lowest':
                $query .= "
                    ORDER BY
                        max_bid ASC, starting_price ASC
                ";
                break;
            case 'higest':
                $query .= "
                    ORDER BY
                        max_bid DESC, starting_price DESC
                ";
                break;
            case 'oldest':
                $query .= "
                    ORDER BY
                        i.starting_time ASC
                ";
                break;
            default:
                # newest
                $query .= "
                    ORDER BY
                        i.starting_time DESC
                ";
                break;
        }

        $query .= "
            LIMIT
                $pagination
            OFFSET
                $pagination_start;
        ";

        $stmt = $this->db_connection->prepare($query);

        if ($stmt->execute($data)) {
            $this->data = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            return self::FLAG_SUCCESS;
        } else {
            return self::FLAG_FAILURE;
        }
    }

    public function getDiscussions()
    {
        # Getting row count
        $query = "
            SELECT
                COUNT(*) count
            FROM
                discussion d
            JOIN
                item i
            USING
                (id_item)
            WHERE
                d.id_user = :id_user OR
                i.id_creator = :id_user;
        ";

        $data = array(
            'id_user' => $this->id_user
        );

        $stmt = $this->db_connection->prepare($query);
        $stmt->execute($data);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        $pagination = CONFIG['PAGINATION'];
        $this->pages = ceil($row['count'] / $pagination);

        if ($this->pages == 0) {
            $this->pagination = 0;
            return self::FLAG_SUCCESS;
        }

        if ($this->pagination > $this->pages) {
            return self::FLAG_OVERFLOW;
        }

        # Getting details of all available items
        $pagination_start = ($pagination * $this->pagination) - $pagination;

        $query = "
            SELECT
                d.*,
                ud.username,
                it.name item_name,
                (
                    SELECT
                		im.image_url image
                    FROM
                    	image im
                    JOIN
                    	item_image ii
                    USING
                    	(id_image)
                    WHERE
                    	ii.id_item = it.id_item AND
                    	ii.is_main = True
                    LIMIT
                    	1
                ) item_image,
                uit.username item_creator_username,
                m.id_sender,
                m.content,
                m.created_at,
                m.is_read
            FROM
                discussion d
            JOIN
                item i
            USING
                (id_item)
            JOIN
                message m
            USING
                (id_discussion)
            JOIN
                item it
            USING
                (id_item)
            JOIN
                user uit
            ON
                it.id_creator = uit.id_user
            JOIN
                user ud
            ON
                d.id_user = ud.id_user
            WHERE
                (d.id_user = :id_user OR
                i.id_creator = :id_user) AND
                m.id_message = (
                    SELECT
                        id_message
                    FROM
                        message
                    WHERE
                        id_discussion = d.id_discussion
                    ORDER BY
                        created_at DESC
                    LIMIT
                        1   
                )
            ORDER BY
            m.created_at ASC
            LIMIT
                $pagination
            OFFSET
                $pagination_start;
        ";

        $stmt = $this->db_connection->prepare($query);

        if ($stmt->execute($data)) {
            $this->data = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $no_rows = count($this->data);
            for ($i = 0; $i < $no_rows; $i++) {
                $this->data[$i]["discussion_creator"] = array(
                    "id_user" => array_remove($this->data[$i], "id_user"),
                    "username" => array_remove($this->data[$i], "username")
                );

                $this->data[$i]["item"] = array(
                    "id_item" => array_remove($this->data[$i], "id_item"),
                    "name" => array_remove($this->data[$i], "item_name"),
                    "creator_username" => array_remove($this->data[$i], "item_creator_username"),
                    "image" => array_remove($this->data[$i], "item_image")
                );
            }

            return self::FLAG_SUCCESS;
        } else {
            return self::FLAG_FAILURE;
        }
    }
}
