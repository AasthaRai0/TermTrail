<?php require __DIR__.'/src/bootstrap.php'; $u = require_login();
$q = fn(string $sql) => (int)(function() use ($sql,$u){ $s = db()->prepare($sql); $s->execute([$u['id']]); return $s->fetchColumn(); })();
$total   = $q('SELECT COUNT(*) FROM contracts WHERE user_id=? AND status<>"archived"');
$expiring= $q('SELECT COUNT(*) FROM contracts WHERE user_id=? AND status="active" AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)');
$pending = $q('SELECT COUNT(*) FROM obligations o JOIN contracts c ON c.id=o.contract_id WHERE c.user_id=? AND o.status IN("pending","in_progress")');
page_head('Dashboard'); ?>
<nav class="tt-nav p-3 d-flex justify-content-between"><strong>TermTrail AI</strong>
<form method="post" action="logout.php" class="m-0"><?= csrf_field() ?><span class="tt-muted me-3"><?= e($u['name']) ?></span><button class="btn btn-sm btn-outline-light">Log out</button></form></nav>
<main class="container py-4"><h1 class="h3 mb-4">Dashboard</h1>
<div class="row g-3">
<?php foreach (['Total contracts'=>$total,'Expiring in 30 days'=>$expiring,'Open obligations'=>$pending] as $l=>$n): ?>
<div class="col-md-4"><div class="tt-card"><div class="tt-muted"><?= e($l) ?></div><div class="display-6"><?= $n ?></div></div></div><?php endforeach; ?></div>
<?php if ($total === 0): ?><div class="tt-card mt-4 text-center"><p class="mb-1">No contracts yet.</p><p class="tt-muted mb-0">Contract upload arrives in the next build step.</p></div><?php endif; ?></main><?php page_foot();
