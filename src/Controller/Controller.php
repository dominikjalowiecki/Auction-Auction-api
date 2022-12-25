<?php

namespace App\Controller;

abstract class Controller
{
    protected $db_connection;
    protected $endpoint;
    protected $request_method;
    protected $model;

    public function __construct($db_connection, $endpoint, $request_method, $model)
    {

        $this->db_connection = $db_connection;
        $this->endpoint = $endpoint;
        $this->request_method = $request_method;
        $this->model = $model;
    }

    abstract public function processRequest();
}
