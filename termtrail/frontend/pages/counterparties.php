<?php
use TermTrail\helpers\Security as S;
$title = 'Counterparties'; $active = 'counterparties'; $scripts = ['counterparties.js'];
$canWrite = in_array($user['role'], ['admin', 'manager'], true);
require __DIR__ . '/../components/app_shell_start.php';
?>
<div class="page-head">
  <div><h1 class="h3 mb-1">Counterparties</h1><p class="text-muted mb-0">Organizations and individuals you contract with.</p></div>
  <?php if ($canWrite): ?><button class="btn btn-primary" id="addBtn"><i data-lucide="plus" class="me-1" style="width:16px;height:16px"></i><span>Add counterparty</span></button><?php endif; ?>
</div>
<div class="glass p-3 mb-3"><div class="row g-2 align-items-end">
  <div class="col-md-6"><label class="form-label" for="cq">Search</label><input class="form-control" id="cq" placeholder="Name, contact or email"></div>
  <div class="col-md-4"><div class="form-check mt-md-4"><input class="form-check-input" type="checkbox" id="carch"><label class="form-check-label" for="carch">Show archived</label></div></div>
</div></div>
<div class="glass p-2 p-md-3">
  <div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr><th>Name</th><th>Contact</th><th>Email</th><th>Phone</th><th class="text-center">Contracts</th><th></th></tr></thead>
    <tbody id="rows"><tr><td colspan="6" class="text-center text-muted py-4">Loading…</td></tr></tbody></table></div>
  <div id="pager" class="mt-3"></div>
</div>
<?php if ($canWrite): ?>
<div class="modal fade" id="cpModal" tabindex="-1" aria-labelledby="cpModalTitle" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content glass">
  <form id="cpForm" data-api="counterparties/create" data-no-redirect="1" novalidate>
    <div class="modal-header"><h2 class="modal-title h5" id="cpModalTitle">Add counterparty</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <div class="modal-body">
      <input type="hidden" name="id" value="">
      <div class="mb-3"><label class="form-label" for="cp_name">Name *</label><input class="form-control" id="cp_name" name="name" maxlength="190" required><div class="invalid-feedback" data-error-for="name"></div></div>
      <div class="row g-3 mb-3"><div class="col-md-6"><label class="form-label" for="cp_contact">Contact person</label><input class="form-control" id="cp_contact" name="contact_person" maxlength="150"></div>
        <div class="col-md-6"><label class="form-label" for="cp_phone">Phone</label><input class="form-control" id="cp_phone" name="phone" maxlength="40"><div class="invalid-feedback" data-error-for="phone"></div></div></div>
      <div class="mb-3"><label class="form-label" for="cp_email">Email</label><input type="email" class="form-control" id="cp_email" name="email" maxlength="190"><div class="invalid-feedback" data-error-for="email"></div></div>
      <div class="mb-3"><label class="form-label" for="cp_address">Address</label><textarea class="form-control" id="cp_address" name="address" rows="2" maxlength="2000"></textarea></div>
      <div class="mb-1"><label class="form-label" for="cp_notes">Notes</label><textarea class="form-control" id="cp_notes" name="notes" rows="2" maxlength="2000"></textarea></div>
      <div class="alert alert-danger d-none mt-3" data-form-error></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit"><span class="btn-label">Save</span></button></div>
  </form></div></div></div>
<?php endif; ?>
<?php require __DIR__ . '/../components/app_shell_end.php'; ?>
