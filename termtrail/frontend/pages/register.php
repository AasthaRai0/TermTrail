<?php
use TermTrail\helpers\Security as S;
$title = 'Create account';
require __DIR__ . '/../components/header.php';
?>
<body class="auth-body">
<main class="auth-card glass">
  <div class="brand mb-3"><span class="brand-mark"><i data-lucide="file-search"></i></span><span class="brand-text">TermTrail <b>AI</b></span></div>
  <h1 class="h4">Create your account</h1>
  <p class="text-muted">The first account created becomes the administrator.</p>
  <form id="registerForm" data-api="auth/register" novalidate>
    <div class="mb-3"><label class="form-label" for="name">Full name</label>
      <input class="form-control" id="name" name="name" autocomplete="name" required>
      <div class="invalid-feedback" data-error-for="name"></div></div>
    <div class="mb-3"><label class="form-label" for="email">Email</label>
      <input class="form-control" type="email" id="email" name="email" autocomplete="username" required>
      <div class="invalid-feedback" data-error-for="email"></div></div>
    <div class="mb-3"><label class="form-label" for="password">Password</label>
      <input class="form-control" type="password" id="password" name="password" autocomplete="new-password" required>
      <div class="form-text">At least 8 characters with letters and numbers.</div>
      <div class="invalid-feedback" data-error-for="password"></div></div>
    <div class="mb-3"><label class="form-label" for="password_confirm">Confirm password</label>
      <input class="form-control" type="password" id="password_confirm" name="password_confirm" autocomplete="new-password" required>
      <div class="invalid-feedback" data-error-for="password_confirm"></div></div>
    <div class="alert alert-danger d-none" role="alert" data-form-error></div>
    <button class="btn btn-primary w-100" type="submit"><span class="btn-label">Create account</span></button>
  </form>
  <p class="mt-3 mb-0 small text-muted">Already registered? <a href="<?= APP_URL ?>/index.php?page=login">Sign in</a></p>
</main>
<script src="<?= APP_URL ?>/frontend/assets/js/app.js"></script>
</body></html>
