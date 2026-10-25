<?php
use TermTrail\helpers\Security as S;
$editing = ($page === 'obligation_edit');
$oid = (int)($_GET['id'] ?? 0);
$title = $editing ? 'Edit obligation' : 'New obligation'; $active = 'obligations'; $scripts = ['obligation_form.js'];
require __DIR__ . '/../components/app_shell_start.php';
?>
<div class="page-head"><h1 class="h3"><?= S::e($title) ?></h1></div>
<form id="obForm" class="glass p-4" data-api="<?= $editing ? 'obligations/update' : 'obligations/create' ?>" data-edit-id="<?= $editing ? $oid : 0 ?>" novalidate>
  <?php if ($editing): ?><input type="hidden" name="id" value="<?= $oid ?>"><?php endif; ?>
  <div class="row g-3">
    <div class="col-12"><label class="form-label" for="contract_id">Contract *</label><select class="form-select" id="contract_id" name="contract_id" required><option value="">Select a contract…</option></select><div class="invalid-feedback" data-error-for="contract_id"></div></div>
    <div class="col-12"><label class="form-label" for="title">Title *</label><input class="form-control" id="title" name="title" maxlength="255" required><div class="invalid-feedback" data-error-for="title"></div></div>
    <div class="col-12"><label class="form-label" for="description">Description</label><textarea class="form-control" id="description" name="description" rows="3" maxlength="5000"></textarea></div>
    <div class="col-md-6"><label class="form-label" for="responsible_party">Responsible party</label><input class="form-control" id="responsible_party" name="responsible_party" maxlength="190" placeholder="e.g. Vendor, Our company"></div>
    <div class="col-md-6"><label class="form-label" for="owner_id">Internal owner</label><select class="form-select" id="owner_id" name="owner_id"><option value="">Unassigned</option></select><div class="form-text">Must be the contract owner, its reviewer, or an admin.</div><div class="invalid-feedback" data-error-for="owner_id"></div></div>
    <div class="col-md-4"><label class="form-label" for="due_date">Due date</label><input type="date" class="form-control" id="due_date" name="due_date"><div class="invalid-feedback" data-error-for="due_date"></div></div>
    <div class="col-md-4"><label class="form-label" for="recurrence">Recurrence</label><select class="form-select" id="recurrence" name="recurrence"><option value="none">None</option><option value="weekly">Weekly</option><option value="monthly">Monthly</option><option value="quarterly">Quarterly</option><option value="yearly">Yearly</option></select><div class="form-text">The next occurrence is created when this one is completed.</div></div>
    <div class="col-md-4"><label class="form-label" for="priority">Priority</label><select class="form-select" id="priority" name="priority"><option value="low">Low</option><option value="medium" selected>Medium</option><option value="high">High</option></select></div>
    <div class="col-12"><label class="form-label" for="source_quote">Source clause / evidence</label><textarea class="form-control" id="source_quote" name="source_quote" rows="2" maxlength="2000" placeholder="Optional: quote the contract text this comes from"></textarea></div>
  </div>
  <div class="alert alert-danger d-none mt-3" role="alert" data-form-error></div>
  <div class="d-flex gap-2 mt-4"><button class="btn btn-primary" type="submit"><span class="btn-label"><?= $editing ? 'Save changes' : 'Create obligation' ?></span></button>
    <a class="btn btn-outline-secondary" href="<?= APP_URL ?>/index.php?page=<?= $editing ? 'obligation_view&id=' . $oid : 'obligations' ?>">Cancel</a></div>
</form>
<?php require __DIR__ . '/../components/app_shell_end.php'; ?>
