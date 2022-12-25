<?php

namespace App\Controller;

use App\Controller\{Controller, AuthenticationController};
use App\Model\ItemModel;
use App\Response;

class ItemController extends Controller
{
    public function __construct($db_connection, $endpoint, $request_method)
    {
        parent::__construct($db_connection, $endpoint, $request_method, new ItemModel($db_connection));
    }

    public function processRequest()
    {
        switch ($this->endpoint[0]) {
            case '';
                header("Access-Control-Allow-Methods: GET, POST, OPTIONS");

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
                    case 'POST':
                        $token_data = AuthenticationController::authenticate($this->db_connection);

                        if ($token_data) {
                            $this->model->id_creator = $token_data->data->id;
                            $data = json_decode(file_get_contents("php://input"));
                            $options1 = array(
                                'options' => array(
                                    'min_range' => 1
                                )
                            );
                            $options2 = array(
                                'options' => array(
                                    "decimal" => ".",
                                    "min_range" => 0.01
                                )
                            );
                            if (
                                !is_null($data)
                                &&
                                (!empty($this->model->name = trim($data->name ?? null)) &&
                                    !empty($this->model->description = trim($data->description ?? null)) &&
                                    !empty($this->model->id_category = trim($data->id_category ?? null)) &&
                                    !empty($this->model->starting_price = trim($data->starting_price ?? null)) &&
                                    !empty($this->model->ending_time = trim($data->ending_time ?? null))
                                )
                                &&
                                strlen($this->model->name) <= 100 &&
                                strlen($this->model->description) <= 500 &&
                                filter_var($this->model->id_category, FILTER_VALIDATE_INT, $options1) &&
                                filter_var($this->model->starting_price, FILTER_VALIDATE_FLOAT, $options2) &&
                                date('d.m.Y H:i:s', strtotime($this->model->ending_time)) === $this->model->ending_time
                            ) {
                                $status = $this->model->createItem();
                                if ($status & $this->model::FLAG_SUCCESS) {
                                    return Response::created(
                                        array(
                                            "id_item" => $this->model->id_item
                                        )
                                    );
                                } elseif ($status & $this->model::FLAG_ERROR) {
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
                        return Response::methodNotAllowed("GET, POST, OPTIONS");
                }
                break;
            default:
                header("Access-Control-Allow-Methods: GET, OPTIONS");
                $options = array(
                    'options' => array(
                        'min_range' => 1
                    )
                );
                if (filter_var($this->model->id_item = $this->endpoint[0], FILTER_VALIDATE_INT, $options)) {
                    switch ($this->request_method) {
                        case 'GET':
                            $status = $this->model->getItem();
                            if ($status & $this->model::FLAG_SUCCESS) {
                                return Response::ok($this->model->data);
                            } elseif ($status & $this->model::FLAG_ITEM_NOT_FOUND) {
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
