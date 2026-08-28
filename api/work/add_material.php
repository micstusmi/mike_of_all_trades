<?php
require_once __DIR__ . '/_admin_auth.php';
require_once __DIR__ . '/../../includes/work_tracker.php';
$id=(int)($_POST['job_id']??0);
$stmt=$pdo->prepare("INSERT INTO work_materials(job_id,description,supplier,cost,paid_by) VALUES(?,?,?,?,?)");
$stmt->execute([$id,trim($_POST['description']),trim($_POST['supplier']??''),$_POST['cost'],$_POST['paid_by']]);
header("Location: ../../admin/work/job.php?id=$id");
