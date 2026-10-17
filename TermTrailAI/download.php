<?php require 'config/bootstrap.php'; require_login();
$s=db()->prepare('SELECT v.* FROM contract_versions v JOIN contracts c ON c.id=v.contract_id WHERE v.id=? AND c.user_id=?');$s->execute([(int)($_GET['id']??0),uid()]);$v=$s->fetch();
$f=$v?ROOT.'/storage/'.$v['stored_name']:''; if(!$v||!is_file($f)){http_response_code(404);exit('Not found.');}
$types=['pdf'=>'application/pdf','docx'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document','txt'=>'text/plain'];
header('Content-Type: '.($types[$v['mime']]??'application/octet-stream')); header('X-Content-Type-Options: nosniff'); header('Content-Length: '.filesize($f));
header('Content-Disposition: attachment; filename="'.preg_replace('/[^A-Za-z0-9._-]/','_',$v['original_name']).'"'); readfile($f);
