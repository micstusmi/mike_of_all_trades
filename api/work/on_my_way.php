<?php
require_once __DIR__ . '/_admin_auth.php';
require_once __DIR__ . '/../../includes/work_tracker.php';
$id=(int)($_POST['job_id']??0); $job=wt_job($pdo,$id);
$eta=trim($_POST['eta']??''); $origin=trim($_POST['origin']??''); $notes=trim($_POST['notes']??'');
if($eta==='') die('Please enter an ETA.');
$dup=$pdo->prepare("SELECT id FROM work_sessions WHERE job_id=? AND worker_id IS NULL AND ended_at IS NULL LIMIT 1");$dup->execute([$id]);if($dup->fetchColumn()){header("Location: ../../admin/work/job.php?id=$id&duplicate=1");exit;}
$route=$origin!==''?$origin.' → '.$job['job_address']:'Travelling to customer premises';
$q=$pdo->prepare("INSERT INTO work_sessions (job_id,session_source,worker_id,started_at,category,start_location,location_detail,travel_type,travel_eta,billable,notes) VALUES (?,'live',NULL,NOW(),'travel','travel_job',?,'to_customer',?,1,?)");
$q->execute([$id,$route,$eta,$notes?:'Travel to customer premises']);
$pdo->prepare("UPDATE work_jobs SET status='active' WHERE id=?")->execute([$id]);
$mode=$job['customer_update_mode']??'full_transparency';
if(in_array($mode,['full_transparency','important_only'],true)&&!empty($job['customer_phone'])){
 $msg="Mike of All Trades — On my way\n🚗 Mike has left for your job.\nETA: ".$eta.".\nJob-related travel is now being recorded separately so your job history shows travel as well as on-site work.\nLive job record: ".wt_public_url($job);
 wt_send_sms($pdo,$id,$job['customer_phone'],$msg,'on_my_way');
}
header("Location: ../../admin/work/job.php?id=$id&onmyway=1");exit;
