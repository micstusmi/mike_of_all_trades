<?php
declare(strict_types=1);

require_once __DIR__ . '/_admin_auth.php';
require_once __DIR__ . '/../../includes/work_tracker.php';

$id = (int)($_POST['job_id'] ?? 0);
$job = wt_job($pdo, $id);
$tot = wt_totals($pdo, $id);

$kind = $_POST['kind'] ?? 'general';

if ($kind === 'agreement') {
    $msg = "Mike of All Trades: please review the current job scope, costs and agreement to continue here: " . wt_public_url($job);
} elseif ($kind === 'progress_payment') {
    $msg = "Mike of All Trades progress update: recorded work/materials to date " .
        wt_money($tot['total']) . "; payments " .
        wt_money($tot['payments']) . "; outstanding " .
        wt_money($tot['outstanding']) .
        ". Please review: " . wt_public_url($job);
} else {
    $msg = trim((string)($_POST['message'] ?? ''));
}

$r = wt_send_sms(
    $pdo,
    $id,
    (string)$job['customer_phone'],
    $msg,
    (string)$kind
);

$ok = !empty($r['ok']);

$status = '';
if (!empty($r['status'])) {
    $status = (string)$r['status'];
} elseif (!empty($r['message'])) {
    $status = (string)$r['message'];
} elseif ($ok) {
    $status = 'Accepted by SMS gateway';
} else {
    $status = 'Gateway did not accept message';
}

$_SESSION['work_sms_flash'] = [
    'ok' => $ok,
    'purpose' => ucwords(str_replace('_', ' ', (string)$kind)),
    'time' => date('j M Y, g:i a'),
    'status' => $status,
    'message' => $msg,
];

header("Location: ../../admin/work/manage_job.php?id=$id#customer-communication");
exit;
