<?php
require_once __DIR__ . '/_admin_auth.php';
require_once __DIR__ . '/../../includes/work_tracker.php';

$id = (int)($_POST['job_id'] ?? 0);
$sid = (int)($_POST['session_id'] ?? 0);
$job = wt_job($pdo, $id);

$allowedReasons = [
    'lunch','coffee_break','other_job_errand','other_customer_call','home_for_night',
    'personal_errand','customer_emergency','supplier_delay','awaiting_customer',
    'job_related_travel','completed_activity','other'
];

$reasonLabels = [
    'lunch'=>'Lunch',
    'coffee_break'=>'Coffee / short break',
    'other_job_errand'=>'Errand for a different job',
    'other_customer_call'=>'Phone call / admin for another customer',
    'home_for_night'=>'Going home for the night',
    'personal_errand'=>'Personal errand',
    'customer_emergency'=>'Helping another customer with an emergency',
    'supplier_delay'=>'Supplier / material delay',
    'awaiting_customer'=>'Waiting for customer decision / access',
    'job_related_travel'=>'Changing location / travelling for this job',
    'completed_activity'=>'Current activity completed',
    'other'=>'Other'
];

$reason = $_POST['stop_reason'] ?? '';
if (!in_array($reason, $allowedReasons, true)) die('Please choose a valid stop reason.');

$stopNote = trim($_POST['stop_note'] ?? '');
$expectedReturn = trim($_POST['expected_return'] ?? '');

$sessionStmt = $pdo->prepare("
    SELECT s.*, w.worker_name
    FROM work_sessions s
    LEFT JOIN work_workers w ON w.id=s.worker_id
    WHERE s.id=? AND s.job_id=? LIMIT 1
");
$sessionStmt->execute([$sid,$id]);
$session = $sessionStmt->fetch(PDO::FETCH_ASSOC);
if (!$session || !empty($session['ended_at'])) {
    header("Location: ../../admin/work/job.php?id=$id");
    exit;
}
$workerName = $session['worker_name'] ?: 'Mike';

$stmt = $pdo->prepare("
    UPDATE work_sessions
    SET ended_at=NOW(), stop_reason=?, stop_note=?, expected_return=?
    WHERE id=? AND job_id=? AND ended_at IS NULL
");
$stmt->execute([$reason,$stopNote ?: null,$expectedReturn ?: null,$sid,$id]);

$running = $pdo->prepare("SELECT COUNT(*) FROM work_sessions WHERE job_id=? AND ended_at IS NULL");
$running->execute([$id]);
$status = ((int)$running->fetchColumn() > 0) ? 'active' : 'paused';
$pdo->prepare("UPDATE work_jobs SET status=? WHERE id=?")->execute([$status,$id]);

$mode = $job['customer_update_mode'] ?? 'full_transparency';
$send = ($mode === 'full_transparency');

if ($mode === 'important_only') {
    $send = in_array($reason, [
        'home_for_night','customer_emergency','supplier_delay',
        'awaiting_customer','job_related_travel'
    ], true);
}

if ($send && !empty($job['customer_phone'])) {
    $planStmt = $pdo->prepare("SELECT planned_finish_time FROM work_daily_plans
                              WHERE job_id=? AND plan_date=CURDATE() LIMIT 1");
    $planStmt->execute([$id]);
    $plan = $planStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $unrelated = in_array($reason, [
        'lunch','coffee_break','other_job_errand','other_customer_call',
        'personal_errand','customer_emergency'
    ], true);

    $msg = "Mike of All Trades — Job update\n";
    $msg .= "⏸ ".date('g:i a')." — ".$workerName." paused/stopped job activity.\n";
    $msg .= "Reason: ".($reasonLabels[$reason] ?? $reason).".";
    if ($stopNote !== '') $msg .= "\nDetails: ".$stopNote.".";

    if ($unrelated) {
        $msg .= "\nThis paused/unrelated time is not being recorded against your job.";
    } elseif ($reason === 'job_related_travel') {
        $msg .= "\nJob-related travel can be recorded separately from on-site time.";
    }

    if ($expectedReturn !== '') {
        $msg .= "\nExpected return / next attendance: ".$expectedReturn.".";
    }

    if (!empty($plan['planned_finish_time']) && $reason !== 'home_for_night') {
        $msg .= "\nToday's anticipated finish remains approx. ".date('g:i a', strtotime($plan['planned_finish_time'])).".";
    }

    $msg .= "\nLive job record: ".wt_public_url($job);

    wt_send_sms($pdo, $id, $job['customer_phone'], $msg, 'session_stopped');
}

header("Location: ../../admin/work/job.php?id=$id&stopped=1");
exit;
