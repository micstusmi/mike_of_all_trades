<?php
require_once __DIR__ . '/../../includes/work_tracker.php';
$token=$_POST['token']??''; $job=wt_job_by_token($pdo,$token); $rid=(int)($_POST['report_id']??0);
$stmt=$pdo->prepare("UPDATE work_reports SET customer_acknowledged_at=NOW(),customer_comment=? WHERE id=? AND job_id=?");
$stmt->execute([trim($_POST['customer_comment']??''),$rid,$job['id']]);
header("Location: ../../work/job.php?t=".urlencode($token));
