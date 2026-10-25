<?php
use TermTrail\helpers\Security as S;
$title = 'Sign in';
require __DIR__ . '/../components/header.php';
?>
<body class="auth-body">
<main class="auth-card glass">
  <div class="brand mb-3"><span class="brand-mark"><i data-lucide="file-search"></i></span><span class="brand-text">TermTrail <b>AI</b></span></div>
  <h1 class="h4">Welcome back</h1>
  <p class="text-muted">Understand the contract. Manage every commitment.</p>
  <form id="loginForm" data-api="auth/login" novalidate>
    <div class="mb-3"><label class="form-label" for="email">Email</label>
      <input class="form-control" type="email" id="email" name="email" autocomplete="username" required autofocus>
      <div class="invalid-feedback" data-error-for="email"></div></div>
    <div class="mb-3"><label class="form-label" for="password">Password</label>
      <input class="form-control" type="password" id="password" name="password" autocomplete="current-password" required>
      <div class="invalid-feedback" data-error-for="password"></div></div>
    <div class="alert alert-danger d-none" role="alert" data-form-error></div>
    <button class="btn btn-primary w-100" type="submit"><span class="btn-label">Sign in</span></button>
  </form>
  <?php if (\TermTrail\services\SettingsService::registrationOpen()): ?>
  <p class="mt-3 mb-0 small text-muted">New here? <a href="<?= APP_URL ?>/index.php?page=register">Create an account</a></p>
  <?php else: ?><p class="mt-3 mb-0 small text-muted">Need an account? Ask your administrator.</p><?php endif; ?>
</main>
<script src="<?= APP_URL ?>/frontend/assets/js/app.js"></script>
</body></html>
