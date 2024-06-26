<?php

require_once(__DIR__ . '/src/config.php');
require_once(__DIR__ . '/vendor/autoload.php');

use App\DatabaseConnector;
use App\Controller\{
    Controller,
    UserController,
    CountryController,
    ProvinceController,
    ItemController,
    CategoryController,
    ItemImageController,
    MessageImageController,
    FavouriteController,
    BidController,
    DiscussionController,
    MessageController
};
use App\Response;

function controllerFactory($path, $db_connection, $endpoint, $request_method): ?Controller
{
    switch ($path) {
        case 'users':
            return new UserController($db_connection, $endpoint, $request_method);
        case 'countries':
            return new CountryController($db_connection, $endpoint, $request_method);
        case 'provinces':
            return new ProvinceController($db_connection, $endpoint, $request_method);
        case 'items':
            return new ItemController($db_connection, $endpoint, $request_method);
        case 'categories':
            return new CategoryController($db_connection, $endpoint, $request_method);
        case 'itemsImages':
            return new ItemImageController($db_connection, $endpoint, $request_method);
        case 'messagesImages':
            return new MessageImageController($db_connection, $endpoint, $request_method);
        case 'favourites':
            return new FavouriteController($db_connection, $endpoint, $request_method);
        case 'bids':
            return new BidController($db_connection, $endpoint, $request_method);
        case 'discussions':
            return new DiscussionController($db_connection, $endpoint, $request_method);
        case 'messages':
            return new MessageController($db_connection, $endpoint, $request_method);
        default:
            Response::notFound();
            return null;
    }
}

function apiController($uri, $db_connection, $endpoint, $request_method)
{
    $current_controller = controllerFactory($uri[2], $db_connection, $endpoint, $request_method);
    if ($current_controller === null) return;

    $current_controller->processRequest();
}

$cors = explode("/", CONFIG['BASE_FRONTEND_URL']);
$cors = $cors[0] . "//" . $cors[2];

header("Access-Control-Allow-Origin: " . $cors);
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = explode('/', $uri);

apiController(
    $uri,
    DatabaseConnector::getConnection(),
    [$uri[3] ?? null, $uri[4] ?? null],
    $_SERVER['REQUEST_METHOD']
);
