<?php
/**
 * Project self-check. Run:  php tools/check.php
 * Verifies that every page, script, include and API route referenced by the code actually exists, and that all PHP files have valid syntax.
 * It does not need a database or a web server.
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$problems = []; $checked = 0;
$fail = function (string $m) use (&$problems) { $problems[] = $m; };
$ok = function () use (&$checked) { $checked++; };

// 1. PHP syntax
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    $p = $f->getPathname();
    if (substr($p, -4) !== '.php' || strpos($p, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR) !== false) { continue; }
    $out = []; $code = 0;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($p) . ' 2>&1', $out, $code);
    $code === 0 ? $ok() : $fail('Syntax error in ' . substr($p, strlen($root) + 1) . ': ' . implode(' ', $out));
}

// 2. Page registry -> files
$index = (string)file_get_contents($root . '/index.php');
preg_match_all("/'([a-z_]+)'\s*=>\s*\['([a-z_]+\.php)'/", $index, $m, PREG_SET_ORDER);
$pages = [];
foreach ($m as $x) { $pages[$x[1]] = $x[2]; is_file($root . '/frontend/pages/' . $x[2]) ? $ok() : $fail("Page '{$x[1]}' points to missing frontend/pages/{$x[2]}"); }

// 3. Page templates: includes and scripts
foreach (glob($root . '/frontend/pages/*.php') as $file) {
    $src = (string)file_get_contents($file); $name = basename($file);
    preg_match_all("/require __DIR__ \\. '\\/\\.\\.\\/components\\/([a-z_]+\\.php)'/", $src, $inc);
    foreach ($inc[1] as $c) { is_file($root . '/frontend/components/' . $c) ? $ok() : $fail("$name includes missing component $c"); }
    if (preg_match('/\$scripts\s*=\s*\[(.*?)\]/s', $src, $s)) {
        preg_match_all("/'([A-Za-z0-9_\\-]+\\.js)'/", $s[1], $js);
        foreach ($js[1] as $j) { is_file($root . '/frontend/assets/js/' . $j) ? $ok() : $fail("$name loads missing script $j"); }
    }
}
foreach (['style.css', 'components.css', 'responsive.css'] as $css) { is_file($root . '/frontend/assets/css/' . $css) ? $ok() : $fail("Missing stylesheet $css"); }
foreach (['app.js', 'notifications.js'] as $j) { is_file($root . '/frontend/assets/js/' . $j) ? $ok() : $fail("Missing core script $j"); }

// 4. Front-end -> API routes and pages
$api = (string)file_get_contents($root . '/backend/api/index.php');
preg_match_all("/'(?:GET|POST) ([a-z_]+\\/[a-z_]+)'/", $api, $r);
$routes = array_flip($r[1]);
foreach (glob($root . '/frontend/assets/js/*.js') as $file) {
    $src = (string)file_get_contents($file); $name = basename($file);
    preg_match_all("/(?:TT\\.api\\(|data-api=\"|\\.api\\(|dataset\\.api = )'?([a-z_]+\\/[a-z_]+)'/", $src, $a);
    preg_match_all("/TT\\.api\\('([a-z_]+\\/[a-z_]+)'/", $src, $b);
    foreach (array_unique(array_merge($a[1], $b[1])) as $route) { isset($routes[$route]) ? $ok() : $fail("$name calls unknown API route '$route'"); }
    preg_match_all("/TT\\.url\\('([a-z_]+)'/", $src, $u);
    foreach (array_unique($u[1]) as $pg) { isset($pages[$pg]) ? $ok() : $fail("$name links to unknown page '$pg'"); }
}
foreach (glob($root . '/frontend/pages/*.php') as $file) {
    $src = (string)file_get_contents($file); $name = basename($file);
    preg_match_all('/data-api="([a-z_]+\/[a-z_]+)"/', $src, $a);
    foreach ($a[1] as $route) { isset($routes[$route]) ? $ok() : $fail("$name form posts to unknown API route '$route'"); }
    preg_match_all('/page=([a-z_]+)/', $src, $u);
    foreach (array_unique($u[1]) as $pg) { isset($pages[$pg]) || in_array($pg, ['login', 'register'], true) ? $ok() : $fail("$name links to unknown page '$pg'"); }
}

// 5. Router -> controller methods
preg_match_all('/(\w+Controller)::(\w+)\(/', $api, $c, PREG_SET_ORDER);
foreach ($c as $x) {
    $f = $root . '/backend/controllers/' . $x[1] . '.php';
    if (!is_file($f)) { $fail("Router uses missing controller {$x[1]}"); continue; }
    preg_match('/function ' . $x[2] . '\(/', (string)file_get_contents($f)) ? $ok() : $fail("Router calls {$x[1]}::{$x[2]}() which does not exist");
}

// 6. Required folders/files
foreach (['storage/contracts', 'storage/extracted_text', 'storage/reports', 'storage/logs', 'database/schema.sql', 'database/seed.sql', 'cron/reminders.php', '.env.example', 'composer.json'] as $p) {
    file_exists($root . '/' . $p) ? $ok() : $fail("Missing $p");
}
foreach (['storage', 'database'] as $d) { is_file($root . "/$d/.htaccess") ? $ok() : $fail("$d/.htaccess is missing (direct web access must be blocked)"); }
echo ($problems ? "PROBLEMS FOUND:\n - " . implode("\n - ", $problems) . "\n" : '') . "Checks passed: $checked" . ($problems ? ', problems: ' . count($problems) : ', problems: 0') . "\n";
exit($problems ? 1 : 0);
