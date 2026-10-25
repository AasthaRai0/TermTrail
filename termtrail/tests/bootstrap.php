<?php
declare(strict_types=1);

// Marks the process as a test run: config/bootstrap.php then ignores the real .env file.
define('TT_TESTING', true);
require dirname(__DIR__) . '/backend/config/bootstrap.php';
ini_set('display_errors', '1'); // in tests, show problems on screen

$db = (string)getenv('DB_NAME');
if (!preg_match('/^[A-Za-z0-9_]+_test$/', $db)) {
    fwrite(STDERR, "Refusing to run tests: DB_NAME must end with \"_test\" (got \"$db\"). Tests wipe their database.\n");
    exit(1);
}

// Create the throw-away test database and schema if needed (never touches any other database).
$dsn = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', getenv('DB_HOST') ?: '127.0.0.1', getenv('DB_PORT') ?: '3306');
try {
    $pdo = new PDO($dsn, (string)getenv('DB_USER'), (string)getenv('DB_PASS'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
    fwrite(STDERR, "Cannot connect to MySQL for tests: " . $e->getMessage() . "\nStart MySQL in XAMPP and check phpunit.xml.\n");
    exit(1);
}
$pdo->exec("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo->exec("USE `$db`");
foreach (preg_split('/;\s*\n/', (string)file_get_contents(ROOT_PATH . '/database/schema.sql')) as $stmt) {
    $stmt = trim($stmt);
    if ($stmt !== '' && !str_starts_with($stmt, '--')) { $pdo->exec($stmt); }
}

spl_autoload_register(function (string $c): void {
    if (strncmp($c, 'TermTrail\\Tests\\', 16) === 0) {
        $f = __DIR__ . '/' . str_replace('\\', '/', substr($c, 16)) . '.php';
        if (is_file($f)) { require $f; }
    }
});
