<?php
use TermTrail\helpers\Security as S;
$title = 'Obligations'; $active = 'obligations'; $scripts = ['obligations.js'];
$canWrite = in_array($user['role'], ['admin', 'manager'], true);
require __DIR__ . '/../components/app_shell_start.php';
?>
<div class="page-head">
  <div><h1 class="h3 mb-1">Obligations</h1><p class="text-muted mb-0">Commitments and deadlines across your contracts.</p></div>
  <div class="d-flex gap-2">
    <div class="btn-group" role="group" aria-label="View"><a class="btn btn-primary btn-sm disabled" aria-current="page">List</a><a class="btn btn-outline-secondary btn-sm" href="<?= APP_URL ?>/index.php?page=calendar">Calendar</a></div>
    <?php if ($canWrite): ?><a class="btn btn-primary btn-sm" href="<?= APP_URL ?>/index.php?page=obligation_new"><i data-lucide="plus" class="me-1" style="width:16px;height:16px"></i>New obligation</a><?php endif; ?>
  </div>
</div>
<form id="filters" class="glass p-3 mb-3" autocomplete="off"><div class="row g-2">
  <div class="col-12 col-lg-3"><label class="form-label" for="f_q">Search</label><input class="form-control" id="f_q" name="q" placeholder="Title or contract"></div>
  <div class="col-6 col-lg-2"><label class="form-label" for="f_status">Status</label><select class="form-select" id="f_status" name="status"><option value="">All</option><option value="active">Open &amp; overdue</option><option value="open">Open</option><option value="overdue">Overdue</option><option value="completed">Completed</option><option value="cancelled">Cancelled</option></select></div>
  <div class="col-6 col-lg-2"><label class="form-label" for="f_pri">Priority</label><select class="form-select" id="f_pri" name="priority"><option value="">All</option><option value="high">High</option><option value="medium">Medium</option><option value="low">Low</option></select></div>
  <div class="col-6 col-lg-3"><label class="form-label" for="f_contract">Contract</label><select class="form-select" id="f_contract" name="contract_id"><option value="">All</option></select></div>
  <div class="col-6 col-lg-2"><label class="form-label" for="f_owner">Owner</label><select class="form-select" id="f_owner" name="owner_id"><option value="">Anyone</option><option value="me">Assigned to me</option></select></div>
  <div class="col-6 col-lg-2"><label class="form-label" for="f_from">Due from</label><input type="date" class="form-control" id="f_from" name="date_from"></div>
  <div class="col-6 col-lg-2"><label class="form-label" for="f_to">Due to</label><input type="date" class="form-control" id="f_to" name="date_to"></div>
  <div class="col-12 col-lg-2 d-flex align-items-end"><button type="button" class="btn btn-outline-secondary btn-sm" id="resetFilters">Reset</button></div>
</div></form>
<div class="glass p-2 p-md-3">
  <div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr><th class="sortable" data-sort="title">Title</th><th>Contract</th><th class="sortable" data-sort="due_date">Due</th><th class="sortable" data-sort="priority">Priority</th><th class="sortable" data-sort="status">Status</th><th>Owner</th></tr></thead>
    <tbody id="rows"><tr><td colspan="6" class="text-center text-muted py-4">Loading…</td></tr></tbody></table></div>
  <div id="pager" class="mt-3"></div>
</div>
<?php require __DIR__ . '/../components/app_shell_end.php'; ?>
