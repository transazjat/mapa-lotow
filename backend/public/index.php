<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Transazja\MapaLotowApi\Controller\AccountExportController;
use Transazja\MapaLotowApi\Controller\AdminController;
use Transazja\MapaLotowApi\Controller\AuthController;
use Transazja\MapaLotowApi\Controller\FlightController;
use Transazja\MapaLotowApi\Controller\PublicProfileController;
use Transazja\MapaLotowApi\Controller\TransAzjaOfferController;
use Transazja\MapaLotowApi\Database\Database;
use Transazja\MapaLotowApi\Security\AuthService;
use Transazja\MapaLotowApi\Service\SmtpMailer;

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

$config = require __DIR__ . '/../config/database.php';
$database = new Database($config);
$pdo = $database->getConnection();

$appUrl = rtrim(
    (string) ($_ENV['APP_URL'] ?? 'https://mapalotow.test'),
    '/'
);


$appEnv = mb_strtolower(
    trim((string) ($_ENV['APP_ENV'] ?? 'local')),
    'UTF-8'
);

$sessionSecure = filter_var(
    $_ENV['SESSION_SECURE_COOKIE'] ?? 'true',
    FILTER_VALIDATE_BOOL
);

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.gc_maxlifetime', (string) (60 * 60 * 24 * 30));

session_name('mapalotow_session');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $sessionSecure,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

$mailer = new SmtpMailer(
    (string) ($_ENV['SMTP_HOST'] ?? '127.0.0.1'),
    (int) ($_ENV['SMTP_PORT'] ?? 1025),
    (string) ($_ENV['SMTP_FROM_EMAIL'] ?? 'noreply@mapalotow.test'),
    (string) ($_ENV['SMTP_FROM_NAME'] ?? 'Mapa Lotów'),
    isset($_ENV['SMTP_USERNAME']) && $_ENV['SMTP_USERNAME'] !== ''
        ? (string) $_ENV['SMTP_USERNAME']
        : null,
    isset($_ENV['SMTP_PASSWORD']) && $_ENV['SMTP_PASSWORD'] !== ''
        ? (string) $_ENV['SMTP_PASSWORD']
        : null,
    (string) ($_ENV['SMTP_ENCRYPTION'] ?? 'none')
);

$authService = new AuthService($pdo);
$authController = new AuthController($pdo, $authService, $mailer, $appUrl);
$accountExportController = new AccountExportController($pdo, $authService);
$adminController = new AdminController($pdo, $authService);
$flightController = new FlightController($pdo, $authService);
$publicProfileController = new PublicProfileController($pdo, $appUrl);
$transAzjaOfferController = new TransAzjaOfferController();

$app = AppFactory::create();
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
$app->addErrorMiddleware(true, true, true);

$app->get('/api/health', function (
    Request $request,
    Response $response
): Response {
    $response->getBody()->write(
        json_encode([
            'status' => 'ok',
            'application' => 'Mapa Lotow API',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    );

    return $response->withHeader('Content-Type', 'application/json');
});

$app->post('/api/dev/mail-test', function (
    Request $request,
    Response $response
) use ($mailer, $appEnv): Response {
    if (!in_array($appEnv, ['local', 'development', 'dev'], true)) {
        $response->getBody()->write(
            json_encode([
                'status' => 'error',
                'message' => 'Endpoint testowy jest wyłączony.',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        return $response
            ->withStatus(404)
            ->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    $data = $request->getParsedBody();
    $email = is_array($data)
        ? trim((string) ($data['email'] ?? ''))
        : '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response->getBody()->write(
            json_encode([
                'status' => 'error',
                'message' => 'Podaj poprawny adres e-mail.',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        return $response
            ->withStatus(422)
            ->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    $mailer->send(
        $email,
        'Test Mailpit - Mapa Lotów',
        '<h1>Mapa Lotów</h1><p>Połączenie SMTP z Mailpit działa prawidłowo.</p>'
    );

    $response->getBody()->write(
        json_encode([
            'status' => 'ok',
            'message' => 'Wiadomość testowa została wysłana do Mailpit.',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    );

    return $response->withHeader(
        'Content-Type',
        'application/json; charset=utf-8'
    );
});

$app->get('/api/db-test', function (
    Request $request,
    Response $response
) use ($pdo): Response {
    $result = $pdo
        ->query('SELECT DATABASE() AS database_name')
        ->fetch();

    $response->getBody()->write(
        json_encode([
            'status' => 'ok',
            'database' => $result['database_name'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    );

    return $response->withHeader('Content-Type', 'application/json');
});

$app->get('/api/auth/me', [$authController, 'me']);
$app->post('/api/auth/register', [$authController, 'register']);
$app->post('/api/auth/activate', [$authController, 'activate']);
$app->post('/api/auth/resend-activation', [$authController, 'resendActivation']);
$app->post('/api/auth/login', [$authController, 'login']);
$app->post('/api/auth/logout', [$authController, 'logout']);
$app->post('/api/auth/forgot-password', [$authController, 'forgotPassword']);
$app->post('/api/auth/reset-password', [$authController, 'resetPassword']);
$app->put('/api/account/profile', [$authController, 'updateProfile']);
$app->put('/api/account/privacy', [$authController, 'updatePrivacy']);
$app->post('/api/account/share-link/regenerate', [$authController, 'regenerateShareLink']);
$app->put('/api/account/password', [$authController, 'changePassword']);
$app->post('/api/account/email', [$authController, 'requestEmailChange']);
$app->post('/api/account/email/confirm', [$authController, 'confirmEmailChange']);
$app->get('/api/account/export/{format:csv|xlsx|json}', [$accountExportController, 'export']);

$app->get(
    '/api/public/profile/{slug:[A-Za-z0-9-]+}',
    [$publicProfileController, 'publicProfile']
);
$app->get(
    '/api/shared/map/{token:[A-Fa-f0-9]+}',
    [$publicProfileController, 'sharedMap']
);

$app->get('/api/transazja/offers', [$transAzjaOfferController, 'index']);

$app->get('/api/admin/dashboard', [$adminController, 'dashboard']);
$app->get('/api/admin/users', [$adminController, 'users']);
$app->get('/api/admin/users/{id:[0-9]+}', [$adminController, 'user']);
$app->put('/api/admin/users/{id:[0-9]+}', [$adminController, 'updateUser']);
$app->get('/api/admin/flights', [$adminController, 'flights']);
$app->get('/api/admin/flights/{id:[0-9]+}', [$adminController, 'flight']);
$app->delete('/api/admin/flights/{id:[0-9]+}', [$adminController, 'deleteFlight']);

$app->get('/api/flights', [$flightController, 'index']);
$app->post('/api/flights', [$flightController, 'create']);
$app->put('/api/flights/{id:[0-9]+}', [$flightController, 'update']);
$app->delete('/api/flights/{id:[0-9]+}', [$flightController, 'delete']);
$app->get('/api/flights/{id:[0-9]+}', [$flightController, 'show']);

$app->get('/api/airports/search', [$flightController, 'searchAirports']);
$app->get('/api/airlines/search', [$flightController, 'searchAirlines']);
$app->get('/api/aircraft-types/search', [$flightController, 'searchAircraftTypes']);

$app->run();
