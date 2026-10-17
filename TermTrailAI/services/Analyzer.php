<?php
/** Rule-based (heuristic) analysis. No ML model and no LLM is used here.
 * Risk score per pillar = sum of weights of matched patterns. 0 => no finding; 1-2 low; 3-4 medium; >=5 high.
 * Every finding's evidence is the real paragraph from the document. */
const CLAUSE_RULES=['Payment'=>'/\b(pay|payment|invoice|fee|fees|price|remit)/i','Termination'=>'/\bterminat/i','Renewal'=>'/\b(renew|auto-?renew)/i','Confidentiality'=>'/\bconfidential/i','Privacy'=>'/\b(personal data|privacy|data protection|GDPR)/i','Intellectual property'=>'/\b(intellectual property|copyright|patent|trademark|work for hire|ownership of)/i','Liability'=>'/\b(liabilit|damages|limitation of)/i','Indemnification'=>'/\bindemn/i','Dispute resolution'=>'/\b(arbitrat|governing law|jurisdiction|dispute)/i','Delivery and services'=>'/\b(deliver|services?|milestone|scope of work)/i'];
const RISK_RULES=['financial'=>[['/unlimited liability|liability\s+(shall\s+)?not\s+be\s+limited/i',3,'Liability appears uncapped.'],['/\bindemnif/i',2,'Indemnification obligation present.'],['/late (fee|charge)|interest at|penalt/i',2,'Late fees, interest or penalties mentioned.']],
'termination'=>[['/automatically\s+renew|auto-?renew/i',3,'Automatic renewal clause.'],['/early termination (fee|charge)|termination fee/i',3,'Early termination fee.'],['/may not terminate|non-?cancel+able|cannot be terminated/i',3,'Termination is restricted.'],['/exclusive/i',1,'Exclusivity wording.']],
'privacy'=>[['/third[- ]part(y|ies)[^.]{0,80}(personal data|data)|(personal data|data)[^.]{0,80}third[- ]part/i',2,'Data may involve third parties.'],['/without (prior )?notice[^.]{0,60}(data|access)/i',2,'Access/data use without notice.'],['/personal data/i',1,'Personal data is processed.']],
'ip'=>[['/assigns?\s+(all|any)[^.]{0,60}(rights|intellectual property)/i',3,'Broad IP assignment.'],['/work(s)?[- ]for[- ]hire/i',2,'Work-for-hire wording.'],['/perpetual[^.]{0,40}irrevocable|irrevocable[^.]{0,40}perpetual/i',2,'Perpetual, irrevocable licence.']]];
function an_paragraphs(string $t): array { return array_values(array_filter(array_map('trim',preg_split('/\R+/',$t)),fn($x)=>mb_strlen($x)>=25)); }
function an_classify(string $p): string { foreach(CLAUSE_RULES as $c=>$re) if(preg_match($re,$p)) return $c; return 'Other'; }
function an_level(int $s): string { return $s>=5?'high':($s>=3?'medium':'low'); }
function an_obligations(array $paras): array { $out=[];
 foreach($paras as $p) foreach(preg_split('/(?<=[.;])\s+/',$p) as $s){ if(!preg_match('/\b(shall|must|agrees? to|will pay|is required to)\b/i',$s)) continue;
  $party=preg_match('/^(The\s+)?(Customer|Client|Provider|Supplier|Vendor|Company|Licensor|Licensee|Landlord|Tenant)\b/i',$s,$m)?trim($m[0]):'';
  $due=null; if(preg_match('/\b(\d{4}-\d{2}-\d{2})\b/',$s,$d)&&valid_date($d[1])) $due=$d[1]; elseif(preg_match('/\b(January|February|March|April|May|June|July|August|September|October|November|December)\s+\d{1,2},\s+\d{4}\b/',$s,$d)&&($ts=strtotime($d[0]))) $due=date('Y-m-d',$ts);
  $trig=preg_match('/\bwithin\s+\w+\s*(\(\d+\)\s*)?(business\s+|calendar\s+)?days?\b[^.,;]*/i',$s,$t)?trim($t[0]):'';
  $out[]=['description'=>mb_substr($s,0,480),'party'=>$party,'due'=>$due,'trigger'=>mb_substr($trig,0,190),'source'=>mb_substr($s,0,1000)]; } return array_slice($out,0,60); }
function run_analysis(PDO $pdo,int $cid,array $ver): array { $paras=an_paragraphs($ver['extracted_text']); if(!$paras) throw new RuntimeException('No usable text in latest version.');
 $pdo->beginTransaction(); try{
 foreach(['risk_findings','clauses'] as $t) $pdo->prepare("DELETE FROM $t WHERE contract_id=?")->execute([$cid]);
 $pdo->prepare("DELETE FROM obligation_suggestions WHERE contract_id=? AND review_status='pending'")->execute([$cid]);
 $ic=$pdo->prepare("INSERT INTO clauses(contract_id,version_id,category,text_excerpt,method) VALUES(?,?,?,?,'heuristic')"); foreach($paras as $p) $ic->execute([$cid,$ver['id'],an_classify($p),mb_substr($p,0,2000)]);
 $worst=0; $rank=['low'=>1,'medium'=>2,'high'=>3]; $ir=$pdo->prepare('INSERT INTO risk_findings(contract_id,pillar,level,explanation,evidence) VALUES(?,?,?,?,?)');
 foreach(RISK_RULES as $pillar=>$rules){ $score=0;$why=[];$ev=[]; foreach($paras as $p) foreach($rules as [$re,$w,$msg]) if(preg_match($re,$p)){ $score+=$w; $why[$msg]=1; $ev[mb_substr($p,0,600)]=1; }
  if($score>0){ $lv=an_level($score); $worst=max($worst,$rank[$lv]); $ir->execute([$cid,$pillar,$lv,'Score '.$score.': '.implode(' ',array_keys($why)).' (keyword heuristic; not a legal determination)',implode("\n---\n",array_slice(array_keys($ev),0,4))]); } }
 $is=$pdo->prepare("INSERT INTO obligation_suggestions(contract_id,description,responsible_party,due_date,trigger_text,source_text,extraction_method) VALUES(?,?,?,?,?,?,'rule-based heuristic')"); $obs=an_obligations($paras); foreach($obs as $o) $is->execute([$cid,$o['description'],$o['party'],$o['due'],$o['trigger'],$o['source']]);
 $lvl=['low','low','medium','high'][$worst]; $pdo->prepare("UPDATE contracts SET risk_level=? WHERE id=?")->execute([$worst?$lvl:'low',$cid]);
 $pdo->prepare("INSERT INTO analysis_results(contract_id,version_id,provider,summary_json,status) VALUES(?,?,'rule-based heuristic',?,'success')")->execute([$cid,$ver['id'],json_encode(['clauses'=>count($paras),'suggestions'=>count($obs)])]);
 $pdo->commit(); return [count($paras),count($obs)]; }catch(Throwable $e){ $pdo->rollBack(); throw $e; } }
