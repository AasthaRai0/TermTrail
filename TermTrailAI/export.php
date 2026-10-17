<?php require 'config/bootstrap.php'; require_login(); $t=$_GET['t']??'';
$q=['contracts'=>['SELECT title,contract_type,parties,status,risk_level,effective_date,expiry_date FROM contracts WHERE user_id=?'],
'obligations'=>['SELECT c.title contract,o.description,o.responsible_party,o.due_date,o.priority,o.status FROM obligations o JOIN contracts c ON c.id=o.contract_id WHERE c.user_id=?'],
'payments'=>['SELECT c.title contract,p.currency,p.expected_amount,p.paid_amount,p.due_date,p.paid_date,p.status FROM payments p JOIN contracts c ON c.id=p.contract_id WHERE c.user_id=?']];
if(!isset($q[$t])){http_response_code(404);exit('Unknown export.');}
$s=db()->prepare($q[$t][0]);$s->execute([uid()]);$rows=$s->fetchAll();
header('Content-Type: text/csv; charset=utf-8'); header("Content-Disposition: attachment; filename=\"termtrail_$t.csv\""); $o=fopen('php://output','w'); fwrite($o,"\xEF\xBB\xBF");
$safe=fn($v)=>is_string($v)&&$v!==''&&strpbrk($v[0],'=+-@')!==false?"'".$v:$v;
if($rows) fputcsv($o,array_keys($rows[0])); foreach($rows as $r) fputcsv($o,array_map($safe,$r));
