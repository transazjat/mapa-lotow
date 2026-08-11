<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Transazja\MapaLotowApi\Controller\FlightController;
use Transazja\MapaLotowApi\Controller\UserOverviewController;
use Transazja\MapaLotowApi\Controller\UserSummaryController;
use Transazja\MapaLotowApi\Database\Database;

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$config = require __DIR__ . '/../config/database.php';

$database = new Database($config);
$pdo = $database->getConnection();

$app = AppFactory::create();

$app->addRoutingMiddleware();

$app->addErrorMiddleware(
    true,
    true,
    true
);

$app->get('/api/health', function (
    Request $request,
    Response $response
): Response {

    $response->getBody()->write(
        json_encode(
            [
                'status' => 'ok',
                'application' => 'Mapa Lotow API',
            ],
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
        )
    );

    return $response
        ->withHeader('Content-Type', 'application/json');
});

$app->get('/api/db-test', function (
    Request $request,
    Response $response
) use ($pdo): Response {

    $result = $pdo
        ->query('SELECT DATABASE() AS database_name')
        ->fetch();

    $response->getBody()->write(
        json_encode(
            [
                'status' => 'ok',
                'database' => $result['database_name'],
            ],
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
        )
    );

    return $response
        ->withHeader('Content-Type', 'application/json');
});

$flightController = new FlightController($pdo);
$UserOverviewController = new UserOverviewController($pdo);
$userSummaryController = new UserSummaryController($pdo);

$app->get(
    '/api/flights',
    [$flightController, 'index']
);

$app->get(
    '/api/flights/{id:[0-9]+}',
    [$flightController, 'show']
);

$app->get(
    '/api/users/{id:[0-9]+}/overview',
    [$UserOverviewController, 'show']
);

$app->get(
    '/api/users/{id:[0-9]+}/summary',
    [$userSummaryController, 'show']
);

$app->run();