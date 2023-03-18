<?php

namespace App\Controller;

require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/../getIpAddress.php');

use App\Controller\{Controller, AuthenticationController};
use App\Model\UserModel;
use App\Response;
use Firebase\JWT\{JWT, Key};
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMException;

class UserController extends Controller
{
    public function __construct($db_connection, $endpoint, $request_method)
    {
        parent::__construct($db_connection, $endpoint, $request_method, new UserModel($db_connection));
    }

    public function generateTokens()
    {
        $token = array(
            "iss" => CONFIG['BASE_BACKEND_URL'],
            "aud" => CONFIG['BASE_FRONTEND_URL'],
            "iat" => time(),
            "nbf" => time(),
            "exp" => time() + (60 * 30), // 30 min expiration time
            "data" => array(
                "id" => $this->model->id_user,
                "pswc" => $this->model->pswc,
                "ipAddress" => getIpAddress(),
                "token_flag" => AuthenticationController::TOKEN_LOGIN
            )
        );
        $this->model->token = JWT::encode($token, base64_decode(CONFIG['SECRET_KEY']), 'HS256');

        $refresh_token = array(
            "iss" => CONFIG['BASE_BACKEND_URL'],
            "aud" => CONFIG['BASE_FRONTEND_URL'],
            "iat" => time(),
            "nbf" => time(),
            "exp" => time() + (60 * 90), // 90 min expiration time
            "data" => array(
                "id" => $this->model->id_user,
                "pswc" => $this->model->pswc,
                "ipAddress" => getIpAddress(),
                "token_flag" => AuthenticationController::TOKEN_REFRESH
            )
        );
        $this->model->refresh_token = JWT::encode($refresh_token, base64_decode(CONFIG['SECRET_KEY']), 'HS256');
    }

