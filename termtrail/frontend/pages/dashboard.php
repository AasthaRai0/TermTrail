<?php
use TermTrail\helpers\Security as S;
$title = 'Dashboard'; $active = 'dashboard';
require __DIR__ . '/../components/app_shell_start.php';
$links = [
  'total_contracts' => ['contracts', []], 'active_contracts' => ['contracts', ['status' => 'active']],
  'expiring_soon' => ['contracts', ['quick' => 'expiring']], 'expired_contracts' => ['contracts', ['quick' => 'expired']],
  'awaiting_review' => ['contracts', ['status' => 'under_review']], 'high_risk' => ['contracts', ['risk' => 'high']],
  'open_obligations' => ['obligations', ['status' => 'active']], 'overdue_obligations' => ['obligations', ['status' => 'overdue']],
  'upcoming_payments' => ['payments', ['status' => 'upcoming']],
];
$cards = [
  ['total_contracts','file-text','Total contracts'], ['active_contracts','badge-check','Active'],
  ['expiring_soon','hourglass','Expiring in 30 days'], ['expired_contracts','calendar-x','Expired'],
  ['awaiting_review','eye','Awaiting review'], ['high_risk','shield-alert','High risk'],
  ['open_obligations','list-checks','Open obligations'], ['overdue_obligations','alarm-clock','Overdue obligations'],
  ['upcoming_payments','wallet','Upcoming payments'],
];
?>
<div class="page-head"><div><h1 class="h3 mb-1">Dashboard</h1><p class="text-muted mb-0">Live overview of your contracts and commitments.</p></div></div>
<div class="d-flex gap-2 flex-wrap mb-3">
  <?php if (in_array($user['role'], ['admin','manager'], true)): ?>
  <a class="btn btn-primary btn-sm" href="<?= APP_URL ?>/index.php?page=contract_new">New contract</a>
  <a class="btn btn-outline-secondary btn-sm" href="<?= APP_URL ?>/index.php?page=obligation_new">New obligation</a>
  <a class="btn btn-outline-secondary btn-sm" href="<?= APP_URL ?>/index.php?page=payments&new=1">Add payment</a>
  <a class="btn btn-outline-secondary btn-sm" href="<?= APP_URL ?>/index.php?page=counterparties">Add counterparty</a>
  <?php endif; ?>
  <a class="btn btn-outline-secondary btn-sm" href="<?= APP_URL ?>/index.php?page=contracts">Browse contracts</a>
</div>
<div class="row g-3 mb-4" id="statCards">
  <?php foreach ($cards as [$k, $icon, $label]): ?>
  <?php $href = isset($links[$k]) ? APP_URL . '/index.php?' . http_build_query(['page' => $links[$k][0]] + $links[$k][1]) : null; ?>
  <div class="col-6 col-lg-4 col-xl-3"><<?= $href ? 'a href="' . S::e($href) . '"' : 'div' ?> class="stat-card glass"><i data-lucide="<?= $icon ?>"></i>
    <div><div class="stat-value" data-stat="<?= $k ?>">–</div><div class="stat-label"><?= S::e($label) ?></div></div></<?= $href ? 'a' : 'div' ?>></div>
  <?php endforeach; ?>
</div>
<div class="row g-3 mb-4">
  <div class="col-lg-4"><div class="glass p-3 h-100"><h2 class="h6">Contract status</h2><div class="chart-box"><canvas id="chartStatus"></canvas></div></div></div>
  <div class="col-lg-4"><div class="glass p-3 h-100"><h2 class="h6">Risk distribution</h2><div class="chart-box"><canvas id="chartRisk"></canvas></div></div></div>
  <div class="col-lg-4"><div class="glass p-3 h-100"><h2 class="h6">Contracts added (12 months)</h2><div class="chart-box"><canvas id="chartMonthly"></canvas></div></div></div>
</div>
<div class="row g-3">
  <div class="col-lg-7"><div class="glass p-3"><h2 class="h6">Recent contracts</h2>
    <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Title</th><th>Counterparty</th><th>Status</th><th>Expiry</th></tr></thead><tbody id="recentBody"></tbody></table></div></div></div>
  <div class="col-lg-5"><div class="glass p-3 mb-3"><h2 class="h6">Upcoming deadlines</h2><ul class="list-clean" id="deadlineList"></ul></div>
</div>
<?php
$scripts = ['dashboard.js'];
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<?php require __DIR__ . '/../components/app_shell_end.php'; ?>
