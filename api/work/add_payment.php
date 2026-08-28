<?php
require_once __DIR__ . '/_admin_auth.php';
require_once __DIR__ . '/../../includes/work_tracker.php';
$id=(int)($_POST['job_id']??0);
$stmt=$pdo->prepare("INSERT INTO work_payments(job_id,amount,payment_type,method) VALUES(?,?,'progress',?)");
$stmt->execute([$id,$_POST['amount'],trim($_POST['method']??'')]);
header("Location: ../../admin/work/job.php?id=$id");
