<?php

namespace App\Controller;

use App\Controller\{Controller, AuthenticationController};
use App\Model\MessageModel;
use App\Response;

class MessageController extends Controller
{
    public function __construct($db_connection, $endpoint, $request_method)
    {
        parent::__construct($db_connection, $endpoint, $request_method, new MessageModel($db_connection));
    }

    public function processRequest()
    {
        switch ($this->endpoint[0]) {
            case '';
                header("Access-Control-Allow-Methods: POST, OPTIONS");

                switch ($this->request_method) {
                    case 'POST':
                        $token_data = AuthenticationController::authenticate($this->db_connection);

                        if ($token_data) {
                            $this->model->id_user = $token_data->data->id;
                            $data = json_decode(file_get_contents("php://input"));
                            $options = array(
                                'options' => array(
                                    'min_range' => 1
                                )
                            );
                            if (
                                !is_null($data)
                                &&
                                (!empty($this->model->id_discussion = trim($data->id_discussion ?? null)) &&
                                    !empty($this->model->content = trim($data->content ?? null))
                                )
                                &&
                                strlen($this->model->content) <= 300 &&
                                filter_var($this->model->id_discussion, FILTER_VALIDATE_INT, $options)
                            ) {
                                $status = $this->model->createMessage();
                                if ($status & $this->model::FLAG_SUCCESS) {
                                    return Response::created(
                                        array(
                                            "id_message" => $this->model->id_message
                                        )
                                    );
                                } elseif ($status & $this->model::FLAG_DISCUSSION_DOESNT_EXIST) {
                                    return Response::unprocessableEntity(
                                        array(
                                            "details" => "Discussion doesn't exist"
                                        )
                                    );
                                } elseif ($status & $this->model::FLAG_USER_NOT_ALLOWED) {
                                    return Response::unprocessableEntity();
                                } elseif ($status & $this->model::FLAG_FAILURE) {
                                    return Response::serviceUnavailable();
                                }
                            } else {
                                return Response::badRequest();
                            }
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
    }
}
