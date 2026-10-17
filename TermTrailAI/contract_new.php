<?php require 'config/bootstrap.php'; require_login(); $err=[];
if($_SERVER['REQUEST_METHOD']==='POST'){ csrf_check(); $t=trim($_POST['title']??''); if($t==='')$err[]='Title is required.';
 $ed=valid_date($_POST['effective_date']??''); $xd=valid_date($_POST['expiry_date']??''); if(($_POST['effective_date']??'')&&!$ed)$err[]='Invalid effective date.'; if(($_POST['expiry_date']??'')&&!$xd)$err[]='Invalid expiry date.'; if($ed&&$xd&&$xd<$ed)$err[]='Expiry is before effective date.';
 $f=$_FILES['doc']??null; $has=$f&&$f['error']!==UPLOAD_ERR_NO_FILE; $ext='';
 if($has){ if($f['error']!==UPLOAD_ERR_OK)$err[]='Upload failed (code '.$f['error'].').'; else { $ext=strtolower(pathinfo($f['name'],PATHINFO_EXTENSION));
   if(!in_array($ext,['pdf','docx','txt'],true))$err[]='Only PDF, DOCX, TXT allowed.';
   elseif($f['size']>(int)env('MAX_UPLOAD_MB','10')*1048576)$err[]='File too large.';
   else { $head=file_get_contents($f['tmp_name'],false,null,0,4); $okm=($ext==='pdf'&&$head==='%PDF')||($ext==='docx'&&str_starts_with($head,'PK'))||($ext==='txt'&&!str_contains(file_get_contents($f['tmp_name'],false,null,0,2048),"\0")); if(!$okm)$err[]='File content does not match its extension.'; } } }
 if(!$err){ $pdo=db(); $pdo->beginTransaction(); try{
  $pdo->prepare('INSERT INTO contracts(user_id,title,contract_type,parties,description,effective_date,expiry_date,renewal_terms,notice_period_days,status) VALUES(?,?,?,?,?,?,?,?,?,?)')->execute([uid(),$t,trim($_POST['contract_type']??'Other'),trim($_POST['parties']??''),trim($_POST['description']??''),$ed,$xd,trim($_POST['renewal_terms']??''),($_POST['notice']??'')!==''?(int)$_POST['notice']:null,'draft']);
  $cid=(int)$pdo->lastInsertId();
  if($has){ $stored=bin2hex(random_bytes(20)); if(!move_uploaded_file($f['tmp_name'],ROOT.'/storage/'.$stored)) throw new RuntimeException('Could not store file'); [$tx,$meth,$stat,$er]=extract_text(ROOT.'/storage/'.$stored,$ext);
   $pdo->prepare('INSERT INTO contract_versions(contract_id,original_name,stored_name,sha256,mime,size_bytes,extracted_text,extraction_method,extraction_status,extraction_error,word_count) VALUES(?,?,?,?,?,?,?,?,?,?,?)')->execute([$cid,mb_substr(basename($f['name']),0,255),$stored,hash_file('sha256',ROOT.'/storage/'.$stored),$ext,$f['size'],$tx,$meth,$stat,$er,str_word_count($tx)]); }
  $pdo->commit(); log_act('contract_created',$cid,$t); flash('Contract created.'); header("Location: contract.php?id=$cid"); exit;
 }catch(Throwable $e){ $pdo->rollBack(); error_log($e->getMessage()); $err[]='Could not save contract.'; } } }
page_head('New contract'); ?><h1 class="h3 mb-3">New contract</h1><?php foreach($err as $x):?><div class="alert alert-danger"><?=e($x)?></div><?php endforeach;?>
<form method="post" enctype="multipart/form-data" class="cardx row g-3"><?=csrf_field()?>
<div class="col-md-8"><label class="form-label">Title *</label><input class="form-control" name="title" required value="<?=e($_POST['title']??'')?>"></div>
<div class="col-md-4"><label class="form-label">Type</label><select class="form-select" name="contract_type"><?php foreach(['Service','NDA','Employment','Lease','Vendor','Licence','Other'] as $x):?><option><?=$x?></option><?php endforeach;?></select></div>
<div class="col-12"><label class="form-label">Parties</label><input class="form-control" name="parties"></div>
<div class="col-md-4"><label class="form-label">Effective date</label><input class="form-control" type="date" name="effective_date"></div><div class="col-md-4"><label class="form-label">Expiry date</label><input class="form-control" type="date" name="expiry_date"></div><div class="col-md-4"><label class="form-label">Notice period (days)</label><input class="form-control" type="number" min="0" name="notice"></div>
<div class="col-12"><label class="form-label">Renewal terms</label><input class="form-control" name="renewal_terms"></div><div class="col-12"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="2"></textarea></div>
<div class="col-12"><label class="form-label">Document (PDF, DOCX, TXT, max <?=e(env('MAX_UPLOAD_MB','10'))?> MB)</label><input class="form-control" type="file" name="doc" accept=".pdf,.docx,.txt"></div>
<div class="col-12"><button class="btn btn-primary">Save contract</button></div></form><?php page_foot();
