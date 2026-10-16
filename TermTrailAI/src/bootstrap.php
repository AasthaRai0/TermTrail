<?php
declare(strict_types=1);
$root = dirname(__DIR__);
if (is_file("$root/vendor/autoload.php")) { require "$root/vendor/autoload.php"; Dotenv\Dotenv::createImmutable($root)->safeLoad(); }
function env(string $k, string $d = ''): string { return $_ENV[$k] ?? getenv($k) ?: $d; }
function base(): string { return rtrim(env('BASE_URL', '/TermTrailAI'), '/'); }
session_set_cookie_params(['httponly'=>true,'samesite'=>'Lax','secure'=>!empty($_SERVER['HTTPS']),'path'=>'/']);
session_start();
header('X-Content-Type-Options: nosniff'); header('X-Frame-Options: DENY'); header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; style-src 'self' https://cdn.jsdelivr.net; script-src 'self' https://cdn.jsdelivr.net");
function db(): PDO { static $p = null; if ($p) return $p;
  try { $p = new PDO('mysql:host='.env('DB_HOST','127.0.0.1').';dbname='.env('DB_NAME','termtrail').';charset=utf8mb4', env('DB_USER','root'), env('DB_PASS'), [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false]); }
  catch (PDOException $e) { error_log('DB: '.$e->getMessage()); http_response_code(503); exit('Database unavailable. Check your .env settings.'); }
  return $p; }
function e(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
function csrf_token(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function csrf_field(): string { return '<input type="hidden" name="csrf" value="'.e(csrf_token()).'">'; }
function csrf_check(): void { if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) { http_response_code(419); exit('Invalid or expired form token. Go back and try again.'); } }
function redirect(string $p): never { header('Location: '.base().$p); exit; }
function current_user(): ?array { static $u = false; if ($u === false) { $u = null;
  if (!empty($_SESSION['uid'])) { $s = db()->prepare('SELECT id,name,email FROM users WHERE id=?'); $s->execute([$_SESSION['uid']]); $u = $s->fetch() ?: null; } } return $u; }
function require_login(): array { $u = current_user(); if (!$u) redirect('/login.php'); return $u; }
function log_activity(?int $uid, ?int $cid, string $action, ?string $detail = null): void { db()->prepare('INSERT INTO activity_logs(user_id,contract_id,action,detail) VALUES(?,?,?,?)')->execute([$uid,$cid,$action,$detail]); }
function throttle(string $key, int $max, int $window): bool { $_SESSION['thr'][$key] = array_filter($_SESSION['thr'][$key] ?? [], fn($t)=>$t > time()-$window); if (count($_SESSION['thr'][$key]) >= $max) return false; $_SESSION['thr'][$key][] = time(); return true; }
function page_head(string $title): void { $b = base(); echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.e($title).' · TermTrail AI</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="'.$b.'/assets/css/app.css" rel="stylesheet"></head><body>'; }
function page_foot(): void { echo '</body></html>'; }
