<?php
use TermTrail\helpers\Security as S;
$title = 'Sign in';
require __DIR__ . '/../components/header.php';
?>
<body class="auth-body bg-light d-flex align-items-center justify-content-center min-vh-100">

<main class="auth-card card shadow-sm border-0 p-4 rounded-4" style="width: 100%; max-width: 400px;">
  
  <!-- Brand Header -->
  <div class="brand mb-4 d-flex align-items-center gap-2">
    <span class="brand-mark text-primary bg-primary-subtle p-2 rounded-3 d-inline-flex align-items-center justify-content-center">
      <i data-lucide="file-search" style="width: 20px; height: 20px;"></i>
    </span>
    <span class="brand-text fs-5 fw-bold text-light">TermTrail <span class="text-primary">AI</span></span>
  </div>

  <h1 class="h4 fw-bold mb-1 text-light">Welcome back</h1>
  <p class="text-muted small mb-4">Understand the contract. Manage every commitment.</p>

  <form id="loginForm" data-api="auth/login" novalidate>
    <div class="mb-3">
      <label class="form-label small fw-semibold text-secondary" for="email">Email</label>
      <input class="form-control form-control-lg fs-6 py-2" type="email" id="email" name="email" autocomplete="username" required autofocus>
      <div class="invalid-feedback" data-error-for="email"></div>
    </div>

    <div class="mb-3">
      <div class="d-flex justify-content-between align-items-center">
        <label class="form-label small fw-semibold text-secondary" for="password">Password</label>
      </div>
      <input class="form-control form-control-lg fs-6 py-2" type="password" id="password" name="password" autocomplete="current-password" required>
      <div class="invalid-feedback" data-error-for="password"></div>
    </div>

    <div class="alert alert-danger d-none py-2 small" role="alert" data-form-error></div>

    <button class="btn btn-primary btn-lg fs-6 w-100 py-2 mt-2 shadow-sm" type="submit">
      <span class="btn-label">Sign in</span>
    </button>
  </form>

  <div class="mt-4 pt-3 border-top text-center">
    <?php if (\TermTrail\services\SettingsService::registrationOpen()): ?>
      <p class="mb-0 small text-muted">New here? <a href="<?= APP_URL ?>/index.php?page=register" class="text-primary text-decoration-none fw-medium">Create an account</a></p>
    <?php else: ?>
      <p class="mb-0 small text-muted">Need an account? <span class="text-secondary">Ask your administrator.</span></p>
    <?php endif; ?>
  </div>

</main>

<!-- Lucide Icons Script -->
<script>
  if (typeof lucide !== 'undefined') {
    lucide.createIcons();
  }
</script>
<script src="<?= APP_URL ?>/frontend/assets/js/app.js"></script>
</body>
</html>