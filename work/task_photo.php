<?php
require_once __DIR__ . '/../includes/work_tracker.php';
$id=(int)($_GET['id']??0);$token=(string)($_GET['t']??'');if($id<=0||$token===''){http_response_code(404);exit;}
try{$job=wt_job_by_token($pdo,$token);}catch(Throwable $e){http_response_code(404);exit;}
$q=$pdo->prepare("SELECT p.* FROM work_task_photos p JOIN work_tasks t ON t.id=p.task_id WHERE p.id=? AND p.job_id=? AND t.customer_visible=1 LIMIT 1");$q->execute([$id,$job['id']]);$p=$q->fetch(PDO::FETCH_ASSOC);if(!$p){http_response_code(404);exit;}
$base=wt_env('WORKTRACKER_PRIVATE_UPLOAD_DIR',dirname(__DIR__).'/storage/private/job_intake');$path=dirname(rtrim($base,'/')).'/task_photos/'.ltrim($p['relative_path'],'/');if(!is_file($path)){http_response_code(404);exit;}
header('Content-Type: '.$p['mime_type']);header('Cache-Control: private, max-age=300');header('X-Content-Type-Options: nosniff');readfile($path);
