<?php require __DIR__.'/src/bootstrap.php';
$errors = []; $name = $email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') { csrf_check();
  $name = trim($_POST['name'] ?? ''); $email = strtolower(trim($_POST['email'] ?? '')); $pw = (string)($_POST['password'] ?? '');
  if ($name === '' || mb_strlen($name) > 100) $errors[] = 'Enter your name (max 100 characters).';
  if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) $errors[] = 'Enter a valid email address.';
  if (strlen($pw) < 10) $errors[] = 'Password must be at least 10 characters.';
  if (!$errors) { try {
    db()->prepare('INSERT INTO users(name,email,password_hash) VALUES(?,?,?)')->execute([$name,$email,password_hash($pw, PASSWORD_DEFAULT)]);
    $id = (int)db()->lastInsertId(); session_regenerate_id(true); $_SESSION['uid'] = $id; log_activity($id, null, 'user_registered'); redirect('/dashboard.php');
  } catch (PDOException $e) { $errors[] = $e->getCode() === '23000' ? 'An account with this email already exists.' : 'Registration failed. Please try again.'; if ($e->getCode() !== '23000') error_log($e->getMessage()); } } }
page_head('Register'); ?>
<main class="container py-5" style="max-width:460px"><div class="tt-card"><h1 class="h3 mb-3">Create your account</h1>
<?php foreach ($errors as $m): ?><div class="alert alert-danger py-2" role="alert"><?= e($m) ?></div><?php endforeach; ?>
<form method="post" novalidate><?= csrf_field() ?>
<label class="form-label" for="name">Name</label><input class="form-control mb-3" id="name" name="name" value="<?= e($name) ?>" required>
<label class="form-label" for="email">Email</label><input class="form-control mb-3" id="email" type="email" name="email" value="<?= e($email) ?>" required>
<label class="form-label" for="password">Password (10+ characters)</label><input class="form-control mb-3" id="password" type="password" name="password" minlength="10" required>
<button class="btn btn-accent w-100">Register</button></form>
<p class="mt-3 mb-0 tt-muted">Have an account? <a href="login.php">Log in</a></p></div></main><?php page_foot();
