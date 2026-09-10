<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth_admin.php';
require_once __DIR__ . '/../../includes/work_tracker.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('POST required');
}

$jobId = (int)($_POST['job_id'] ?? 0);
if ($jobId <= 0) {
    http_response_code(400);
    exit('Invalid job');
}

$hourlyRaw = trim((string)($_POST['agreed_hourly_rate'] ?? ''));
$limitRaw  = trim((string)($_POST['unpaid_balance_limit'] ?? ''));
$paymentMode = trim((string)($_POST['payment_mode'] ?? 'completion'));

$allowedModes = ['daily', 'balance_limit', 'completion', 'milestone'];
if (!in_array($paymentMode, $allowedModes, true)) {
    http_response_code(400);
    exit('Invalid payment mode');
}

$hourlyRate = null;
if ($hourlyRaw !== '') {
    if (!is_numeric($hourlyRaw) || (float)$hourlyRaw < 0) {
        http_response_code(400);
        exit('Invalid hourly rate');
    }
    $hourlyRate = round((float)$hourlyRaw, 2);
}

$unpaidLimit = null;
if ($limitRaw !== '') {
    if (!is_numeric($limitRaw) || (float)$limitRaw < 0) {
        http_response_code(400);
        exit('Invalid unpaid balance limit');
    }
    $unpaidLimit = round((float)$limitRaw, 2);
}

$stmt = $pdo->prepare("
    UPDATE work_jobs
    SET agreed_hourly_rate = ?,
        payment_mode = ?,
        unpaid_balance_limit = ?
    WHERE id = ?
");
$stmt->execute([
    $hourlyRate,
    $paymentMode,
    $unpaidLimit,
    $jobId
]);

header('Location: ../../admin/work/manage_job.php?id=' . $jobId . '&pricing_saved=1');
exit;
