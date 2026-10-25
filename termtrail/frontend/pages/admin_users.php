<?php
use TermTrail\helpers\Security as S;
$title = 'Users'; $active = 'admin_users'; $scripts = ['admin_users.js'];
require __DIR__ . '/../components/app_shell_start.php';
?>
<div class="page-head"><div><h1 class="h3 mb-1">Users</h1><p class="text-muted mb-0">Manage accounts, roles and sign-up access.</p></div>
  <button class="btn btn-primary" id="addUser"><i data-lucide="user-plus" class="me-1" style="width:16px;height:16px"></i>Create user</button></div>
<div class="glass p-3 mb-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
  <div><strong>Self-registration</strong><div class="small text-muted">When off, only administrators can create accounts. (The very first account is always allowed.)</div></div>
  <div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" id="regSwitch"><label class="form-check-label" for="regSwitch" id="regLabel">…</label></div>
</div>
<div class="glass p-2 p-md-3"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
  <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Last sign-in</th><th class="text-center">Contracts</th><th></th></tr></thead>
  <tbody id="rows"><tr><td colspan="7" class="text-center text-muted py-4">Loading…</td></tr></tbody></table></div></div>
<p class="small text-muted mt-3 mb-0">Deactivated users are signed out immediately. Temporary passwords are shown once and are never stored or logged.</p>
<?php require __DIR__ . '/../components/app_shell_end.php'; ?>
