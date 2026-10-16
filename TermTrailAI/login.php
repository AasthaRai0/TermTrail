<?php require __DIR__.'/src/bootstrap.php';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') { csrf_check();
  if (!throttle('login', 5, 300)) { $error = 'Too many attempts. Wait a few minutes and try again.'; }
  else { $s = db()->prepare('SELECT id,password_hash FROM users WHERE email=?'); $s->execute([strtolower(trim($_POST['email'] ?? ''))]); $u = $s->fetch();
    $hash = $u['password_hash'] ?? '$2y$10$usesomesillystringforsalt.invalidinvalidinvalidinvalidinv';
    if (password_verify((string)($_POST['password'] ?? ''), $hash) && $u) { session_regenerate_id(true); $_SESSION['uid'] = (int)$u['id']; log_activity((int)$u['id'], null, 'login'); redirect('/dashboard.php'); }
    $error = 'Invalid email or password.'; } }
page_head('Log in'); ?>
<main class="container py-5" style="max-width:460px"><div class="tt-card"><h1 class="h3 mb-3">Log in</h1>
<?php if ($error): ?><div class="alert alert-danger py-2" role="alert"><?= e($error) ?></div><?php endif; ?>
<form method="post"><?= csrf_field() ?>
<label class="form-label" for="email">Email</label><input class="form-control mb-3" id="email" type="email" name="email" required>
<label class="form-label" for="password">Password</label><input class="form-control mb-3" id="password" type="password" name="password" required>
<button class="btn btn-accent w-100">Log in</button></form>
<p class="mt-3 mb-0 tt-muted">New here? <a href="register.php">Create an account</a></p></div></main><?php page_foot();
