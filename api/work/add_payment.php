<?php
declare(strict_types=1);

require_once __DIR__ . '/_admin_auth.php';
require_once __DIR__ . '/../../includes/work_tracker.php';

$jobId = (int)($_POST['job_id'] ?? 0);
wt_job($pdo, $jobId);

$amount = (float)($_POST['amount'] ?? 0);

if ($amount <= 0) {
    exit('Payment amount must be greater than zero.');
}

$paymentType = (string)($_POST['payment_type'] ?? 'progress');

$allowedTypes = [
    'progress',
    'final',
    'deposit',
    'other',
];

if (!in_array($paymentType, $allowedTypes, true)) {
    $paymentType = 'progress';
}

$method = trim((string)($_POST['method'] ?? ''));
$sourceReference = trim((string)($_POST['source_reference'] ?? ''));
$notes = trim((string)($_POST['notes'] ?? ''));

$paidAt = trim((string)($_POST['paid_at'] ?? ''));

if ($paidAt !== '') {
    $dt = DateTimeImmutable::createFromFormat(
        'Y-m-d\TH:i',
        $paidAt
    );

    if (!$dt) {
        exit('Invalid payment date/time.');
    }

    $paidAtSql = $dt->format('Y-m-d H:i:s');
} else {
    $paidAtSql = date('Y-m-d H:i:s');
}

$stmt = $pdo->prepare("
    INSERT INTO work_payments
    (
        job_id,
        amount,
        payment_type,
        method,
        source_reference,
        notes,
        paid_at
    )
    VALUES
    (
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?
    )
");

$stmt->execute([
    $jobId,
    $amount,
    $paymentType,
    $method !== '' ? $method : null,
    $sourceReference !== '' ? $sourceReference : null,
    $notes !== '' ? $notes : null,
    $paidAtSql,
]);

header(
    'Location: ../../admin/work/manage_job.php?id=' .
    $jobId .
    '&payment_saved=1#payments'
);
exit;
