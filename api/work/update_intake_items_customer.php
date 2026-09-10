<?php
require_once __DIR__ . '/../../includes/work_tracker.php';
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit;}
$token=(string)($_POST['token']??'');try{$job=wt_job_by_token($pdo,$token);}catch(Throwable $e){http_response_code(404);die('Job not found');}
$jobId=(int)$job['id'];$action=(string)($_POST['action']??'save');$itemId=(int)($_POST['item_id']??0);$text=trim((string)($_POST['request_text']??''));
$before=(string)($job['customer_request_text']??'');
if($action==='remove'&&$itemId>0){$pdo->prepare("DELETE FROM work_job_intake_items WHERE id=? AND job_id=?")->execute([$itemId,$jobId]);}
elseif($action==='add'){if($text==='')die('Item required');$q=$pdo->prepare("SELECT COALESCE(MAX(item_order),0)+10 FROM work_job_intake_items WHERE job_id=?");$q->execute([$jobId]);$ord=(int)$q->fetchColumn();$pdo->prepare("INSERT INTO work_job_intake_items(job_id,item_order,request_text) VALUES(?,?,?)")->execute([$jobId,$ord,$text]);}
elseif($itemId>0){if($text==='')die('Item required');$pdo->prepare("UPDATE work_job_intake_items SET request_text=?,needs_clarification=0,clarification_reason=NULL WHERE id=? AND job_id=?")->execute([$text,$itemId,$jobId]);}
$q=$pdo->prepare("SELECT request_text FROM work_job_intake_items WHERE job_id=? ORDER BY item_order,id");$q->execute([$jobId]);$new=implode("\n",array_map(fn($r)=>$r['request_text'],$q->fetchAll(PDO::FETCH_ASSOC)));
$pdo->prepare("UPDATE work_jobs SET customer_request_text=?,customer_request_updated_at=NOW(),ai_breakdown_status='pending' WHERE id=?")->execute([$new,$jobId]);
$pdo->prepare("INSERT INTO work_job_request_revisions(job_id,source,previous_text,new_text,note,requires_review) VALUES(?,'customer',?,?,?,1)")->execute([$jobId,$before,$new,'Customer edited individual requested-work item']);
header('Location: ../../work/job.php?t='.urlencode($token).'&request_saved=1#customer-request');exit;
