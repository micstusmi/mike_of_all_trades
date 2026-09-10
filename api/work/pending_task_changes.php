<?php
require_once __DIR__ . '/_admin_auth.php';
require_once __DIR__ . '/../../includes/work_tracker.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
$jobId=(int)($_GET['job_id']??0);
if($jobId<=0){http_response_code(400);echo json_encode(['error'=>'Invalid job ID']);exit;}
try{$job=wt_job($pdo,$jobId);}catch(Throwable $e){http_response_code(404);echo json_encode(['error'=>'Job not found']);exit;}

$q=$pdo->prepare("SELECT cr.id,cr.task_id,cr.request_type,cr.customer_message,cr.created_at,t.title AS task_title FROM work_task_change_requests cr JOIN work_tasks t ON t.id=cr.task_id WHERE cr.job_id=? AND cr.status='awaiting_review' ORDER BY cr.created_at DESC,cr.id DESC LIMIT 25");
$q->execute([$jobId]);
$rows=[];
foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r){
  $msg=preg_replace('/\s+/u',' ',trim((string)$r['customer_message'])); if(mb_strlen($msg)>220)$msg=mb_substr($msg,0,217).'...';
  $rows[]=['id'=>'task-'.(int)$r['id'],'numeric_id'=>(int)$r['id'],'kind'=>'task_change','task_id'=>(int)$r['task_id'],'request_type'=>$r['request_type'],'customer_message'=>$msg,'created_at'=>$r['created_at'],'task_title'=>$r['task_title'],'customer_name'=>(string)$job['customer_name'],'anchor'=>'task-'.(int)$r['task_id']];
}

$q=$pdo->prepare("SELECT id,new_text,created_at FROM work_job_request_revisions WHERE job_id=? AND source='customer' AND requires_review=1 AND reviewed_at IS NULL ORDER BY created_at DESC,id DESC LIMIT 25");
$q->execute([$jobId]);
foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r){
  $msg=preg_replace('/\s+/u',' ',trim((string)$r['new_text'])); if(mb_strlen($msg)>220)$msg=mb_substr($msg,0,217).'...';
  $rows[]=['id'=>'request-'.(int)$r['id'],'numeric_id'=>(int)$r['id'],'kind'=>'job_request_update','task_id'=>0,'request_type'=>'job_list','customer_message'=>$msg,'created_at'=>$r['created_at'],'task_title'=>'Requested work list updated','customer_name'=>(string)$job['customer_name'],'anchor'=>'customer-request'];
}
usort($rows,fn($a,$b)=>strcmp((string)$b['created_at'],(string)$a['created_at']));
echo json_encode(['count'=>count($rows),'pending'=>$rows],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
