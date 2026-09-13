<?php
declare(strict_types=1);

require_once __DIR__ . '/_admin_auth.php';
require_once __DIR__ . '/../../includes/work_tracker.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('POST required');
}

$jobId = (int)($_POST['job_id'] ?? 0);
$mid = (int)($_POST['material_id'] ?? 0);

if ($jobId <= 0 || $mid <= 0) {
    exit('Invalid request');
}

wt_job($pdo, $jobId);

$taskId = (int)($_POST['task_id'] ?? 0);
if ($taskId <= 0) $taskId = null;

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
if (!in_array($source, [
    'supplier_purchase',
    'mike_vehicle_stock',
    'customer_supplied',
    'already_on_site',
    'other',
], true)) {
    $source = 'supplier_purchase';
}

$paid = (string)($_POST['paid_by'] ?? 'mike');
if (!in_array($paid, ['mike','customer','other'], true)) {
    $paid = 'mike';
}

$reimb = (string)($_POST['reimbursement_status'] ?? 'not_applicable');
if (!in_array($reimb, [
    'not_applicable',
    'reimbursement_due',
    'reimbursed',
    'no_reimbursement_due',
], true)) {
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
    UPDATE work_materials
    SET
        task_id=?,
        material_status=?,
        supplier=?,
        estimated_cost=?,
        actual_cost=?,
        cost=?,
        source_type=?,
        paid_by=?,
        reimbursement_status=?,
        receipt_gst_amount=?,
        receipt_number=?,
        purchase_date=?,
        notes=?,
        updated_at=NOW()
    WHERE id=? AND job_id=?
");

$st->execute([
    $taskId,
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
    $mid,
    $jobId,
]);

header(
    'Location: ../../admin/work/materials.php?id=' .
    $jobId .
    '&saved=1#material-' .
    $mid
);
exit;
