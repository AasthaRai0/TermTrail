<?php require 'config/bootstrap.php'; require_login(); $d=db(); $u=uid();
function one($q,$a){global $d;$s=$d->prepare($q);$s->execute($a);return $s->fetchColumn();}
$st=[['Total contracts',one("SELECT COUNT(*) FROM contracts WHERE user_id=? AND status<>'archived'",[$u])],
['Under review',one("SELECT COUNT(*) FROM contracts WHERE user_id=? AND status='under_review'",[$u])],
['High risk (set manually)',one("SELECT COUNT(*) FROM contracts WHERE user_id=? AND risk_level='high'",[$u])],
['Pending obligations',one("SELECT COUNT(*) FROM obligations o JOIN contracts c ON c.id=o.contract_id WHERE c.user_id=? AND o.status IN('pending','in_progress')",[$u])],
['Overdue obligations',one("SELECT COUNT(*) FROM obligations o JOIN contracts c ON c.id=o.contract_id WHERE c.user_id=? AND o.status IN('pending','in_progress') AND o.due_date<CURDATE()",[$u])],
['Expiring in 30 days',one("SELECT COUNT(*) FROM contracts WHERE user_id=? AND status<>'archived' AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 30 DAY)",[$u])],
['Outstanding payments',one("SELECT COALESCE(SUM(p.expected_amount-COALESCE(p.paid_amount,0)),0) FROM payments p JOIN contracts c ON c.id=p.contract_id WHERE c.user_id=? AND p.status<>'paid'",[$u])]];
$rec=$d->prepare('SELECT id,title,status,created_at FROM contracts WHERE user_id=? ORDER BY id DESC LIMIT 5');$rec->execute([$u]);$rec=$rec->fetchAll();
$act=$d->prepare('SELECT action,detail,created_at FROM activity_logs WHERE user_id=? ORDER BY id DESC LIMIT 8');$act->execute([$u]);$act=$act->fetchAll();
page_head('Overview'); ?><h1 class="h3 mb-4">Welcome, <?=e($_SESSION['name'])?></h1>
<div class="row g-3 mb-4"><?php foreach($st as $s):?><div class="col-6 col-lg-3"><div class="cardx"><div class="lbl"><?=e($s[0])?></div><div class="stat"><?=e($s[1])?></div></div></div><?php endforeach;?></div>
<div class="row g-3"><div class="col-lg-6"><div class="cardx"><h2 class="h6">Recent contracts</h2><?php if(!$rec):?><div class="empty">No contracts yet.<br><a class="btn btn-primary btn-sm mt-2" href="contract_new.php">Add your first contract</a></div><?php else:?><ul class="list-unstyled mb-0"><?php foreach($rec as $r):?><li class="py-1"><a href="contract.php?id=<?=$r['id']?>"><?=e($r['title'])?></a> <span class="text-muted">· <?=e($r['status'])?></span></li><?php endforeach;?></ul><?php endif;?></div></div>
<div class="col-lg-6"><div class="cardx"><h2 class="h6">Recent activity</h2><?php if(!$act):?><div class="empty">No activity yet.</div><?php else:?><ul class="list-unstyled mb-0"><?php foreach($act as $a):?><li class="py-1"><?=e($a['action'])?> <span class="text-muted"><?=e($a['detail'])?> · <?=e($a['created_at'])?></span></li><?php endforeach;?></ul><?php endif;?></div></div></div><?php page_foot();
