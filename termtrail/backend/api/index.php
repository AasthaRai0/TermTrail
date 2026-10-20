<?php
declare(strict_types=1);
require dirname(__DIR__) . '/config/bootstrap.php';

use TermTrail\controllers\{AuthController, DashboardController};
use TermTrail\helpers\Response;
use TermTrail\middleware\Auth;

header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

$method = $_SERVER['REQUEST_METHOD'];
$route = trim((string)($_GET['r'] ?? ''), '/');

// Parse JSON bodies; fall back to form data.
$input = [];
if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
    $ct = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($ct, 'application/json') !== false) {
        $input = json_decode((string)file_get_contents('php://input'), true);
        if (!is_array($input)) { Response::error('Invalid JSON body.', 400); }
    } else {
        $input = $_POST;
    }
}

$routes = [
    'POST auth/register'  => fn() => AuthController::register($input),
    'POST auth/login'     => fn() => AuthController::login($input),
    'POST auth/logout'    => fn() => AuthController::logout(),
    'POST auth/password'  => fn() => AuthController::changePassword($input),
    'POST auth/profile'   => fn() => AuthController::updateProfile($input),
    'GET dashboard/stats' => fn() => DashboardController::stats(),
];

$key = "$method $route";
if (!isset($routes[$key])) {
    $known = array_filter(array_keys($routes), fn($k) => str_ends_with($k, " $route"));
    Response::error($known ? 'Method not allowed.' : 'Endpoint not found.', $known ? 405 : 404);
}

try {
    $routes[$key]();
} catch (\RuntimeException $e) {
    error_log('API error [' . $route . ']: ' . $e->getMessage());
    Response::error(APP_ENV === 'development' ? $e->getMessage() : 'Service temporarily unavailable.', 503);
} catch (\Throwable $e) {
    error_log('API error [' . $route . ']: ' . $e->getMessage());
    Response::error('Something went wrong. Please try again.', 500);
}
