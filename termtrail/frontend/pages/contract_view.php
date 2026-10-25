<?php
use TermTrail\helpers\Security as S;
$cid = (int)($_GET['id'] ?? 0);
$title = 'Contract'; $active = 'contracts'; $scripts = ['contract_view.js', 'analysis.js', 'copilot.js', 'versions.js'];
require __DIR__ . '/../components/app_shell_start.php';
?>
<div id="contractRoot" data-id="<?= $cid ?>">
  <div class="text-center text-muted py-5" id="loading">Loading contract…</div>
  <div id="content" class="d-none">
    <div class="page-head">
      <div><h1 class="h3 mb-1" id="cTitle"></h1><div id="cBadges" class="d-flex gap-2 flex-wrap"></div></div>
      <div class="d-flex gap-2 flex-wrap" id="cActions"></div>
    </div>
    <ul class="nav nav-tabs mb-3" id="cTabs" role="tablist">
      <li class="nav-item" role="presentation"><button class="nav-link active" id="tabOverviewBtn" data-bs-toggle="tab" data-bs-target="#tabOverview" type="button" role="tab" aria-controls="tabOverview" aria-selected="true">Overview</button></li>
      <li class="nav-item" role="presentation"><button class="nav-link" id="tabAnalysisBtn" data-bs-toggle="tab" data-bs-target="#tabAnalysis" type="button" role="tab" aria-controls="tabAnalysis" aria-selected="false">AI analysis <span class="badge rounded-pill bg-secondary d-none" id="analysisBadge"></span></button></li>
      <li class="nav-item" role="presentation"><button class="nav-link" id="tabVersionsBtn" data-bs-toggle="tab" data-bs-target="#tabVersions" type="button" role="tab" aria-controls="tabVersions" aria-selected="false">Versions</button></li>
      <li class="nav-item" role="presentation"><button class="nav-link" id="tabCopilotBtn" data-bs-toggle="tab" data-bs-target="#tabCopilot" type="button" role="tab" aria-controls="tabCopilot" aria-selected="false">Copilot</button></li>
    </ul>
    <div class="tab-content">
    <div class="tab-pane fade show active" id="tabOverview" role="tabpanel" aria-labelledby="tabOverviewBtn">
    <div class="row g-3">
      <div class="col-lg-7">
        <div class="glass p-4 mb-3"><h2 class="h6 mb-3">Details</h2><dl class="dl-grid mb-0" id="cDetails"></dl></div>
        <div class="glass p-4 mb-3"><div class="d-flex justify-content-between align-items-center mb-2"><h2 class="h6 mb-0">Obligations</h2><a class="btn btn-outline-secondary btn-sm d-none" id="addOb" href="#">Add obligation</a></div><ul class="list-clean" id="relObs"><li class="text-muted">Loading…</li></ul></div>
        <div class="glass p-4 mb-3"><div class="d-flex justify-content-between align-items-center mb-2"><h2 class="h6 mb-0">Payments</h2><a class="btn btn-outline-secondary btn-sm d-none" id="addPay" href="#">Add payment</a></div><ul class="list-clean" id="relPays"><li class="text-muted">Loading…</li></ul></div>
        <div class="glass p-4"><h2 class="h6 mb-3">Review notes</h2>
          <form id="noteForm" class="mb-3 d-none" data-api="contracts/notes" data-reset="1" data-no-redirect="1" novalidate>
            <input type="hidden" name="contract_id" value="<?= $cid ?>">
            <label class="form-label" for="note">Add a note</label>
            <textarea class="form-control mb-2" id="note" name="note" rows="2" maxlength="2000"></textarea>
            <div class="invalid-feedback" data-error-for="note"></div>
            <div class="alert alert-danger d-none" data-form-error></div>
            <button class="btn btn-primary btn-sm" type="submit"><span class="btn-label">Add note</span></button>
          </form>
          <ul class="list-clean" id="notesList"></ul></div>
      </div>
      <div class="col-lg-5">
        <div class="glass p-4"><h2 class="h6 mb-3">Document</h2><div id="docCard"></div></div>
      </div>
    </div>
    </div>
    <div class="tab-pane fade" id="tabAnalysis" role="tabpanel" aria-labelledby="tabAnalysisBtn"><div id="analysisRoot"><div class="text-muted py-4 text-center">Loading analysis…</div></div></div>
    <div class="tab-pane fade" id="tabVersions" role="tabpanel" aria-labelledby="tabVersionsBtn"><div id="versionsRoot"><div class="text-muted py-4 text-center">Loading versions…</div></div></div>
    <div class="tab-pane fade" id="tabCopilot" role="tabpanel" aria-labelledby="tabCopilotBtn"><div id="copilotRoot"></div></div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../components/app_shell_end.php'; ?>
