<?php
declare(strict_types=1);

require_once __DIR__ . '/_admin_auth.php';
require_once __DIR__ . '/../../includes/work_tracker.php';
require_once __DIR__ . '/../../includes/work_photo_queue.php';

header('Content-Type: application/json; charset=utf-8');
$jobId=(int)($_GET['job_id']??0);
$batchToken=preg_replace('/[^a-zA-Z0-9_-]/','',(string)($_GET['batch_token']??''))??'';
if ($jobId<=0) { http_response_code(400); echo json_encode(['ok'=>false,'message'=>'Invalid job.']); exit; }
wt_job($pdo,$jobId);
$summary=wt_photo_queue_summary($pdo,$jobId,$batchToken?:null);
$stmt=$pdo->prepare("SELECT id,original_name,status,error_message,photo_id FROM work_photo_upload_queue WHERE job_id=?".($batchToken!==''?' AND batch_token=?':'')." ORDER BY id");
$params=[$jobId]; if ($batchToken!=='') $params[]=$batchToken;
$stmt->execute($params);
$worker=['started'=>false];
if ($summary['queued']>0 && $summary['processing']===0) $worker=wt_photo_queue_start_worker();
echo json_encode(['ok'=>true,'summary'=>$summary,'items'=>$stmt->fetchAll(PDO::FETCH_ASSOC),'worker_started'=>(bool)($worker['started']??false)]);
