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
    [$file, $public] = $pages[$page];
    if ($public) {
        if (Auth::user()) { header('Location: ' . APP_URL . '/index.php?page=dashboard'); exit; }
        $user = null;
    } else {
        $user = Auth::requirePage();
    }
    require __DIR__ . '/frontend/pages/' . $file;
} catch (\RuntimeException $e) {
    http_response_code(503);
    error_log($e->getMessage());
    echo '<!doctype html><meta charset="utf-8"><title>TermTrail AI</title><body style="font-family:sans-serif;padding:3rem;background:#0b1020;color:#e8eaf6"><h1>Service unavailable</h1><p>'
        . (APP_ENV === 'development' ? htmlspecialchars($e->getMessage()) : 'Please try again shortly.') . '</p></body>';
}
