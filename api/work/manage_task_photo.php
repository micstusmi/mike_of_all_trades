<?php
declare(strict_types=1);

require_once __DIR__ . '/_admin_auth.php';
require_once __DIR__ . '/../../includes/work_tracker.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('POST required.');
}

$jobId = (int)($_POST['job_id'] ?? 0);
$photoId = (int)($_POST['photo_id'] ?? 0);
$action = (string)($_POST['action'] ?? '');

wt_job($pdo, $jobId);
$q = $pdo->prepare('SELECT id FROM work_task_photos WHERE id=? AND job_id=? LIMIT 1');
$q->execute([$photoId, $jobId]);
if (!$q->fetchColumn()) exit('Photo not found for this job.');

if ($action === 'update_stage') {
    $type = (string)($_POST['photo_type'] ?? 'progress');
    if (!in_array($type, ['before', 'progress', 'after'], true)) exit('Invalid photo stage.');
    $taskId = (int)($_POST['task_id'] ?? 0);
    $taskCheck = $pdo->prepare("SELECT id FROM work_tasks WHERE id=? AND job_id=? AND status<>'cancelled' LIMIT 1");
    $taskCheck->execute([$taskId, $jobId]);
    if (!$taskCheck->fetchColumn()) exit('Choose a valid task for this job.');
    $pdo->prepare('UPDATE work_task_photos SET task_id=?,photo_type=?,assignment_method=? WHERE id=? AND job_id=?')
        ->execute([$taskId, $type, 'manual_task_and_stage_correction', $photoId, $jobId]);
    header('Location: ../../admin/work/task_photos.php?id=' . $jobId . '&photo_updated=1');
    exit;
}

if ($action === 'delete') {
    $pdo->prepare('UPDATE work_task_photos SET file_deleted_at=COALESCE(file_deleted_at,NOW()),social_deleted_at=COALESCE(social_deleted_at,NOW()),assignment_method=? WHERE id=? AND job_id=?')
        ->execute(['manually_hidden_duplicate_or_error', $photoId, $jobId]);
    header('Location: ../../admin/work/task_photos.php?id=' . $jobId . '&photo_deleted=1');
    exit;
}

exit('Invalid photo action.');
