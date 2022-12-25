<?php

namespace App\Controller;

use App\Controller\Controller;
use App\Model\CategoryModel;
use App\Response;

class CategoryController extends Controller
{
    public function __construct($db_connection, $endpoint, $request_method)
    {
        parent::__construct($db_connection, $endpoint, $request_method, new CategoryModel($db_connection));
    }

    public function processRequest()
    {
        switch ($this->endpoint[0]) {
            case '':
                header("Access-Control-Allow-Methods: GET, OPTIONS");
                switch ($this->request_method) {
                    case 'GET':
                        $status = $this->model->getCategories();
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
                break;
            default:
                return Response::notFound();
        }
    }
}
