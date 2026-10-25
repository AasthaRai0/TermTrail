<?php
declare(strict_types=1);
require __DIR__ . '/backend/config/bootstrap.php';

use TermTrail\middleware\Auth;

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

// page => [file, public?, min role]
$pages = [
    'login'     => ['login.php', true],
    'register'  => ['register.php', true],
    'dashboard' => ['dashboard.php', false],
    'settings'  => ['settings.php', false],
    'contracts'         => ['contracts.php', false],
    'contract_new'      => ['contract_form.php', false, 'manager'],
    'contract_edit'     => ['contract_form.php', false, 'manager'],
    'contract_view'     => ['contract_view.php', false],
    'counterparties'    => ['counterparties.php', false],
    'counterparty_view' => ['counterparty_view.php', false],
    'obligations'       => ['obligations.php', false],
    'obligation_new'    => ['obligation_form.php', false, 'manager'],
    'obligation_edit'   => ['obligation_form.php', false, 'manager'],
    'obligation_view'   => ['obligation_view.php', false],
    'payments'          => ['payments.php', false],
    'renewals'          => ['renewals.php', false],
    'calendar'          => ['calendar.php', false],
    'clause_library'    => ['clause_library.php', false],
    'reports'           => ['reports.php', false],
    'admin_users'       => ['admin_users.php', false, 'admin'],
    'audit'             => ['audit.php', false, 'admin'],
];

$page = (string)($_GET['page'] ?? '');
if ($page === '') {
    header('Location: ' . APP_URL . '/index.php?page=' . (Auth::user() ? 'dashboard' : 'login'));
    exit;
}
if (!isset($pages[$page])) { http_response_code(404); $page = 'notfound'; }

try {
    if ($page === 'notfound') {
        $user = Auth::user();
        $title = 'Page not found';
        require __DIR__ . '/frontend/pages/notfound.php';
        exit;
    }
    [$file, $public, $minRole] = $pages[$page] + [2 => 'viewer'];
    if ($public) {
        if (Auth::user()) { header('Location: ' . APP_URL . '/index.php?page=dashboard'); exit; }
        $user = null;
    } else {
        $user = Auth::requirePage($minRole);
    }
    require __DIR__ . '/frontend/pages/' . $file;
} catch (\RuntimeException $e) {
    http_response_code(503);
    error_log($e->getMessage());
    echo '<!doctype html><meta charset="utf-8"><title>TermTrail AI</title><body style="font-family:sans-serif;padding:3rem;background:#0b1020;color:#e8eaf6"><h1>Service unavailable</h1><p>'
        . (APP_ENV === 'development' ? htmlspecialchars($e->getMessage()) : 'Please try again shortly.') . '</p></body>';
}
