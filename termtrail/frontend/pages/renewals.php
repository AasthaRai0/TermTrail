<?php
use TermTrail\helpers\Security as S;
$title = 'Renewals'; $active = 'renewals'; $scripts = ['renewals.js'];
require __DIR__ . '/../components/app_shell_start.php';
?>
<div class="page-head"><div><h1 class="h3 mb-1">Renewals</h1><p class="text-muted mb-0">Renewal and expiry dates, notice deadlines and your decisions.</p></div></div>
<div class="glass p-3 mb-3"><div class="row g-2">
  <div class="col-6 col-md-3"><label class="form-label" for="r_days">Look ahead</label><select class="form-select" id="r_days"><option value="60">60 days</option><option value="90">90 days</option><option value="180" selected>180 days</option><option value="365">1 year</option></select></div>
  <div class="col-6 col-md-3"><label class="form-label" for="r_dec">Decision</label><select class="form-select" id="r_dec"><option value="">All</option><option value="pending">Pending</option><option value="renew">Renew</option><option value="renegotiate">Renegotiate</option><option value="not_renew">Do not renew</option></select></div>
</div></div>
<div class="glass p-2 p-md-3"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
  <thead><tr><th>Contract</th><th>Counterparty</th><th>Key date</th><th>Notice deadline</th><th style="min-width:170px">Decision</th></tr></thead>
  <tbody id="rows"><tr><td colspan="5" class="text-center text-muted py-4">Loading…</td></tr></tbody></table></div></div>
<?php require __DIR__ . '/../components/app_shell_end.php'; ?>
