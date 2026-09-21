<?php
declare(strict_types=1);

require_once __DIR__ . '/_admin_auth.php';
require_once __DIR__ . '/../../includes/work_tracker.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('POST required.');
}

$jobId = (int)($_POST['job_id'] ?? 0);

if ($jobId <= 0) {
    http_response_code(400);
    exit('Invalid job.');
}

$reason = trim((string)($_POST['no_charge_reason'] ?? 'unclassified'));

$allowedReasons = [
    'unclassified',
    'goodwill',
    'rectification',
    'other',
];

if (!in_array($reason, $allowedReasons, true)) {
    $reason = 'unclassified';
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
$materialDetails = trim((string)($_POST['material_details'] ?? ''));

$hoursRaw = trim((string)($_POST['labour_hours'] ?? ''));
$labourRaw = trim((string)($_POST['labour_value'] ?? ''));
$materialRaw = trim((string)($_POST['material_value'] ?? ''));

$labourHours =
    $hoursRaw !== '' && is_numeric($hoursRaw)
        ? max(0, (float)$hoursRaw)
        : null;

$labourValue =
    $labourRaw !== '' && is_numeric($labourRaw)
        ? max(0, (float)$labourRaw)
        : 0.0;

$materialValue =
    $materialRaw !== '' && is_numeric($materialRaw)
        ? max(0, (float)$materialRaw)
        : 0.0;

if ($description === '') {
    http_response_code(400);
    exit('Description is required.');
}

$totalValue = $labourValue + $materialValue;

$stmt = $pdo->prepare("
    INSERT INTO work_complimentary_items
    (
        job_id,
        item_type,
        no_charge_reason,
        labour_hours,
        labour_value,
        material_value,
        material_details,
        description,
        estimated_value,
        note,
        updated_at
    )
    VALUES
    (
        ?,?,?,?,?,?,?,?,?,?,NOW()
    )
");

$stmt->execute([
    $jobId,
    $itemType,
    $reason,
    $labourHours,
    $labourValue,
    $materialValue,
    $materialDetails !== '' ? $materialDetails : null,
    $description,
    $totalValue,
    $note !== '' ? $note : null,
]);

$returnTo = (string)($_POST['return_to'] ?? '');

if ($returnTo === 'invoice_preparation') {
    header(
        'Location: ../../admin/work/invoice_preparation.php?id=' .
        $jobId .
        '&free_added=1#free-materials'
    );
} else {
    header(
        'Location: ../../admin/work/manage_job.php?id=' .
        $jobId .
        '&free_added=1#no-charge-work'
    );
}

exit;
