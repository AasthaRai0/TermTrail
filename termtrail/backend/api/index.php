<?php
declare(strict_types=1);
require dirname(__DIR__) . '/config/bootstrap.php';

use TermTrail\controllers\{AdminController, ReportController, TemplateController, VersionController, AnalysisController, AuthController, CalendarController, ContractController, CopilotController, CounterpartyController, DashboardController, FindingController, NotificationController, ObligationController, PaymentController, RenewalController, SuggestionController, UserController};
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

// Oversized uploads: PHP drops the whole body when post_max_size is exceeded.
if ($method === 'POST' && !$input && !$_FILES && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0 && stripos($_SERVER['CONTENT_TYPE'] ?? '', 'multipart/') !== false) {
    Response::error('The upload is larger than the server limit (post_max_size). Use a smaller file.', 413);
}

$routes = [
    'POST auth/register'  => fn() => AuthController::register($input),
    'POST auth/login'     => fn() => AuthController::login($input),
    'POST auth/logout'    => fn() => AuthController::logout(),
    'POST auth/password'  => fn() => AuthController::changePassword($input),
    'POST auth/profile'   => fn() => AuthController::updateProfile($input),
    'GET dashboard/stats' => fn() => DashboardController::stats(),

    'GET contracts/list'      => fn() => ContractController::list(),
    'GET contracts/get'       => fn() => ContractController::get(),
    'POST contracts/create'   => fn() => ContractController::create($input),
    'POST contracts/update'   => fn() => ContractController::update($input),
    'POST contracts/file'     => fn() => ContractController::replaceFile($input),
    'POST contracts/archive'  => fn() => ContractController::setArchived($input, true),
    'POST contracts/restore'  => fn() => ContractController::setArchived($input, false),
    'POST contracts/delete'   => fn() => ContractController::delete($input),
    'GET contracts/download'  => fn() => ContractController::download(),
    'GET contracts/text'      => fn() => ContractController::text(),
    'POST contracts/notes'    => fn() => ContractController::addNote($input),

    'GET counterparties/list'     => fn() => CounterpartyController::list(),
    'GET counterparties/options'  => fn() => CounterpartyController::options(),
    'GET counterparties/get'      => fn() => CounterpartyController::get(),
    'POST counterparties/create'  => fn() => CounterpartyController::create($input),
    'POST counterparties/update'  => fn() => CounterpartyController::update($input),
    'POST counterparties/archive' => fn() => CounterpartyController::setArchived($input, true),
    'POST counterparties/restore' => fn() => CounterpartyController::setArchived($input, false),
    'POST counterparties/delete'  => fn() => CounterpartyController::delete($input),

    'GET users/options' => fn() => UserController::options(),
    'GET contracts/options' => fn() => ContractController::options(),

    'GET obligations/list'     => fn() => ObligationController::list(),
    'GET obligations/get'      => fn() => ObligationController::get(),
    'POST obligations/create'  => fn() => ObligationController::create($input),
    'POST obligations/update'  => fn() => ObligationController::update($input),
    'POST obligations/status'  => fn() => ObligationController::setStatus($input),
    'POST obligations/delete'  => fn() => ObligationController::delete($input),

    'GET payments/list'     => fn() => PaymentController::list(),
    'GET payments/summary'  => fn() => PaymentController::summary(),
    'GET payments/get'      => fn() => PaymentController::get(),
    'POST payments/create'  => fn() => PaymentController::create($input),
    'POST payments/update'  => fn() => PaymentController::update($input),
    'POST payments/status'  => fn() => PaymentController::setStatus($input),
    'POST payments/delete'  => fn() => PaymentController::delete($input),

    'GET renewals/list'     => fn() => RenewalController::list(),
    'POST renewals/decision' => fn() => RenewalController::decide($input),

    'GET calendar/events'   => fn() => CalendarController::events(),

    'GET versions/list'        => fn() => VersionController::list(),
    'POST versions/upload'     => fn() => VersionController::upload($input),
    'GET versions/download'    => fn() => VersionController::download(),
    'POST versions/compare'    => fn() => VersionController::compare($input),
    'GET versions/comparison'  => fn() => VersionController::comparison(),

    'GET templates/list'      => fn() => TemplateController::list(),
    'GET templates/get'       => fn() => TemplateController::get(),
    'POST templates/create'   => fn() => TemplateController::create($input),
    'POST templates/update'   => fn() => TemplateController::update($input),
    'POST templates/archive'  => fn() => TemplateController::setArchived($input, true),
    'POST templates/restore'  => fn() => TemplateController::setArchived($input, false),

    'GET reports/types'   => fn() => ReportController::types(),
    'GET reports/data'    => fn() => ReportController::data(),
    'GET reports/export'  => fn() => ReportController::export(),

    'GET admin/users'            => fn() => AdminController::users(),
    'POST admin/users_create'    => fn() => AdminController::createUser($input),
    'POST admin/users_role'      => fn() => AdminController::setRole($input),
    'POST admin/users_active'    => fn() => AdminController::setActive($input),
    'POST admin/users_password'  => fn() => AdminController::resetPassword($input),
    'POST admin/settings'        => fn() => AdminController::setSettings($input),
    'GET admin/audit'            => fn() => AdminController::audit(),

    'GET ai/status'            => fn() => AnalysisController::aiStatus(),
    'POST analysis/start'      => fn() => AnalysisController::start($input),
    'POST analysis/step'       => fn() => AnalysisController::step($input),
    'GET analysis/status'      => fn() => AnalysisController::status(),
    'GET analysis/get'         => fn() => AnalysisController::get(),
    'POST analysis/apply_date' => fn() => AnalysisController::applyDate($input),
    'POST suggestions/approve' => fn() => SuggestionController::approve($input),
    'POST suggestions/reject'  => fn() => SuggestionController::reject($input),
    'POST findings/review'     => fn() => FindingController::review($input),
    'POST copilot/ask'         => fn() => CopilotController::ask($input),
    'GET copilot/history'      => fn() => CopilotController::history(),
    'POST copilot/clear'       => fn() => CopilotController::clear($input),

    'GET notifications/list'      => fn() => NotificationController::list(),
    'POST notifications/read'     => fn() => NotificationController::read($input),
    'POST notifications/read_all' => fn() => NotificationController::readAll(),
    'GET reminders/status'        => fn() => NotificationController::reminderStatus(),
    'POST reminders/run'          => fn() => NotificationController::runReminders(),
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
