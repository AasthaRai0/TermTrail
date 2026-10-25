<?php
use TermTrail\helpers\Security as S;
$title = 'Contracts'; $active = 'contracts'; $scripts = ['contracts.js'];
$opt = require __DIR__ . '/../components/options.php';
$canWrite = in_array($user['role'], ['admin', 'manager'], true);
require __DIR__ . '/../components/app_shell_start.php';
?>
<div class="page-head">
  <div><h1 class="h3 mb-1">Contracts</h1><p class="text-muted mb-0">Search, filter and manage your contract library.</p></div>
  <?php if ($canWrite): ?><a class="btn btn-primary" href="<?= APP_URL ?>/index.php?page=contract_new"><i data-lucide="plus" class="me-1" style="width:16px;height:16px"></i><span>New contract</span></a><?php endif; ?>
</div>
<form id="filters" class="glass p-3 mb-3" autocomplete="off">
  <div class="row g-2">
    <div class="col-12 col-lg-4"><label class="form-label" for="f_q">Search</label><input class="form-control" id="f_q" name="q" placeholder="Title, counterparty, type or contract text"></div>
    <div class="col-6 col-lg-2"><label class="form-label" for="f_status">Status</label><select class="form-select" id="f_status" name="status"><option value="">All</option>
      <?php foreach ($opt['statuses'] as $v => $l): ?><option value="<?= S::e($v) ?>"><?= S::e($l) ?></option><?php endforeach; ?></select></div>
    <div class="col-6 col-lg-2"><label class="form-label" for="f_risk">Risk</label><select class="form-select" id="f_risk" name="risk"><option value="">All</option><option value="high">High</option><option value="medium">Medium</option><option value="low">Low</option><option value="unassessed">Not assessed</option></select></div>
    <div class="col-6 col-lg-2"><label class="form-label" for="f_type">Type</label><select class="form-select" id="f_type" name="type"><option value="">All</option>
      <?php foreach ($opt['types'] as $t): ?><option><?= S::e($t) ?></option><?php endforeach; ?></select></div>
    <div class="col-6 col-lg-2"><label class="form-label" for="f_cp">Counterparty</label><select class="form-select" id="f_cp" name="counterparty_id"><option value="">All</option></select></div>
    <div class="col-6 col-lg-2"><label class="form-label" for="f_quick">Expiry window</label><select class="form-select" id="f_quick" name="quick"><option value="">Any</option><option value="expiring">Expiring in 30 days</option><option value="expired">Expired</option></select></div>
    <div class="col-6 col-lg-2"><label class="form-label" for="f_from">Expires from</label><input type="date" class="form-control" id="f_from" name="date_from"></div>
    <div class="col-6 col-lg-2"><label class="form-label" for="f_to">Expires to</label><input type="date" class="form-control" id="f_to" name="date_to"></div>
    <div class="col-12 col-lg-3 d-flex align-items-end gap-3">
      <div class="form-check"><input class="form-check-input" type="checkbox" id="f_arch" name="archived" value="1"><label class="form-check-label" for="f_arch">Show archived</label></div>
      <button type="button" class="btn btn-outline-secondary btn-sm" id="resetFilters">Reset</button></div>
  </div>
</form>
<div class="glass p-2 p-md-3">
  <div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr>
      <th class="sortable" data-sort="title">Title</th><th>Counterparty</th><th>Type</th><th class="sortable" data-sort="status">Status</th>
      <th class="sortable" data-sort="risk">Risk</th><th class="sortable" data-sort="expiry_date">Expiry</th><th class="sortable text-end" data-sort="contract_value">Value</th></tr></thead>
    <tbody id="rows"><tr><td colspan="7" class="text-center text-muted py-4">Loading…</td></tr></tbody></table></div>
  <div id="pager" class="mt-3"></div>
</div>
<?php require __DIR__ . '/../components/app_shell_end.php'; ?>
