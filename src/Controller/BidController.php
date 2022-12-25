<?php

namespace App\Controller;

use App\Controller\{Controller, AuthenticationController};
use App\Model\BidModel;
use App\Response;

class BidController extends Controller
{
    public function __construct($db_connection, $endpoint, $request_method)
    {
        parent::__construct($db_connection, $endpoint, $request_method, new BidModel($db_connection));
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
                            $this->model->id_bidder = $token_data->data->id;
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
                                (!empty($this->model->id_item = trim($data->id_item ?? null)) &&
                                    !empty($this->model->bid_price = trim($data->bid_price ?? null))
                                )
                                &&
                                filter_var($this->model->id_item, FILTER_VALIDATE_INT, $options1) &&
                                filter_var($this->model->bid_price, FILTER_VALIDATE_FLOAT, $options2)
                            ) {
                                $status = $this->model->createBid();
                                if ($status & $this->model::FLAG_SUCCESS) {
                                    return Response::created();
                                } elseif ($status & $this->model::FLAG_BID_PRICE_ERROR) {
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
