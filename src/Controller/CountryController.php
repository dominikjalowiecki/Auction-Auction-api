<?php

namespace App\Controller;

use App\Controller\Controller;
use App\Model\CountryModel;
use App\Response;

class CountryController extends Controller
{
    public function __construct($db_connection, $endpoint, $request_method)
    {
        parent::__construct($db_connection, $endpoint, $request_method, new CountryModel($db_connection));
    }

    public function processRequest()
    {
        switch ($this->endpoint[0]) {
            case '':
                header("Access-Control-Allow-Methods: GET, OPTIONS");
                switch ($this->request_method) {
                    case 'GET':
                        $status = $this->model->getCountries();
                        if ($status & $this->model::FLAG_SUCCESS) {
                            Response::ok($this->model->data);
                        } elseif ($status & $this->model::FLAG_FAILURE) {
                            Response::serviceUnavailable();
                        }
                        break;
                    case 'OPTIONS':
                        return Response::noContent();
                    default:
                        return Response::methodNotAllowed("GET, OPTIONS");
                }
                break;
            default:
                return Response::notFound();
        }
    }
}
