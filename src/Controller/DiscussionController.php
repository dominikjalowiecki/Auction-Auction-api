<?php

namespace App\Controller;

use App\Controller\{Controller, AuthenticationController};
use App\Model\DiscussionModel;
use App\Response;

class DiscussionController extends Controller
{
    public function __construct($db_connection, $endpoint, $request_method)
    {
        parent::__construct($db_connection, $endpoint, $request_method, new DiscussionModel($db_connection));
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
                                (!empty($this->model->id_item = trim($data->id_item ?? null)) &&
                                    !empty($this->model->content = trim($data->content ?? null))
                                )
                                &&
                                strlen($this->model->content) <= 300 &&
                                filter_var($this->model->id_item, FILTER_VALIDATE_INT, $options)
                            ) {
                                $status = $this->model->createDiscussion();
                                if ($status & $this->model::FLAG_SUCCESS) {
                                    return Response::created(
                                        array(
                                            "id_message" => $this->model->id_message
                                        )
                                    );
                                } elseif ($status & $this->model::FLAG_ITEM_DOESNT_EXIST) {
                                    return Response::unprocessableEntity(
                                        array(
                                            "details" => "Item doesn't exist"
                                        )
                                    );
                                } elseif ($status & $this->model::FLAG_USER_IS_CREATOR) {
                                    return Response::unprocessableEntity(
                                        array(
                                            "details" => "User is product creator"
                                        )
                                    );
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
                header("Access-Control-Allow-Methods: GET, OPTIONS");
                $options = array(
                    'options' => array(
                        'min_range' => 1
                    )
                );
                if (filter_var($this->model->id_discussion = $this->endpoint[0], FILTER_VALIDATE_INT, $options)) {
                    switch ($this->request_method) {
                        case 'GET':
                            $token_data = AuthenticationController::authenticate($this->db_connection);

                            if ($token_data) {
                                $this->model->id_user = $token_data->data->id;
                                if (
                                    isset($_GET['page']) &&
                                    filter_var($_GET['page'], FILTER_VALIDATE_INT, $options)
                                ) {
                                    $this->model->pagination = (int) $_GET['page'];
                                }
                                $status = $this->model->getDiscussion();
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
                                } elseif ($status & $this->model::FLAG_USER_NOT_ALLOWED) {
                                    return Response::unprocessableEntity();
                                } elseif ($status & $this->model::FLAG_DISCUSSION_DOESNT_EXIST) {
                                    return Response::notFound();
                                } elseif ($status & $this->model::FLAG_FAILURE) {
                                    return Response::serviceUnavailable();
                                }
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
