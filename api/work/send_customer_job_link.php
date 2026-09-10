<?php
require_once __DIR__ . '/_admin_auth.php';
require_once __DIR__ . '/../../includes/work_tracker.php';
$jobId=(int)($_POST['job_id']??0);$job=wt_job($pdo,$jobId);
if(empty($job['customer_phone']))die('Customer phone number is missing.');
$msg='Mike of All Trades: Your job has been logged. You can review your requested work, update details and follow progress here: '.wt_public_url($job);
wt_send_sms($pdo,$jobId,(string)$job['customer_phone'],$msg,'job_logged_link');
header("Location: ../../admin/work/manage_job.php?id=$jobId&link_sent=1");exit;
