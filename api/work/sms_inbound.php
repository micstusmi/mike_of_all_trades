<?php
require_once __DIR__ . '/../../includes/work_tracker.php';
$to=trim($_GET['to']??''); $from=trim($_GET['from']??''); $message=trim(urldecode($_GET['message']??''));
$norm=wt_normalise_phone($from);
$stmt=$pdo->prepare("SELECT id FROM work_jobs WHERE REPLACE(REPLACE(customer_phone,' ',''),'-','') LIKE ? ORDER BY updated_at DESC LIMIT 1");
$local='0'.substr($norm,-9); $stmt->execute(['%'.substr($local,-9)]);
$jobId=$stmt->fetchColumn(); $jobId=$jobId?(int)$jobId:null;
$ins=$pdo->prepare("INSERT INTO work_sms_messages(job_id,direction,phone,message,purpose,received_at,raw_payload) VALUES(?,'inbound',?,?,?,NOW(),?)");
$upper=strtoupper(trim($message)); $purpose=in_array($upper,['YES','NO','PAUSE','STOP WORK'],true)?strtolower(str_replace(' ','_',$upper)):'reply';
$ins->execute([$jobId,$from,$message,$purpose,json_encode($_GET)]);
if($jobId && in_array($upper,['PAUSE','STOP WORK'],true)){
    $pdo->prepare("UPDATE work_jobs SET status='paused' WHERE id=?")->execute([$jobId]);
}
http_response_code(200); echo "OK";
