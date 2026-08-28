<?php
require_once __DIR__ . '/_admin_auth.php';
require_once __DIR__ . '/../../includes/work_tracker.php';

$jobId = (int)($_POST['job_id'] ?? 0);
$job = wt_job($pdo, $jobId);

$allowed = ['full_transparency','important_only','daily_only','none'];
$mode = $_POST['customer_update_mode'] ?? 'full_transparency';

if (!in_array($mode, $allowed, true)) {
    $mode = 'full_transparency';
}

$stmt = $pdo->prepare("UPDATE work_jobs SET customer_update_mode=? WHERE id=?");
$stmt->execute([$mode, $jobId]);

header("Location: ../../admin/work/job.php?id=".$jobId."&update_mode_saved=1");
exit;
