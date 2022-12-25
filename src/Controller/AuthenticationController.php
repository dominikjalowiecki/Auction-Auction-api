<?php

namespace App\Controller;

require_once(__DIR__ . '/../getIpAddress.php');

use Firebase\JWT\{JWT, Key};
use App\Model\AuthenticationModel;
use App\Response;

class AuthenticationController
{
    private static $model;

    public const TOKEN_ACTIVATION = 0x1;
    public const TOKEN_LOGIN = 0x2;
    public const TOKEN_RESET_PASSWORD = 0x4;
    public const TOKEN_REFRESH = 0x8;

    private function __construct()
    {
    }

    public static function authenticate($db_connection)
    {
        $headers = apache_request_headers();
        $token = $headers['Authorization'] ?? null;

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS')
            return Response::noContent();

        if (!$token) {
            Response::unauthorized(
                array(
                    "details" => "Token not found"
                )
            );
            return False;
        }

        list($jwt) = sscanf($token, 'JWT %s');

        if (!$jwt) {
            Response::unauthorized(
                array(
                    "details" => "Invalid token"
                )
            );
            return False;
        }

        try {
            $token_data = JWT::decode($jwt, new Key(base64_decode(CONFIG['SECRET_KEY']), 'HS256'));

            if (
                !(($token_data->data->token_flag ?? 0) & self::TOKEN_LOGIN) ||
                $token_data->iss != CONFIG['BASE_BACKEND_URL'] ||
                $_SERVER['HTTP_REFERER'] != $token_data->aud
            )
                throw new \Exception("Invalid token");

            if ($token_data->data->ipAddress != getIpAddress()) {
                Response::unauthorized(
                    array(
                        "details" => "IP address doesn't match"
                    )
                );
                return False;
            }

            self::$model = new AuthenticationModel($db_connection);
            self::$model->id_user = $token_data->data->id;

            $status = self::$model->getUserDetails();
            if ($status & self::$model::FLAG_SUCCESS) {
                if ($token_data->data->pswc == self::$model->pswc) {
                    self::$model->updateLastOnline();
                    return $token_data;
                } else {
                    Response::unauthorized(
                        array(
                            "details" => "Invalid authentication details"
                        )
                    );
                }
            } elseif ($status & AuthenticationModel::FLAG_FAILURE) {
                Response::serviceUnavailable();
            }

            return False;
        } catch (\Exception $e) {
            Response::unauthorized(
                array(
                    "details" => $e->getMessage()
                )
            );
            return False;
        }
    }
}
