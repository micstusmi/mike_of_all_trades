<?php
require_once __DIR__ . '/_admin_auth.php';
require_once __DIR__ . '/../../includes/work_tracker.php';
$id=(int)($_POST['job_id']??0);
$stmt=$pdo->prepare("INSERT INTO work_workers(job_id,worker_name,hourly_rate) VALUES(?,?,?)");
$stmt->execute([$id,trim($_POST['worker_name']),$_POST['hourly_rate']]);
header("Location: ../../admin/work/job.php?id=$id");
