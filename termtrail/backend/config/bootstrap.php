<?php
declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__, 2));
define('STORAGE_PATH', ROOT_PATH . '/storage');

$autoload = ROOT_PATH . '/vendor/autoload.php';
if (is_file($autoload)) { require $autoload; }

// Lightweight PSR-4 loader so the app core works even before `composer install`.
spl_autoload_register(function (string $class): void {
    $prefix = 'TermTrail\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) { return; }
    $rel = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file = ROOT_PATH . '/backend/' . $rel . '.php';
    if (is_file($file)) { require $file; }
});

use TermTrail\helpers\Env;
use TermTrail\helpers\Security;

Env::load(ROOT_PATH . '/.env');

define('APP_ENV', Env::get('APP_ENV', 'production'));
define('APP_URL', rtrim(Env::get('APP_URL', 'http://localhost/termtrail'), '/'));
date_default_timezone_set('UTC');

ini_set('display_errors', APP_ENV === 'development' ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', STORAGE_PATH . '/logs/php-error.log');
error_reporting(E_ALL);

Security::startSession();
