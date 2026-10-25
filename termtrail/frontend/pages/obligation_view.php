<?php
use TermTrail\helpers\Security as S;
$oid = (int)($_GET['id'] ?? 0);
$title = 'Obligation'; $active = 'obligations'; $scripts = ['obligation_view.js'];
require __DIR__ . '/../components/app_shell_start.php';
?>
<div id="obRoot" data-id="<?= $oid ?>">
  <div class="text-center text-muted py-5" id="loading">Loading obligation…</div>
  <div id="content" class="d-none">
    <div class="page-head"><div><h1 class="h3 mb-1" id="oTitle"></h1><div id="oBadges" class="d-flex gap-2 flex-wrap"></div></div><div class="d-flex gap-2 flex-wrap" id="oActions"></div></div>
    <div class="row g-3"><div class="col-lg-7"><div class="glass p-4"><h2 class="h6 mb-3">Details</h2><dl class="dl-grid mb-0" id="oDetails"></dl></div></div>
      <div class="col-lg-5"><div class="glass p-4"><h2 class="h6 mb-3">Source &amp; approval</h2><div id="oSource"></div></div></div></div>
  </div>
</div>
<div class="modal fade" id="statusModal" tabindex="-1" aria-labelledby="statusTitle" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content glass">
  <div class="modal-header"><h2 class="modal-title h5" id="statusTitle">Update status</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
  <div class="modal-body"><label class="form-label" for="statusNotes">Notes (optional)</label><textarea class="form-control" id="statusNotes" rows="3" maxlength="2000"></textarea></div>
  <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" id="statusOk">Confirm</button></div></div></div></div>
<?php require __DIR__ . '/../components/app_shell_end.php'; ?>
