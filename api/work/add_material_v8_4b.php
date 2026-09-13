<?php
declare(strict_types=1);

require_once __DIR__ . '/_admin_auth.php';
require_once __DIR__ . '/../../includes/work_tracker.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('POST required');
}

$jobId = (int)($_POST['job_id'] ?? 0);
if ($jobId <= 0) exit('Invalid job');

wt_job($pdo, $jobId);

$taskId = (int)($_POST['task_id'] ?? 0);
if ($taskId <= 0) $taskId = null;

$description = trim((string)($_POST['description'] ?? ''));
if ($description === '') exit('Description required');

$status = (string)($_POST['material_status'] ?? 'mike_to_purchase');
$statuses = [
    'already_on_site',
    'customer_will_supply',
    'mike_to_purchase',
    'mike_has_it',
    'maybe_required',
    'not_required',
];
if (!in_array($status, $statuses, true)) {
    $status = 'mike_to_purchase';
}

$supplier = trim((string)($_POST['supplier'] ?? ''));

$est = ($_POST['estimated_cost'] ?? '') !== ''
    ? (float)$_POST['estimated_cost']
    : null;

$actual = ($_POST['actual_cost'] ?? '') !== ''
    ? (float)$_POST['actual_cost']
    : null;

$source = (string)($_POST['source_type'] ?? 'supplier_purchase');
$sources = [
    'supplier_purchase',
    'mike_vehicle_stock',
    'customer_supplied',
    'already_on_site',
    'other',
];
if (!in_array($source, $sources, true)) {
    $source = 'supplier_purchase';
}

$paid = (string)($_POST['paid_by'] ?? 'mike');
if (!in_array($paid, ['mike','customer','other'], true)) {
    $paid = 'mike';
}

$reimb = (string)($_POST['reimbursement_status'] ?? 'not_applicable');
$reimbs = [
    'not_applicable',
    'reimbursement_due',
    'reimbursed',
    'no_reimbursement_due',
];
if (!in_array($reimb, $reimbs, true)) {
    $reimb = 'not_applicable';
}

$receiptGst = ($_POST['receipt_gst_amount'] ?? '') !== ''
    ? (float)$_POST['receipt_gst_amount']
    : null;

$receiptNumber = trim((string)($_POST['receipt_number'] ?? ''));

$purchaseDate = trim((string)($_POST['purchase_date'] ?? ''));
if ($purchaseDate !== '') {
    $dt = DateTimeImmutable::createFromFormat('Y-m-d', $purchaseDate);
    if (!$dt || $dt->format('Y-m-d') !== $purchaseDate) {
        exit('Invalid purchase date.');
    }
} else {
    $purchaseDate = null;
}

$notes = trim((string)($_POST['notes'] ?? ''));

$legacyCost = $actual ?? 0.0;

$st = $pdo->prepare("
    INSERT INTO work_materials
    (
        job_id,
        task_id,
        description,
        material_status,
        supplier,
        estimated_cost,
        actual_cost,
        cost,
        source_type,
        paid_by,
        reimbursement_status,
        receipt_gst_amount,
        receipt_number,
        purchase_date,
        notes,
        purchased_at,
        updated_at
    )
    VALUES
    (
        ?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW()
    )
");

$st->execute([
    $jobId,
    $taskId,
    $description,
    $status,
    $supplier !== '' ? $supplier : null,
    $est,
    $actual,
    $legacyCost,
    $source,
    $paid,
    $reimb,
    $receiptGst,
    $receiptNumber !== '' ? $receiptNumber : null,
    $purchaseDate,
    $notes !== '' ? $notes : null,
]);

header(
    'Location: ../../admin/work/materials.php?id=' .
    $jobId .
    '&added=1'
);
exit;
