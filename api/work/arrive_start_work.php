<?php
require_once __DIR__ . '/_admin_auth.php';
require_once __DIR__ . '/../../includes/work_tracker.php';
$id=(int)($_POST['job_id']??0); $job=wt_job($pdo,$id);
$notes=trim($_POST['notes']??'Starting on-site work');
$pdo->beginTransaction();
try{
 $q=$pdo->prepare("SELECT id FROM work_sessions WHERE job_id=? AND worker_id IS NULL AND ended_at IS NULL AND travel_type='to_customer' ORDER BY id DESC LIMIT 1 FOR UPDATE");$q->execute([$id]);$travelId=(int)$q->fetchColumn();
 if(!$travelId) throw new RuntimeException('No active On My Way travel session found.');
 $pdo->prepare("UPDATE work_sessions SET ended_at=NOW(),stop_reason='completed_activity',stop_note='Arrived at customer premises' WHERE id=?")->execute([$travelId]);
 $pdo->prepare("INSERT INTO work_sessions (job_id,session_source,worker_id,started_at,category,start_location,location_detail,billable,notes) VALUES (?,'live',NULL,NOW(),'onsite','onsite',?,1,?)")->execute([$id,$job['job_address'],$notes]);
 $pdo->prepare("UPDATE work_jobs SET status='active' WHERE id=?")->execute([$id]);
 $pdo->commit();
}catch(Throwable $e){$pdo->rollBack();die($e->getMessage());}
$mode=$job['customer_update_mode']??'full_transparency';
if($mode==='full_transparency'&&!empty($job['customer_phone'])){
 $msg="Mike of All Trades — Job update\n📍 Mike has arrived at your premises and the travel session has ended. On-site work has now started.\nLive job record: ".wt_public_url($job);
 wt_send_sms($pdo,$id,$job['customer_phone'],$msg,'arrived_started');
}
header("Location: ../../admin/work/job.php?id=$id&arrived=1");exit;
