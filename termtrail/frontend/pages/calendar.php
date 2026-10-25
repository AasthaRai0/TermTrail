<?php
use TermTrail\helpers\Security as S;
$title = 'Calendar'; $active = 'calendar'; $scripts = ['calendar.js'];
require __DIR__ . '/../components/app_shell_start.php';
?>
<div class="page-head"><div><h1 class="h3 mb-1">Calendar</h1><p class="text-muted mb-0">Deadlines, payments, renewals and expiries. Click an event to open it.</p></div>
  <div class="btn-group" role="group" aria-label="View"><a class="btn btn-outline-secondary btn-sm" href="<?= APP_URL ?>/index.php?page=obligations">List</a><a class="btn btn-primary btn-sm disabled" aria-current="page">Calendar</a></div></div>
<div class="glass p-3 mb-3 cal-legend" id="legend">
  <?php foreach (['obligation' => 'Obligations', 'payment' => 'Payments', 'expiry' => 'Expiries', 'renewal' => 'Renewals', 'notice' => 'Notice deadlines'] as $k => $l): ?>
  <label class="legend-item"><input type="checkbox" class="form-check-input" data-type="<?= $k ?>" checked><span class="dot ev-dot-<?= $k ?>"></span><?= S::e($l) ?></label>
  <?php endforeach; ?>
</div>
<div class="glass p-3"><div id="calendar" aria-label="Calendar of deadlines"></div><noscript>The calendar needs JavaScript.</noscript></div>
<?php
$extra = '<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>';
echo $extra;
require __DIR__ . '/../components/app_shell_end.php'; ?>
