<?php
use TermTrail\helpers\Security as S;
$title = 'Reports'; $active = 'reports'; $scripts = ['reports.js'];
require __DIR__ . '/../components/app_shell_start.php';
?>
<div class="page-head"><div><h1 class="h3 mb-1">Reports</h1><p class="text-muted mb-0">Preview, then export to CSV or PDF. Reports only include records you are allowed to see.</p></div></div>
<div class="glass p-3 mb-3"><form id="repForm" autocomplete="off"><div class="row g-2 align-items-end">
  <div class="col-md-4"><label class="form-label" for="r_type">Report</label><select class="form-select" id="r_type" name="type"></select></div>
  <div class="col-12 col-md-8"><div class="row g-2" id="r_filters"></div></div>
</div>
<div class="d-flex gap-2 flex-wrap mt-3">
  <button class="btn btn-primary" type="submit" id="r_preview"><span class="btn-label">Preview</span></button>
  <a class="btn btn-outline-secondary" id="r_csv" href="#" download>Download CSV</a>
  <a class="btn btn-outline-secondary" id="r_pdf" href="#" download>Download PDF</a>
</div></form></div>
<div class="glass p-2 p-md-3"><div id="r_meta" class="small text-muted mb-2" role="status">Choose a report and click Preview.</div>
  <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead id="r_head"></thead><tbody id="r_body"></tbody></table></div></div>
<p class="small text-muted mt-3 mb-0">Informational reports generated from TermTrail AI records. AI-generated findings are suggestions that require human review. Not legal advice.</p>
<?php require __DIR__ . '/../components/app_shell_end.php'; ?>
