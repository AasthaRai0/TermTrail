<?php
use TermTrail\helpers\Security as S;
$title = 'Audit log'; $active = 'audit'; $scripts = ['audit.js'];
require __DIR__ . '/../components/app_shell_start.php';
?>
<div class="page-head"><div><h1 class="h3 mb-1">Audit log</h1><p class="text-muted mb-0">Who did what, and when. Visible to administrators only. Secrets are never recorded.</p></div>
  <a class="btn btn-outline-secondary" id="auditCsv" href="#" download>Export CSV</a></div>
<form id="filters" class="glass p-3 mb-3" autocomplete="off"><div class="row g-2">
  <div class="col-6 col-lg-3"><label class="form-label" for="f_user">User</label><select class="form-select" id="f_user" name="user_id"><option value="">Anyone</option></select></div>
  <div class="col-6 col-lg-3"><label class="form-label" for="f_action">Action</label><select class="form-select" id="f_action" name="action"><option value="">All actions</option></select></div>
  <div class="col-6 col-lg-2"><label class="form-label" for="f_from">From</label><input type="date" class="form-control" id="f_from" name="date_from"></div>
  <div class="col-6 col-lg-2"><label class="form-label" for="f_to">To</label><input type="date" class="form-control" id="f_to" name="date_to"></div>
  <div class="col-12 col-lg-2 d-flex align-items-end"><button type="button" class="btn btn-outline-secondary btn-sm" id="resetFilters">Reset</button></div>
</div></form>
<div class="glass p-2 p-md-3"><div class="table-responsive"><table class="table table-sm align-middle mb-0">
  <thead><tr><th>Time (server)</th><th>User</th><th>Action</th><th>Record</th><th>Details</th><th>IP</th></tr></thead>
  <tbody id="rows"><tr><td colspan="6" class="text-center text-muted py-4">Loading…</td></tr></tbody></table></div><div id="pager" class="mt-3"></div></div>
<?php require __DIR__ . '/../components/app_shell_end.php'; ?>
