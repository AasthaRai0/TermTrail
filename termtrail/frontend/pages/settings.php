<?php
use TermTrail\helpers\Security as S;
$title = 'Account settings'; $active = 'settings';
require __DIR__ . '/../components/app_shell_start.php';
?>
<div class="page-head"><h1 class="h3">Account settings</h1></div>
<div class="row g-3">
  <div class="col-lg-6"><div class="glass p-4">
    <h2 class="h6 mb-3">Profile</h2>
    <form data-api="auth/profile" novalidate>
      <div class="mb-3"><label class="form-label" for="pname">Name</label>
        <input class="form-control" id="pname" name="name" value="<?= S::e($user['name']) ?>" required>
        <div class="invalid-feedback" data-error-for="name"></div></div>
      <div class="mb-3"><label class="form-label">Email</label><input class="form-control" value="<?= S::e($user['email']) ?>" disabled></div>
      <div class="mb-3"><label class="form-label" for="ptheme">Theme</label>
        <select class="form-select" id="ptheme" name="theme">
          <option value="dark" <?= $user['theme'] === 'dark' ? 'selected' : '' ?>>Dark</option>
          <option value="light" <?= $user['theme'] === 'light' ? 'selected' : '' ?>>Light</option></select></div>
      <div class="alert alert-danger d-none" data-form-error></div>
      <button class="btn btn-primary" type="submit"><span class="btn-label">Save profile</span></button>
    </form></div></div>
  <div class="col-lg-6"><div class="glass p-4">
    <h2 class="h6 mb-3">Change password</h2>
    <form data-api="auth/password" data-reset="1" novalidate>
      <div class="mb-3"><label class="form-label" for="cp">Current password</label>
        <input class="form-control" type="password" id="cp" name="current_password" autocomplete="current-password" required>
        <div class="invalid-feedback" data-error-for="current_password"></div></div>
      <div class="mb-3"><label class="form-label" for="np">New password</label>
        <input class="form-control" type="password" id="np" name="new_password" autocomplete="new-password" required>
        <div class="invalid-feedback" data-error-for="new_password"></div></div>
      <div class="mb-3"><label class="form-label" for="npc">Confirm new password</label>
        <input class="form-control" type="password" id="npc" name="new_password_confirm" autocomplete="new-password" required>
        <div class="invalid-feedback" data-error-for="new_password_confirm"></div></div>
      <div class="alert alert-danger d-none" data-form-error></div>
      <button class="btn btn-primary" type="submit"><span class="btn-label">Update password</span></button>
    </form></div></div>
</div>
<?php require __DIR__ . '/../components/app_shell_end.php'; ?>
