<?php
use TermTrail\helpers\Security as S;
$cid = (int)($_GET['id'] ?? 0);
$title = 'Counterparty'; $active = 'counterparties'; $scripts = ['counterparty_view.js'];
require __DIR__ . '/../components/app_shell_start.php';
?>
<div id="cpRoot" data-id="<?= $cid ?>">
  <div class="text-center text-muted py-5" id="loading">Loading…</div>
  <div id="content" class="d-none">
    <div class="page-head"><div><h1 class="h3 mb-1" id="cpTitle"></h1><div id="cpBadges"></div></div>
      <a class="btn btn-outline-secondary" href="<?= APP_URL ?>/index.php?page=counterparties">Back to list</a></div>
    <div class="row g-3">
      <div class="col-lg-4"><div class="glass p-4"><h2 class="h6 mb-3">Details</h2><dl class="dl-grid mb-0" id="cpDetails"></dl></div></div>
      <div class="col-lg-8"><div class="glass p-3"><h2 class="h6 mb-3 px-1">Related contracts</h2>
        <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Title</th><th>Status</th><th>Risk</th><th>Expiry</th></tr></thead><tbody id="cpContracts"></tbody></table></div>
        <div id="pager" class="mt-3"></div></div></div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../components/app_shell_end.php'; ?>
