<?php
/** Validates and stores an uploaded file as a new version. Returns '' on success or an error message. */
function store_upload(PDO $pdo,int $cid,array $f,string $label): string {
 if(($f['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK) return 'Upload failed or no file chosen.';
 $ext=strtolower(pathinfo($f['name'],PATHINFO_EXTENSION)); if(!in_array($ext,['pdf','docx','txt'],true)) return 'Only PDF, DOCX, TXT allowed.';
 if($f['size']>(int)env('MAX_UPLOAD_MB','10')*1048576) return 'File too large.';
 $h=file_get_contents($f['tmp_name'],false,null,0,4); $ok=($ext==='pdf'&&$h==='%PDF')||($ext==='docx'&&str_starts_with($h,'PK'))||($ext==='txt'&&!str_contains(file_get_contents($f['tmp_name'],false,null,0,2048),"\0")); if(!$ok) return 'File content does not match its extension.';
 $stored=bin2hex(random_bytes(20)); if(!move_uploaded_file($f['tmp_name'],ROOT.'/storage/'.$stored)) return 'Could not store file.';
 [$tx,$m,$st,$er]=extract_text(ROOT.'/storage/'.$stored,$ext);
 $pdo->prepare('INSERT INTO contract_versions(contract_id,version_label,original_name,stored_name,sha256,mime,size_bytes,extracted_text,extraction_method,extraction_status,extraction_error,word_count) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)')->execute([$cid,mb_substr($label,0,40),mb_substr(basename($f['name']),0,255),$stored,hash_file('sha256',ROOT.'/storage/'.$stored),$ext,$f['size'],$tx,$m,$st,$er,str_word_count($tx)]); return ''; }
