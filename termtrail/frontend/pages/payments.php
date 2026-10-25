<?php
use TermTrail\helpers\Security as S;
$title = 'Payments'; $active = 'payments'; $scripts = ['payments.js'];
$canWrite = in_array($user['role'], ['admin', 'manager'], true);
$opt = require __DIR__ . '/../components/options.php';
require __DIR__ . '/../components/app_shell_start.php';
?>
<div class="page-head">
  <div><h1 class="h3 mb-1">Payments</h1><p class="text-muted mb-0">Track contractual payments. This does not process or store card data.</p></div>
  <?php if ($canWrite): ?><button class="btn btn-primary" id="addBtn"><i data-lucide="plus" class="me-1" style="width:16px;height:16px"></i>Add payment</button><?php endif; ?>
</div>
<div class="row g-3 mb-3" id="sumCards">
  <?php foreach ([['upcoming', 'Upcoming', 'calendar-clock'], ['overdue', 'Overdue', 'alarm-clock'], ['paid', 'Paid', 'badge-check'], ['total', 'Total (excl. cancelled)', 'sigma']] as [$k, $l, $i]): ?>
  <div class="col-6 col-lg-3"><div class="stat-card glass"><i data-lucide="<?= $i ?>"></i><div><div class="stat-value stat-money" data-sum="<?= $k ?>">–</div><div class="stat-label"><?= S::e($l) ?></div></div></div></div>
  <?php endforeach; ?>
</div>
<form id="filters" class="glass p-3 mb-3" autocomplete="off"><div class="row g-2">
  <div class="col-12 col-lg-3"><label class="form-label" for="f_q">Search</label><input class="form-control" id="f_q" name="q" placeholder="Reference, notes, contract"></div>
  <div class="col-6 col-lg-2"><label class="form-label" for="f_status">Status</label><select class="form-select" id="f_status" name="status"><option value="">All</option><option value="upcoming">Upcoming</option><option value="planned">Planned</option><option value="due">Due</option><option value="overdue">Overdue</option><option value="paid">Paid</option><option value="cancelled">Cancelled</option></select></div>
  <div class="col-6 col-lg-2"><label class="form-label" for="f_cat">Category</label><select class="form-select" id="f_cat" name="category"><option value="">All</option></select></div>
  <div class="col-6 col-lg-3"><label class="form-label" for="f_contract">Contract</label><select class="form-select" id="f_contract" name="contract_id"><option value="">All</option></select></div>
  <div class="col-6 col-lg-2"><label class="form-label" for="f_from">Due from</label><input type="date" class="form-control" id="f_from" name="date_from"></div>
  <div class="col-6 col-lg-2"><label class="form-label" for="f_to">Due to</label><input type="date" class="form-control" id="f_to" name="date_to"></div>
  <div class="col-12 col-lg-2 d-flex align-items-end"><button type="button" class="btn btn-outline-secondary btn-sm" id="resetFilters">Reset</button></div>
</div></form>
<div class="glass p-2 p-md-3">
  <div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr><th>Contract</th><th>Category</th><th class="sortable text-end" data-sort="amount">Amount</th><th class="sortable" data-sort="due_date">Due</th><th class="sortable" data-sort="status">Status</th><th>Paid on</th><th></th></tr></thead>
    <tbody id="rows"><tr><td colspan="7" class="text-center text-muted py-4">Loading…</td></tr></tbody></table></div>
  <div id="pager" class="mt-3"></div>
</div>

<div class="modal fade" id="payModal" tabindex="-1" aria-labelledby="payModalTitle" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content glass">
  <form id="payForm" data-api="payments/create" data-no-redirect="1" novalidate>
    <div class="modal-header"><h2 class="modal-title h5" id="payModalTitle">Add payment</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <div class="modal-body"><input type="hidden" name="id" value="">
      <div class="mb-3"><label class="form-label" for="p_contract">Contract *</label><select class="form-select" id="p_contract" name="contract_id"><option value="">Select…</option></select><div class="invalid-feedback" data-error-for="contract_id"></div></div>
      <div class="row g-3 mb-3"><div class="col-7"><label class="form-label" for="p_amount">Amount *</label><input class="form-control" id="p_amount" name="amount" inputmode="decimal"><div class="invalid-feedback" data-error-for="amount"></div></div>
        <div class="col-5"><label class="form-label" for="p_currency">Currency</label><select class="form-select" id="p_currency" name="currency"><?php foreach ($opt['currencies'] as $c): ?><option><?= S::e($c) ?></option><?php endforeach; ?></select></div></div>
      <div class="row g-3 mb-3"><div class="col-6"><label class="form-label" for="p_due">Due date *</label><input type="date" class="form-control" id="p_due" name="due_date"><div class="invalid-feedback" data-error-for="due_date"></div></div>
        <div class="col-6"><label class="form-label" for="p_cat">Category</label><input class="form-control" id="p_cat" name="category" list="catList" maxlength="80"><datalist id="catList"><option>Fee</option><option>Installment</option><option>Subscription</option><option>Rent</option><option>Penalty</option><option>Deposit</option><option>Reimbursement</option></datalist></div></div>
      <div class="mb-3"><label class="form-label" for="p_ref">Reference number</label><input class="form-control" id="p_ref" name="reference_number" maxlength="100"></div>
      <div class="mb-1"><label class="form-label" for="p_notes">Notes</label><textarea class="form-control" id="p_notes" name="notes" rows="2" maxlength="2000"></textarea></div>
      <div class="alert alert-danger d-none mt-3" data-form-error></div></div>
    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button><button class="btn btn-primary" id="paySave" type="submit"><span class="btn-label">Save</span></button></div>
  </form></div></div></div>
<div class="modal fade" id="paidModal" tabindex="-1" aria-labelledby="paidTitle" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content glass">
  <form id="paidForm" data-api="payments/status" data-no-redirect="1" novalidate>
    <div class="modal-header"><h2 class="modal-title h5" id="paidTitle">Mark as paid</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <div class="modal-body"><input type="hidden" name="id"><input type="hidden" name="status" value="paid">
      <div class="mb-3"><label class="form-label" for="pd_date">Payment date</label><input type="date" class="form-control" id="pd_date" name="paid_date"><div class="invalid-feedback" data-error-for="paid_date"></div></div>
      <div class="mb-1"><label class="form-label" for="pd_ref">Reference number</label><input class="form-control" id="pd_ref" name="reference_number" maxlength="100"></div>
      <div class="alert alert-danger d-none mt-3" data-form-error></div></div>
    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit"><span class="btn-label">Mark paid</span></button></div>
  </form></div></div></div>
<?php require __DIR__ . '/../components/app_shell_end.php'; ?>
