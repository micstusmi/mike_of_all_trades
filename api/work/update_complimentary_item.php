<?php
declare(strict_types=1);

require_once __DIR__ . '/_admin_auth.php';
require_once __DIR__ . '/../../includes/work_tracker.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('POST required.');
}

$jobId = (int)($_POST['job_id'] ?? 0);
$itemId = (int)($_POST['item_id'] ?? 0);

if ($jobId <= 0 || $itemId <= 0) {
    http_response_code(400);
    exit('Invalid job or complimentary item.');
}

$itemType = trim((string)($_POST['item_type'] ?? 'other'));

$allowedTypes = [
    'labour',
    'material',
    'repair',
    'improvement',
    'other',
];

if (!in_array($itemType, $allowedTypes, true)) {
    $itemType = 'other';
}

$description = trim((string)($_POST['description'] ?? ''));
$note = trim((string)($_POST['note'] ?? ''));

$valueRaw = trim((string)($_POST['estimated_value'] ?? '0'));
$estimatedValue = is_numeric($valueRaw)
    ? max(0, (float)$valueRaw)
    : 0.0;

if ($description === '') {
    http_response_code(400);
    exit('Description is required.');
}

/*
 * job_id is included in the WHERE clause deliberately:
 * an item can only be edited from the job that owns it.
 */
$stmt = $pdo->prepare("
    UPDATE work_complimentary_items
    SET
        item_type = ?,
        description = ?,
        estimated_value = ?,
        note = ?
    WHERE id = ?
      AND job_id = ?
");

$stmt->execute([
    $itemType,
    $description,
    $estimatedValue,
    $note !== '' ? $note : null,
    $itemId,
    $jobId,
]);

header(
    'Location: ../../admin/work/manage_job.php?id=' .
    $jobId .
    '&free_updated=1#complimentary-' .
    $itemId
);

exit;
