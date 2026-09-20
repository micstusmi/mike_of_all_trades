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
    $pdo->prepare('UPDATE work_task_photos SET photo_type=?,assignment_method=? WHERE id=? AND job_id=?')
        ->execute([$type, 'manual_stage_correction', $photoId, $jobId]);
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
