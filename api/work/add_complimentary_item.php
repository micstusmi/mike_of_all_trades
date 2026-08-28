<?php
require_once __DIR__ . '/_admin_auth.php';
require_once __DIR__ . '/../../includes/work_tracker.php';

$jobId = (int)($_POST['job_id'] ?? 0);
$job = wt_job($pdo, $jobId);

$allowed = ['labour','material','repair','improvement','other'];
$type = $_POST['item_type'] ?? 'other';
if (!in_array($type, $allowed, true)) $type = 'other';

$description = trim($_POST['description'] ?? '');
$value = (float)($_POST['estimated_value'] ?? 0);
$note = trim($_POST['note'] ?? '');

if ($description === '') {
    die('Please describe the complimentary item.');
}

$stmt = $pdo->prepare("
    INSERT INTO work_complimentary_items
    (job_id, item_type, description, estimated_value, note)
    VALUES (?,?,?,?,?)
");
$stmt->execute([
    $jobId, $type, $description, max(0, $value),
    $note !== '' ? $note : null
]);

header("Location: ../../admin/work/job.php?id=".$jobId."&free_added=1");
exit;
