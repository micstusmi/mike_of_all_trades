<?php
require_once __DIR__ . '/../../includes/work_tracker.php';
$token=trim((string)($_POST['token']??''));
if($token===''){http_response_code(400);die('Missing job token.');}
try{$job=wt_job_by_token($pdo,$token);}catch(Throwable $e){http_response_code(404);die('Job not found.');}
$new=trim((string)($_POST['customer_request_text']??''));
if($new===''){http_response_code(400);die('The requested-work list cannot be empty.');}
$old=(string)($job['customer_request_text']??$job['original_scope']??'');
if(hash_equals(hash('sha256',$old),hash('sha256',$new))){header('Location: ../../work/job.php?t='.urlencode($token).'&request_saved=1#customer-request');exit;}

$pdo->beginTransaction();
try{
  $q=$pdo->prepare("INSERT INTO work_job_request_revisions(job_id,source,previous_text,new_text,note,requires_review) VALUES(?,?,?,?,?,1)");
  $q->execute([(int)$job['id'],'customer',$old,$new,'Customer updated the requested-work list']);
  $revisionId=(int)$pdo->lastInsertId();
  $q=$pdo->prepare("UPDATE work_jobs SET customer_request_text=?,customer_request_updated_at=NOW(),ai_breakdown_status='pending',ai_breakdown_error=NULL WHERE id=?");
  $q->execute([$new,(int)$job['id']]);
  $pdo->commit();
} catch(Throwable $e){$pdo->rollBack();throw $e;}

$mikeMobile=wt_env('WORK_TRACKER_MIKE_MOBILE');
if($mikeMobile){
  $msg='Mike of All Trades: '.$job['customer_name'].' updated their requested-work list for Job #'.$job['id'].'. Review: '.wt_base_url().'/admin/work/manage_job.php?id='.$job['id'].'#customer-request';
  wt_send_sms($pdo,(int)$job['id'],$mikeMobile,$msg,'customer_request_updated');
}

header('Location: ../../work/job.php?t='.urlencode($token).'&request_saved=1#customer-request');exit;
