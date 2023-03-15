<?php

namespace App;

final class Response
{

    private function __construct()
    {
    }

    public static function ok(array $data = null)
    {
        http_response_code(200);

        if (!empty($data)) {
            $res = $data;
            echo json_encode($res, JSON_NUMERIC_CHECK);
        }
    }

    public static function created(array $data = null)
    {
        http_response_code(201);

        $res = array(
            "message" => "Object was created"
        );

        if (!empty($data)) {
            $res = array_merge($res, $data);
        }

        echo json_encode($res, JSON_NUMERIC_CHECK);
    }

    public static function noContent()
    {
        http_response_code(204);
    }

    public static function badRequest(array $data = null)
    {
        http_response_code(400);

        $res = array(
            "message" => "Invalid content syntax"
        );

        if (!empty($data)) {
            $res = array_merge($res, $data);
        }

        echo json_encode($res);
    }

    public static function unauthorized(array $data = null)
    {
        http_response_code(401);
        header('WWW-Authenticate: JWT realm="Access to the staging site"');

        $res = array(
            "message" => "Access denied!"
        );

        if (!empty($data)) {
            $res = array_merge($res, $data);
        }

        echo json_encode($res);
    }

    public static function notFound()
    {
        http_response_code(404);

        $res = array(
            "message" => "Resource not found"
        );

        echo json_encode($res);
    }

    public static function methodNotAllowed(string $data = null)
    {
        http_response_code(405);

        $res = array(
            "message" => "Request method not allowed"
        );

        if (!empty($data)) {
            header("Allow: " . $data);
        }

        echo json_encode($res);
    }

    public static function unprocessableEntity(array $data = null)
    {
        http_response_code(422);

        $res = array(
            "message" => "Content semantic errors"
        );

        if (!empty($data)) {
            $res = array_merge($res, $data);
        }

        echo json_encode($res);
    }

    public static function serviceUnavailable()
    {
        http_response_code(503);

        $res = array(
            "message" => "Unable to perform action"
        );

        echo json_encode($res);
    }
}
