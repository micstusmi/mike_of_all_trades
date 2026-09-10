<?php
require_once __DIR__ . '/_admin_auth.php';
require_once __DIR__ . '/../../includes/work_tracker.php';
$jobId=(int)($_POST['job_id']??0); $revisionId=(int)($_POST['revision_id']??0);
$job=wt_job($pdo,$jobId);
$q=$pdo->prepare("UPDATE work_job_request_revisions SET reviewed_at=NOW(),requires_review=0 WHERE id=? AND job_id=?");
$q->execute([$revisionId,$jobId]);
if(!empty($job['customer_phone'])){
  wt_send_sms($pdo,$jobId,(string)$job['customer_phone'],'Mike of All Trades: I have reviewed your latest job-list update. Your live job record remains available here: '.wt_public_url($job),'customer_request_reviewed');
}
header("Location: ../../admin/work/manage_job.php?id=$jobId&request_reviewed=1#customer-request");exit;
