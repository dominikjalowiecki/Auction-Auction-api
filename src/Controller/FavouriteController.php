<?php

namespace App\Controller;

use App\Controller\{Controller, AuthenticationController};
use App\Model\FavouriteModel;
use App\Response;

class FavouriteController extends Controller
{
    public function __construct($db_connection, $endpoint, $request_method)
    {
        parent::__construct($db_connection, $endpoint, $request_method, new FavouriteModel($db_connection));
    }

    public function processRequest()
    {
        switch ($this->endpoint[0]) {
            case '':
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
                                !empty($this->model->id_item = trim($data->id_item ?? null))
                                &&
                                filter_var($this->model->id_item, FILTER_VALIDATE_INT, $options)
                            ) {
                                $status = $this->model->addFavourite();
                                if ($status & $this->model::FLAG_SUCCESS) {
                                    return Response::created(
                                        array(
                                            "id_favourite" => $this->model->id_favourite
                                        )
                                    );
                                } elseif ($status & $this->model::FLAG_ITEM_DOESNT_EXIST) {
                                    return Response::unprocessableEntity(
                                        array(
                                            "details" => "Can't add favourite"
                                        )
                                    );
                                } elseif ($status & $this->model::FLAG_ALREADY_FAVOURITE) {
                                    return Response::unprocessableEntity(
                                        array(
                                            "details" => "Item is already favourite"
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
                header("Access-Control-Allow-Methods: DELETE, OPTIONS");
                $options = array(
                    'options' => array(
                        'min_range' => 1
                    )
                );
                if (filter_var($this->model->id_favourite = $this->endpoint[0], FILTER_VALIDATE_INT, $options)) {
                    switch ($this->request_method) {
                        case 'DELETE':
                            $token_data = AuthenticationController::authenticate($this->db_connection);

                            if ($token_data) {
                                $this->model->id_user = $token_data->data->id;

                                $status = $this->model->deleteFavourite();
                                if ($status & $this->model::FLAG_SUCCESS) {
                                    return Response::ok();
                                } elseif ($status & $this->model::FLAG_ITEM_NOT_FOUND) {
                                    return Response::notFound();
                                } elseif ($status & $this->model::FLAG_USER_IS_NOT_OWNER) {
                                    return Response::unprocessableEntity();
                                } elseif ($status & $this->model::FLAG_FAILURE) {
                                    return Response::serviceUnavailable();
                                }
                            }
                            break;
                        case 'OPTIONS':
                            return Response::noContent();
                        default:
                            return Response::methodNotAllowed("DELETE, OPTIONS");
                    }
                } else {
                    return Response::notFound();
                }
        }
    }
}
