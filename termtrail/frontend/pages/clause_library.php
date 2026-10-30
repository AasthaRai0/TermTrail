<?php
use TermTrail\helpers\Security as S;
$title = 'Clause library'; $active = 'clause_library'; $scripts = ['clause_library.js'];
$canWrite = in_array($user['role'], ['admin', 'manager'], true);
require __DIR__ . '/../components/app_shell_start.php';
?>
<div class="page-head">
  <div><h1 class="h3 mb-1">Clause library</h1><p class="text-muted mb-0">Shared reference clauses with plain-English explanations.</p></div>
  <?php if ($canWrite): ?><button class="btn btn-primary" id="addBtn"><i data-lucide="plus" class="me-1" style="width:16px;height:16px"></i>Add template</button><?php endif; ?>
</div>
<div class="glass p-3 mb-3"><div class="row g-2 align-items-end">
  <div class="col-md-5"><label class="form-label" for="tq">Search</label><input class="form-control" id="tq" placeholder="Name, clause text, tags"></div>
  <div class="col-md-4"><label class="form-label" for="tcat">Category</label><select class="form-select" id="tcat"><option value="">All categories</option></select></div>
  <div class="col-md-3"><div class="form-check mt-md-4"><input class="form-check-input" type="checkbox" id="tarch"><label class="form-check-label" for="tarch">Show archived</label></div></div>
</div></div>
<div class="row g-3" id="cards"><div class="col-12 text-center text-muted py-4">Loading…</div></div>
<div id="pager" class="mt-3"></div>
<?php if ($canWrite): ?>
<div class="modal fade" id="tplModal" tabindex="-1" aria-labelledby="tplTitle" aria-hidden="true"><div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content glass">
  <form id="tplForm" data-api="templates/create" data-no-redirect="1" novalidate>
    <div class="modal-header"><h2 class="modal-title h5" id="tplTitle">Add template</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <div class="modal-body"><input type="hidden" name="id" value="">
      <div class="row g-3"><div class="col-md-7"><label class="form-label" for="t_name">Name *</label><input class="form-control" id="t_name" name="name" maxlength="190"><div class="invalid-feedback" data-error-for="name"></div></div>
        <div class="col-md-5"><label class="form-label" for="t_cat">Category *</label><select class="form-select" id="t_cat" name="category"></select><div class="invalid-feedback" data-error-for="category"></div></div>
        <div class="col-12"><label class="form-label" for="t_text">Clause text *</label><textarea class="form-control" id="t_text" name="clause_text" rows="5" maxlength="20000"></textarea><div class="invalid-feedback" data-error-for="clause_text"></div></div>
        <div class="col-12"><label class="form-label" for="t_plain">Plain-English explanation</label><textarea class="form-control" id="t_plain" name="plain_explanation" rows="2" maxlength="5000"></textarea></div>
        <div class="col-md-6"><label class="form-label" for="t_tags">Tags</label><input class="form-control" id="t_tags" name="tags" maxlength="255" placeholder="comma separated"></div>
        <div class="col-md-6"><label class="form-label" for="t_notes">Notes</label><input class="form-control" id="t_notes" name="notes" maxlength="2000"></div></div>
      <div class="alert alert-danger d-none mt-3" data-form-error></div></div>
    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit"><span class="btn-label">Save</span></button></div>
  </form></div></div></div>
<?php endif; ?>
<?php require __DIR__ . '/../components/app_shell_end.php'; ?>
