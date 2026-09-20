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

$cardLast4 = preg_replace('/\D+/', '', (string)($_POST['payment_card_last4'] ?? ''));
if ($cardLast4 !== '' && strlen($cardLast4) !== 4) {
    exit('Card last four digits must contain exactly four numbers.');
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

$financialTreatment =
    (string)($_POST['financial_treatment'] ?? 'charge_customer');

if (!in_array($financialTreatment, [
    'charge_customer',
    'included_in_price',
    'goodwill',
    'rectification',
], true)) {
    $financialTreatment = 'charge_customer';
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

$pdo->beginTransaction();

try {
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
            payment_card_last4=?,
            reimbursement_status=?,
            financial_treatment=?,
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
        $cardLast4 !== '' ? $cardLast4 : null,
        $reimb,
        $financialTreatment,
        $receiptGst,
        $receiptNumber !== '' ? $receiptNumber : null,
        $purchaseDate,
        $notes !== '' ? $notes : null,
        $mid,
        $jobId,
    ]);

    /*
     * Keep exactly one linked no-charge ledger row per material.
     */
    $deleteLinked = $pdo->prepare("
        DELETE FROM work_complimentary_items
        WHERE job_id=?
          AND note LIKE ?
    ");

    $deleteLinked->execute([
        $jobId,
        '[material:' . $mid . ']%'
    ]);

    if (in_array(
        $financialTreatment,
        ['goodwill','rectification'],
        true
    )) {
        $materialValue = (float)($actual ?? $est ?? 0.0);

        $noCharge = $pdo->prepare("
            INSERT INTO work_complimentary_items
            (
                job_id,
                item_type,
                description,
                estimated_value,
                note,
                no_charge_reason,
                labour_hours,
                labour_value,
                material_value,
                material_details,
                updated_at
            )
            VALUES
            (
                ?,
                'material',
                ?,
                ?,
                ?,
                ?,
                NULL,
                0,
                ?,
                ?,
                NOW()
            )
        ");

        $noCharge->execute([
            $jobId,
            $description,
            $materialValue,
            '[material:' . $mid . '] Automatically linked from materials ledger.',
            $financialTreatment,
            $materialValue,
            $description,
        ]);
    }

    $pdo->commit();

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    throw $e;
}

header(
    'Location: ../../admin/work/materials.php?id=' .
    $jobId .
    '&saved=1#material-' .
    $mid
);
exit;