    public function processRequest()
    {
        switch ($this->endpoint[0]) {
            case 'refreshtoken':
                header("Access-Control-Allow-Methods: POST, OPTIONS");
                switch ($this->request_method) {
                    case 'POST':
                        $data = json_decode(file_get_contents("php://input"));
                        if (
                            !is_null($data) &&
                            !empty($token = trim($data->token ?? null))
                        ) {
                            try {
                                $token_data = JWT::decode($token, new Key(base64_decode(CONFIG['SECRET_KEY']), 'HS256'));

                                if ($token_data->data->ipAddress != getIpAddress())
                                    throw new \Exception("IP address doesn't match");

                                if (
                                    !(($token_data->data->token_flag ?? 0) & AuthenticationController::TOKEN_REFRESH) ||
                                    $token_data->iss != CONFIG['BASE_BACKEND_URL'] ||
                                    strpos($_SERVER['HTTP_REFERER'], $token_data->aud) !== 0
                                )
                                    throw new \Exception("Invalid token");

                                $this->model->id_user = $token_data->data->id;
                                $this->model->pswc = $token_data->data->pswc;
                                $this->generateTokens();

                                return Response::ok(
                                    array(
                                        "token" => $this->model->token,
                                        "refresh_token" => $this->model->refresh_token
                                    )
                                );
                            } catch (\Exception $e) {
                                return Response::unprocessableEntity(
                                    array(
                                        "details" => $e->getMessage()
                                    )
                                );
                            }
                        } else {
                            return Response::badRequest();
                        }
                        break;
                    case 'OPTIONS':
                        return Response::noContent();
                    default:
                        return Response::methodNotAllowed("POST, OPTIONS");
                }
            case 'register':
                header("Access-Control-Allow-Methods: POST, OPTIONS");
                switch ($this->request_method) {
                    case 'POST':
                        $data = json_decode(file_get_contents("php://input"));
                        if (
                            !is_null($data)
                            &&
                            (!empty($this->model->username = trim($data->username ?? null)) &&
                                !empty($this->model->first_name = trim($data->first_name ?? null)) &&
                                !empty($this->model->last_name = trim($data->last_name ?? null)) &&
                                !empty($this->model->email = trim($data->email ?? null)) &&
                                !empty($this->model->birth_date = trim($data->birth_date ?? null)) &&
                                !empty($this->model->password = trim($data->password ?? null))
                            )
                            &&
                            strlen($this->model->username) <= 30 &&
                            strlen($this->model->first_name) <= 30 &&
                            strlen($this->model->last_name) <= 30 &&
                            strlen($this->model->email) <= 50 &&
                            filter_var($this->model->email, FILTER_VALIDATE_EMAIL) &&
                            preg_match('/^(?=\S*[A-Z]{1})(?=\S*[0-9]{1})(?=\S*[\W])\S{8,}$/', $this->model->password) &&
                            date('d.m.Y', strtotime($this->model->birth_date)) === $this->model->birth_date
                        ) {
                            $status = $this->model->register();
                            if ($status & $this->model::FLAG_SUCCESS) {
                                $token = array(
                                    "iss" => CONFIG['BASE_BACKEND_URL'],
                                    "aud" => CONFIG['BASE_FRONTEND_URL'],
                                    "iat" => time(),
                                    "nbf" => time(),
                                    "data" => array(
                                        "id" => $this->model->id_user,
                                        "token_flag" => AuthenticationController::TOKEN_ACTIVATION
                                    )
                                );
                                $this->model->token = JWT::encode($token, base64_decode(CONFIG['SECRET_KEY']), 'HS256');

                                $mail = new PHPMailer(true);
                                try {
                                    if (CONFIG['USE_SMTP']) {
                                        $mail->isSMTP();
                                        $mail->Host       = CONFIG['SMTP_HOST'];
                                        $mail->SMTPAuth   = true;
                                        $mail->Username   = CONFIG['SMTP_USERNAME'];
                                        $mail->Password   = CONFIG['SMTP_PASSWORD'];
                                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                                        $mail->Port       = 587;
                                    }

                                    $mail->setFrom(CONFIG['SMTP_USERNAME'], 'Auction Auction service');

                                    $mail->addAddress($this->model->email, $this->model->first_name . ' ' . $this->model->last_name);

                                    $mail->Subject = 'Auction Auction - Account activation';

                                    $mail->Body = 'Link for activating your profile: ' . CONFIG['BASE_FRONTEND_URL'] . '/activate/' . $this->model->token;

                                    $mail->send();
                                    return Response::created();
                                } catch (PHPMException $e) {
                                    error_log($mail->ErrorInfo);
                                    return Response::serviceUnavailable();
                                }
                            } elseif ($status & $this->model::FLAG_USERNAME_FOUND && $status & $this->model::FLAG_EMAIL_FOUND) {
                                return Response::unprocessableEntity(
                                    array(
                                        "details" => "Username and email are already used"
                                    )
                                );
                            } elseif ($status & $this->model::FLAG_USERNAME_FOUND) {
                                return Response::unprocessableEntity(
                                    array(
                                        "details" => "Username is already used"
                                    )
                                );
                            } elseif ($status & $this->model::FLAG_EMAIL_FOUND) {
                                return Response::unprocessableEntity(
                                    array(
                                        "details" => "Email is already used"
                                    )
                                );
                            } elseif ($status & $this->model::FLAG_FAILURE) {
                                return Response::serviceUnavailable();
                            }
                        } else {
                            return Response::badRequest();
                        }
                        break;
                    case 'OPTIONS':
                        return Response::noContent();
                    default:
                        return Response::methodNotAllowed("POST, OPTIONS");
                }
                break;
            case 'activate':
                header("Access-Control-Allow-Methods: POST, OPTIONS");
                switch ($this->request_method) {
                    case 'POST':
                        $data = json_decode(file_get_contents("php://input"));
                        if (
                            !is_null($data)
                            &&
                            !empty($token = trim($data->token ?? null))
                        ) {
                            try {
                                $token_data = JWT::decode($token, new Key(base64_decode(CONFIG['SECRET_KEY']), 'HS256'));

                                if (
                                    !(($token_data->data->token_flag ?? 0) & AuthenticationController::TOKEN_ACTIVATION) ||
                                    $token_data->iss != CONFIG['BASE_BACKEND_URL'] ||
                                    strpos($_SERVER['HTTP_REFERER'], $token_data->aud) !== 0
                                )
                                    throw new \Exception("Invalid token");

                                $this->model->id_user = $token_data->data->id;

                                $status = $this->model->activate();
                                if ($status & $this->model::FLAG_SUCCESS) {
                                    return Response::ok();
                                } elseif ($status & $this->model::FLAG_ALREADY_ACTIVATED) {
                                    return Response::unprocessableEntity(
                                        array(
                                            "details" => "User account already activated"
                                        )
                                    );
                                } elseif ($status & $this->model::FLAG_FAILURE) {
                                    return Response::serviceUnavailable();
                                }
                            } catch (\Exception $e) {
                                return Response::unprocessableEntity(
                                    array(
                                        "details" => $e->getMessage()
                                    )
                                );
                            }
                        } else {
                            return Response::badRequest();
                        }
                        break;
                    case 'OPTIONS':
                        return Response::noContent();
                    default:
                        return Response::methodNotAllowed("POST, OPTIONS");
                }
                break;
            case 'login':
                header("Access-Control-Allow-Methods: POST, OPTIONS");
                switch ($this->request_method) {
                    case 'POST':
                        $data = json_decode(file_get_contents("php://input"));
                        if (
                            !is_null($data)
                            &&
                            (
                                (
                                    (!empty($this->model->username = trim($data->username ?? null)) && strlen($this->model->username) <= 30) ||
                                    (!empty($this->model->email = trim($data->email ?? null)) && strlen($this->model->email) <= 50 && filter_var($this->model->email, FILTER_VALIDATE_EMAIL))
                                ) &&
                                !empty($this->model->password = trim($data->password ?? null))
                            )
                            &&
                            preg_match('/^(?=\S*[A-Z]{1})(?=\S*[0-9]{1})(?=\S*[\W])\S{8,}$/', $this->model->password)
                        ) {
                            $status = $this->model->login();
                            if ($status & $this->model::FLAG_SUCCESS) {
                                $this->generateTokens();

                                return Response::ok(
                                    array(
                                        "token" => $this->model->token,
                                        "refresh_token" => $this->model->refresh_token
                                    )
                                );
                            } elseif ($status & $this->model::FLAG_NOT_FOUND) {
                                return Response::unprocessableEntity(
                                    array(
                                        "details" => "User account not found"
                                    )
                                );
                            } elseif ($status & $this->model::FLAG_USER_NOT_ACTIVATED) {
                                return Response::unprocessableEntity(
                                    array(
                                        "details" => "User account is not active"
                                    )
                                );
                            } elseif ($status & $this->model::FLAG_INVALID_CREDENTIALS) {
                                return Response::unprocessableEntity(
                                    array(
                                        "details" => "Invalid authentication details were provided"
                                    )
                                );
                            } elseif ($status & $this->model::FLAG_FAILURE) {
                                return Response::serviceUnavailable();
                            }
                        } else {
                            return Response::badRequest();
                        }
                        break;
                    case 'OPTIONS':
                        return Response::noContent();
                    default:
                        return Response::methodNotAllowed("POST, OPTIONS");
                }
                break;
            case 'me':
                header('Access-Control-Allow-Credentials: true');
                switch ($this->endpoint[1]) {
                    case 'auctions_report':
                        header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
                        $token_data = AuthenticationController::authenticate($this->db_connection);
                        if ($token_data) {
                            $this->model->id_user = $token_data->data->id;
                            switch ($this->request_method) {
                                case 'GET':
                                    $status = $this->model->getAuctionsReport();

                                    if ($status & $this->model::FLAG_SUCCESS) {
                                        return Response::ok(
                                            array(
                                                'is_completed' => $this->model->data['is_completed'],
                                                'created_at' => $this->model->data['created_at']
                                            )
                                        );
                                    } elseif ($status & $this->model::FLAG_NOT_FOUND) {
                                        return Response::notFound();
                                    } elseif ($status & $this->model::FLAG_FAILURE) {
                                        return Response::serviceUnavailable();
                                    }
                                    break;
                                case 'POST':
                                    $status = $this->model->createAuctionsReport();

                                    if ($status & $this->model::FLAG_SUCCESS) {
                                        return Response::created();
                                    } elseif ($status & $this->model::FLAG_FAILURE) {
                                        return Response::serviceUnavailable();
                                    }
                                    break;
                                case 'OPTIONS':
                                    return Response::noContent();
                                default:
                                    return Response::methodNotAllowed("GET, POST, OPTIONS");
                            }
                        }
                        break;
                    case 'change_password':
                        header("Access-Control-Allow-Methods: POST, OPTIONS");
                        $token_data = AuthenticationController::authenticate($this->db_connection);
                        if ($token_data) {
                            $this->model->id_user = $token_data->data->id;
                            switch ($this->request_method) {
                                case 'POST':
                                    $data = json_decode(file_get_contents("php://input"));
                                    if (
                                        !is_null($data)
                                        &&
                                        (!empty($this->model->old_password = trim($data->old_password ?? null)) &&
                                            !empty($this->model->password = trim($data->new_password ?? null))
                                        )
                                        &&
                                        $this->model->old_password != $this->model->password &&
                                        preg_match('/^(?=\S*[A-Z]{1})(?=\S*[0-9]{1})(?=\S*[\W])\S{8,}$/', $this->model->old_password) &&
                                        preg_match('/^(?=\S*[A-Z]{1})(?=\S*[0-9]{1})(?=\S*[\W])\S{8,}$/', $this->model->password)
                                    ) {
                                        $status = $this->model->changePassword();
                                        if ($status & $this->model::FLAG_SUCCESS) {
                                            return Response::ok();
                                        } elseif ($status & $this->model::FLAG_INVALID_CREDENTIALS) {
                                            return Response::unprocessableEntity(
                                                array(
                                                    "details" => "Invalid authentication details were provided"
                                                )
                                            );
                                        } elseif ($status & $this->model::FLAG_FAILURE) {
                                            return Response::serviceUnavailable();
                                        }
                                    } else {
                                        return Response::badRequest();
                                    }
                                    break;
                                case 'OPTIONS':
                                    return Response::noContent();
                                default:
                                    return Response::methodNotAllowed("POST, OPTIONS");
                            }
                        }
                        break;
                    case 'login_attempts':
                        header("Access-Control-Allow-Methods: GET, OPTIONS");
                        $token_data = AuthenticationController::authenticate($this->db_connection);
                        if ($token_data) {
                            $this->model->id_user = $token_data->data->id;
                            switch ($this->request_method) {
                                case 'GET':
                                    $options = array(
                                        'options' => array(
                                            'min_range' => 1
                                        )
                                    );
                                    if (
                                        isset($_GET['page']) &&
                                        filter_var($_GET['page'], FILTER_VALIDATE_INT, $options)
                                    ) {
                                        $this->model->pagination = (int) $_GET['page'];
                                    }
                                    $this->model->order_by = $_GET['order_by'] ?? null;

                                    $status = $this->model->getLoginAttempts();
                                    if ($status & $this->model::FLAG_SUCCESS) {
                                        return Response::ok(
                                            array(
                                                'pages' => $this->model->pages,
                                                'current_page' => $this->model->pagination,
                                                'result' => $this->model->data
                                            )
                                        );
                                    } elseif ($status & $this->model::FLAG_OVERFLOW) {
                                        return Response::unprocessableEntity();
                                    } elseif ($status & $this->model::FLAG_FAILURE) {
                                        return Response::serviceUnavailable();
                                    }
                                    break;
                                case 'OPTIONS':
                                    return Response::noContent();
                                default:
                                    return Response::methodNotAllowed("GET, OPTIONS");
                            }
                        }
                        break;
                    case 'notifications':
                        header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
                        $token_data = AuthenticationController::authenticate($this->db_connection);
                        if ($token_data) {
                            $this->model->id_user = $token_data->data->id;
                            switch ($this->request_method) {
                                case 'GET':
                                    $status = $this->model->getNotifications();
                                    if ($status & $this->model::FLAG_SUCCESS) {
                                        return Response::ok($this->model->data);
                                    } elseif ($status & $this->model::FLAG_FAILURE) {
                                        return Response::serviceUnavailable();
                                    }
                                    break;
                                case 'POST':
                                    $data = json_decode(file_get_contents("php://input"));

                                    $options = array(
                                        'options' => array(
                                            'min_range' => 1
                                        )
                                    );
                                    if (
                                        !is_null($data)
                                        &&
                                        !empty($this->model->id_notification = trim($data->id_notification ?? null))
                                        &&
                                        filter_var($this->model->id_notification, FILTER_VALIDATE_INT, $options)

                                    ) {
                                        $status = $this->model->changeNotification();
                                        if ($status & $this->model::FLAG_SUCCESS) {
                                            return Response::ok();
                                        } elseif ($status & $this->model::FLAG_ERROR) {
                                            return Response::unprocessableEntity();
                                        } elseif ($status & $this->model::FLAG_FAILURE) {
                                            return Response::serviceUnavailable();
                                        }
                                    } else {
                                        return Response::badRequest();
                                    }
                                    break;
                                case 'OPTIONS':
                                    return Response::noContent();
                                default:
                                    return Response::methodNotAllowed("GET, POST, OPTIONS");
                            }
                        }
                        break;
                    case 'favourites':
                        header("Access-Control-Allow-Methods: GET, OPTIONS");
                        $token_data = AuthenticationController::authenticate($this->db_connection);
                        if ($token_data) {
                            $this->model->id_user = $token_data->data->id;
                            switch ($this->request_method) {
                                case 'GET':
                                    $status = $this->model->getFavourites();
                                    if ($status & $this->model::FLAG_SUCCESS) {
                                        return Response::ok($this->model->data);
                                    } elseif ($status & $this->model::FLAG_FAILURE) {
                                        return Response::serviceUnavailable();
                                    }
                                    break;
                                case 'OPTIONS':
                                    return Response::noContent();
                                default:
                                    return Response::methodNotAllowed("GET, OPTIONS");
                            }
                        }
                        break;
                    case 'items':
                        header("Access-Control-Allow-Methods: GET, OPTIONS");
                        $token_data = AuthenticationController::authenticate($this->db_connection);
                        if ($token_data) {
                            $this->model->id_user = $token_data->data->id;
                            switch ($this->request_method) {
                                case 'GET':
                                    $options = array(
                                        'options' => array(
                                            'min_range' => 1
                                        )
                                    );
                                    if (
                                        isset($_GET['page']) &&
                                        filter_var($_GET['page'], FILTER_VALIDATE_INT, $options)
                                    ) {
                                        $this->model->pagination = (int) $_GET['page'];
                                    }
                                    $this->model->order_by = $_GET['order_by'] ?? null;
                                    $this->model->category = !empty($e = $_GET['category'] ?? '%') ? $e : '%';
                                    $this->model->search = $_GET['search'] ?? '';
                                    $this->model->type = $_GET['type'] ?? null;

                                    $status = $this->model->getItems();
                                    if ($status & $this->model::FLAG_SUCCESS) {
                                        return Response::ok(
                                            array(
                                                'pages' => $this->model->pages,
                                                'current_page' => $this->model->pagination,
                                                'result' => $this->model->data
                                            )
                                        );
                                    } elseif ($status & $this->model::FLAG_OVERFLOW) {
                                        return Response::unprocessableEntity();
                                    } elseif ($status & $this->model::FLAG_FAILURE) {
                                        return Response::serviceUnavailable();
                                    }
                                    break;
                                case 'OPTIONS':
                                    return Response::noContent();
                                default:
                                    return Response::methodNotAllowed("GET, OPTIONS");
                            }
                        }
                        break;
                    case 'discussions':
                        header("Access-Control-Allow-Methods: GET, OPTIONS");
                        $token_data = AuthenticationController::authenticate($this->db_connection);
                        if ($token_data) {
                            $this->model->id_user = $token_data->data->id;
                            switch ($this->request_method) {
                                case 'GET':
                                    $options = array(
                                        'options' => array(
                                            'min_range' => 1
                                        )
                                    );
                                    if (
                                        isset($_GET['page']) &&
                                        filter_var($_GET['page'], FILTER_VALIDATE_INT, $options)
                                    ) {
                                        $this->model->pagination = (int) $_GET['page'];
                                    }

                                    $status = $this->model->getDiscussions();
                                    if ($status & $this->model::FLAG_SUCCESS) {
                                        return Response::ok(
                                            array(
                                                'pages' => $this->model->pages,
                                                'current_page' => $this->model->pagination,
                                                'result' => $this->model->data
                                            )
                                        );
                                    } elseif ($status & $this->model::FLAG_OVERFLOW) {
                                        return Response::unprocessableEntity();
                                    } elseif ($status & $this->model::FLAG_FAILURE) {
                                        return Response::serviceUnavailable();
                                    }
                                    break;
                                case 'OPTIONS':
                                    return Response::noContent();
                                default:
                                    return Response::methodNotAllowed("GET, OPTIONS");
                            }
                        }
                        break;
                    case '':
                        header("Access-Control-Allow-Methods: GET, PATCH, OPTIONS");

                        $token_data = AuthenticationController::authenticate($this->db_connection);
                        if ($token_data) {
                            $this->model->id_user = $token_data->data->id;
                            switch ($this->request_method) {
                                case 'GET':
                                    $status = $this->model->getMe();
                                    if ($status & $this->model::FLAG_SUCCESS) {
                                        return Response::ok($this->model->data);
                                    } elseif ($status & $this->model::FLAG_FAILURE) {
                                        return Response::serviceUnavailable();
                                    }
                                    break;
                                case 'PATCH':
                                    $data = json_decode(file_get_contents("php://input"));

                                    $options = array(
                                        'options' => array(
                                            'min_range' => 1
                                        )
                                    );
                                    if (
                                        !is_null($data)
                                        &&
                                        (!empty(trim($data->phone ?? null)) ||
                                            !empty(trim($data->id_country ?? null)) ||
                                            !empty(trim($data->id_province ?? null)) ||
                                            !empty(trim($data->postcode ?? null)) ||
                                            !empty(trim($data->city ?? null)) ||
                                            !empty(trim($data->street ?? null))
                                        )
                                        &&
                                        (empty(trim($data->phone ?? null)) || (strlen($data->phone) <=  12 && preg_match('/^\d{9,}$/', $data->phone))) &&
                                        (empty(trim($data->postcode ?? null)) || strlen($data->postcode) <= 6) &&
                                        (empty(trim($data->city ?? null)) || strlen($data->city) <= 50) &&
                                        (empty(trim($data->street ?? null)) || strlen($data->street) <= 50) &&
                                        (empty(trim($data->id_country ?? null)) || filter_var($data->id_country, FILTER_VALIDATE_INT, $options)) &&
                                        (empty(trim($data->id_province ?? null)) || filter_var($data->id_province, FILTER_VALIDATE_INT, $options))
                                    ) {
                                        $this->model->phone = trim($data->phone ?? null);
                                        $this->model->id_country = trim($data->id_country ?? null);
                                        $this->model->id_province = trim($data->id_province ?? null);
                                        $this->model->postcode = trim($data->postcode ?? null);
                                        $this->model->city = trim($data->city ?? null);
                                        $this->model->street = trim($data->street ?? null);

                                        $status = $this->model->changeMe();
                                        if ($status & $this->model::FLAG_SUCCESS) {
                                            return Response::ok();
                                        } elseif ($status & $this->model::FLAG_NOT_FOUND) {
                                            return Response::unprocessableEntity();
                                        } elseif ($status & $this->model::FLAG_FAILURE) {
                                            return Response::serviceUnavailable();
                                        }
                                    } else {
                                        return Response::badRequest();
                                    }
                                    break;
                                case 'OPTIONS':
                                    return Response::noContent();
                                default:
                                    return Response::methodNotAllowed("GET, PATCH, OPTIONS");
                            }
                        }
                        break;
                    default:
                        return Response::notFound();
                }
                break;
            case 'reset_password':
                header("Access-Control-Allow-Methods: POST, OPTIONS");
                switch ($this->endpoint[1]) {
                    case '':
                        switch ($this->request_method) {
                            case 'POST':
                                $data = json_decode(file_get_contents("php://input"));
                                if (
                                    !is_null($data)
                                    &&
                                    (!empty($this->model->email = trim($data->email ?? null)) && strlen($this->model->email) <= 50)
                                    &&
                                    filter_var($this->model->email, FILTER_VALIDATE_EMAIL)
                                ) {
                                    $status = $this->model->checkResetPassword();
                                    if ($status & $this->model::FLAG_IGNORE)
                                        return Response::ok();

                                    if ($status & $this->model::FLAG_SUCCESS) {
                                        $token = array(
                                            "iss" => CONFIG['BASE_BACKEND_URL'],
                                            "aud" => CONFIG['BASE_FRONTEND_URL'],
                                            "iat" => time(),
                                            "nbf" => time(),
                                            "exp" => time() + (60 * 15),
                                            "data" => array(
                                                "id" => $this->model->id_user,
                                                "token_flag" => AuthenticationController::TOKEN_RESET_PASSWORD
                                            )
                                        );
                                        $this->model->token = JWT::encode($token, base64_decode(CONFIG['SECRET_KEY']), 'HS256');

                                        $mail = new PHPMailer(true);
                                        try {
                                            if (CONFIG['USE_SMTP']) {
                                                $mail->isSMTP();
                                                $mail->Host       = CONFIG['SMTP_HOST'];
                                                $mail->SMTPAuth   = true;
                                                $mail->Username   = CONFIG['SMTP_USERNAME'];
                                                $mail->Password   = CONFIG['SMTP_PASSWORD'];
                                                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                                                $mail->Port       = 587;
                                            }

                                            $mail->setFrom(CONFIG['SMTP_USERNAME'], 'Auction Auction service');

                                            $mail->addAddress($this->model->email, $this->model->first_name . ' ' . $this->model->last_name);

                                            $mail->Subject = 'Auction Auction - Reset password';

                                            $mail->Body = 'Link for resetting your password: ' . CONFIG['BASE_FRONTEND_URL'] . '/reset-password-confirm/' . $this->model->token;

                                            $mail->send();
                                            return Response::ok();
                                        } catch (PHPMException $e) {
                                            error_log($mail->ErrorInfo);
                                            return Response::serviceUnavailable();
                                        }
                                    } elseif ($status & $this->model::FLAG_USER_NOT_ACTIVATED) {
                                        return Response::unprocessableEntity(
                                            array(
                                                "details" => "User account is not active"
                                            )
                                        );
                                    } elseif ($status & $this->model::FLAG_NOT_FOUND) {
                                        return Response::unprocessableEntity(
                                            array(
                                                "details" => "User account not found"
                                            )
                                        );
                                    } elseif ($status & $this->model::FLAG_FAILURE) {
                                        return Response::serviceUnavailable();
                                    }
                                } else {
                                    return Response::badRequest();
                                }
                                break;
                            case 'OPTIONS':
                                return Response::noContent();
                            default:
                                return Response::methodNotAllowed("POST, OPTIONS");
                        }
                        break;
                    case 'confirm':
                        switch ($this->request_method) {
                            case 'POST':
                                $data = json_decode(file_get_contents("php://input"));
                                if (
                                    !is_null($data)
                                    &&
                                    (!empty($token = trim($data->token ?? null)) &&
                                        !empty($this->model->password = trim($data->password ?? null))
                                    )
                                    &&
                                    preg_match('/^(?=\S*[A-Z]{1})(?=\S*[0-9]{1})(?=\S*[\W])\S{8,}$/', $this->model->password)
                                ) {
                                    try {
                                        $token_data = JWT::decode($token, new Key(base64_decode(CONFIG['SECRET_KEY']), 'HS256'));

                                        if (
                                            !(($token_data->data->token_flag ?? 0) & AuthenticationController::TOKEN_RESET_PASSWORD) ||
                                            $token_data->iss != CONFIG['BASE_BACKEND_URL'] ||
                                            strpos($_SERVER['HTTP_REFERER'], $token_data->aud) !== 0
                                        )
                                            throw new \Exception("Invalid token");

                                        $this->model->id_user = $token_data->data->id;

                                        $status = $this->model->resetPassword();
                                        if ($status & $this->model::FLAG_SUCCESS) {
                                            return Response::ok();
                                        } elseif ($status & $this->model::FLAG_FAILURE) {
                                            return Response::serviceUnavailable();
                                        }
                                    } catch (\Exception $e) {
                                        return Response::unprocessableEntity(
                                            array(
                                                "details" => $e->getMessage()
                                            )
                                        );
                                    }
                                } else {
                                    return Response::badRequest();
                                }
                                break;
                            case 'OPTIONS':
                                return Response::noContent();
                            default:
                                return Response::methodNotAllowed("POST, OPTIONS");
                        }
                        break;
                    default:
                        return Response::notFound();
                }
                break;
            default:
                header("Access-Control-Allow-Methods: GET, OPTIONS");
                $token_data = AuthenticationController::authenticate($this->db_connection);
                if ($token_data) {
                    $this->model->id_logged_user = $token_data->data->id;
                    $options = array(
                        'options' => array(
                            'min_range' => 1
                        )
                    );
                    if (filter_var($this->model->id_user = $this->endpoint[0], FILTER_VALIDATE_INT, $options)) {
                        switch ($this->request_method) {
                            case 'GET':
                                $status = $this->model->getUser();
                                if ($status & $this->model::FLAG_SUCCESS) {
                                    return Response::ok($this->model->data);
                                } elseif ($status & $this->model::FLAG_NOT_FOUND) {
                                    return Response::notFound();
                                } elseif ($status & $this->model::FLAG_FAILURE) {
                                    return Response::serviceUnavailable();
                                }
                                break;
                            case 'OPTIONS':
                                return Response::noContent();
                            default:
                                return Response::methodNotAllowed("GET, OPTIONS");
                        }
                    } else {
                        return Response::notFound();
                    }
                }
        }
    }
}
