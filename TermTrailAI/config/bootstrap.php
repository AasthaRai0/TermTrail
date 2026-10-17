<?php
declare(strict_types=1);
define('ROOT', dirname(__DIR__));
$ENV = [];
if (is_file(ROOT.'/.env')) foreach (file(ROOT.'/.env', FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) as $l) { if ($l[0]==='#'||!str_contains($l,'=')) continue; [$k,$v]=explode('=',$l,2); $ENV[trim($k)]=trim($v); }
function storage_dir(): string { $d=rtrim(env('STORAGE_DIR',ROOT.'/storage'),'/\\'); if(!is_dir($d)) @mkdir($d,0775,true); return $d.'/'; }
function env(string $k, string $d=''): string { global $ENV; return $ENV[$k] ?? $d; }
ini_set('display_errors','0'); ini_set('log_errors','1');
session_set_cookie_params(['httponly'=>true,'samesite'=>'Lax','path'=>'/']);
session_start();
function db(): PDO { static $p=null; if($p) return $p;
  try { $p=new PDO('mysql:host='.env('DB_HOST','127.0.0.1').';dbname='.env('DB_NAME','termtrail').';charset=utf8mb4',env('DB_USER','root'),env('DB_PASS'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]); }
  catch(Throwable $e){ error_log($e->getMessage()); http_response_code(500); exit('Database connection failed. Check your .env settings.'); }
  return $p; }
function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
function csrf(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function csrf_field(): string { return '<input type="hidden" name="csrf" value="'.csrf().'">'; }
function csrf_check(): void { if($_SERVER['REQUEST_METHOD']==='POST' && !hash_equals($_SESSION['csrf']??'', $_POST['csrf']??'')){ http_response_code(419); exit('Invalid CSRF token.'); } }
function uid(): int { return (int)($_SESSION['uid'] ?? 0); }
function require_login(): void { if(!uid()){ header('Location: login.php'); exit; } }
function flash(?string $m=null, string $t='success'){ if($m!==null){$_SESSION['flash']=[$m,$t];return null;} $f=$_SESSION['flash']??null; unset($_SESSION['flash']); return $f; }
function log_act(string $a, ?int $cid=null, string $d=''): void { db()->prepare('INSERT INTO activity_logs(user_id,contract_id,action,detail) VALUES(?,?,?,?)')->execute([uid(),$cid,$a,$d]); }
function own_contract(int $id): array { $s=db()->prepare('SELECT * FROM contracts WHERE id=? AND user_id=?'); $s->execute([$id,uid()]); $c=$s->fetch(); if(!$c){ http_response_code(404); exit('Contract not found.'); } return $c; }
function valid_date(?string $d): ?string { if(!$d) return null; $x=DateTime::createFromFormat('Y-m-d',$d); return ($x && $x->format('Y-m-d')===$d)?$d:null; }
function page_head(string $title, bool $app=true): void { $f=flash(); ?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=e($title)?> · TermTrail AI</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="assets/css/app.css" rel="stylesheet"></head><body>
<?php if($app): ?><button class="btn btn-sm btn-outline-light m-2 d-md-none" onclick="document.querySelector('.side').classList.toggle('open')">Menu</button><div class="shell"><aside class="side"><a class="brand" href="dashboard.php">TermTrail <span>AI</span></a>
<nav><a href="dashboard.php">Overview</a><a href="contracts.php">Contracts</a><a href="obligations.php">Obligations</a><a href="payments.php">Payments</a><a href="settings.php">Settings</a><a href="export.php?t=obligations">Export obligations CSV</a><a href="export.php?t=payments">Export payments CSV</a>
</nav>
<form method="post" action="logout.php"><?=csrf_field()?><button class="btn btn-sm btn-outline-light mt-3">Log out</button></form></aside><main class="main">
<?php endif; if($f): ?><div class="alert alert-<?=$f[1]==='error'?'danger':'success'?>"><?=e($f[0])?></div><?php endif; }
function page_foot(bool $app=true): void { echo $app?'</main></div>':''; echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script></body></html>'; }
function extract_text(string $path, string $ext): array { // [text, method, status, error]
  try {
    if($ext==='txt'){ $t=(string)file_get_contents($path); }
    elseif($ext==='docx'){ $z=new ZipArchive; if($z->open($path)!==true) return ['','zip','failed','Corrupt DOCX']; $x=$z->getFromName('word/document.xml'); $z->close(); if($x===false) return ['','zip','failed','Not a valid DOCX'];
      $t=html_entity_decode(strip_tags(preg_replace(['/<\/w:p>/','/<w:tab\/>/'],["\n","\t"],$x)),ENT_QUOTES|ENT_XML1,'UTF-8'); }
    elseif($ext==='pdf'){ if(!is_file(ROOT.'/vendor/autoload.php')) return ['','smalot','failed','PDF parser missing: run "composer install"']; require_once ROOT.'/vendor/autoload.php';
      $t=(new \Smalot\PdfParser\Parser())->parseFile($path)->getText(); }
    else return ['','none','failed','Unsupported type'];
  } catch(Throwable $e){ error_log($e->getMessage()); return ['',$ext,'failed','Could not read file (corrupt or encrypted?)']; }
  $t=trim($t); return $t===''?['',$ext,'empty','No text found. Scanned PDFs need OCR (not configured). Use manual text entry.']:[$t,$ext,'success',''];
}
