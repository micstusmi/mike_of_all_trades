<?php
declare(strict_types=1);

require_once __DIR__ . '/_admin_auth.php';
require_once __DIR__ . '/../../includes/work_tracker.php';

$id = (int)($_POST['job_id'] ?? 0);
$job = wt_job($pdo, $id);

$etaMinutes = (int)($_POST['eta_minutes'] ?? 0);
$origin = trim((string)($_POST['origin'] ?? ''));
$notes = trim((string)($_POST['notes'] ?? ''));

if ($etaMinutes < 1 || $etaMinutes > 240) {
    $_SESSION['work_sms_flash'] = [
        'ok' => false,
        'purpose' => 'On my way',
        'time' => date('j M Y, g:i a'),
        'status' => 'Travel not started',
        'message' => 'Please enter an ETA between 1 and 240 minutes.',
    ];

    header("Location: ../../admin/work/manage_job.php?id=$id#on-my-way");
    exit;
}

$dup = $pdo->prepare("
    SELECT id
    FROM work_sessions
    WHERE job_id=?
      AND worker_id IS NULL
      AND ended_at IS NULL
    LIMIT 1
");
$dup->execute([$id]);

if ($dup->fetchColumn()) {
    $_SESSION['work_sms_flash'] = [
        'ok' => false,
        'purpose' => 'On my way',
        'time' => date('j M Y, g:i a'),
        'status' => 'Travel not started',
        'message' => 'Mike already has a running session. Stop it before starting travel.',
    ];

    header("Location: ../../admin/work/manage_job.php?id=$id#on-my-way");
    exit;
}

$etaAt = date('Y-m-d H:i:s', time() + ($etaMinutes * 60));
$etaDisplay = date('g:i a', strtotime($etaAt));

$route = $origin !== ''
    ? $origin . ' → ' . $job['job_address']
    : 'Travelling to customer premises';

$q = $pdo->prepare("
    INSERT INTO work_sessions
    (
        job_id,
        session_source,
        worker_id,
        started_at,
        category,
        start_location,
        location_detail,
        travel_type,
        travel_eta,
        billable,
        notes
    )
    VALUES
    (?, 'live', NULL, NOW(), 'travel', 'travel_job', ?, 'to_customer', ?, 1, ?)
");

$q->execute([
    $id,
    $route,
    $etaAt,
    $notes !== '' ? $notes : 'Travel to customer premises'
]);

$pdo->prepare("
    UPDATE work_jobs
    SET status='active'
    WHERE id=?
")->execute([$id]);

$mode = $job['customer_update_mode'] ?? 'full_transparency';

if (
    in_array($mode, ['full_transparency', 'important_only'], true)
    && !empty($job['customer_phone'])
) {
    $msg =
        "Mike of All Trades — On my way\n" .
        "Mike has left for your job.\n" .
        "ETA: " . $etaDisplay . " (about " . $etaMinutes . " minutes).\n" .
        "Job-related travel is now being recorded separately so your job history shows travel as well as on-site work.\n" .
        "Live job record: " . wt_public_url($job);

    $r = wt_send_sms(
        $pdo,
        $id,
        (string)$job['customer_phone'],
        $msg,
        'on_my_way'
    );

    $ok = !empty($r['ok']);

    if (!empty($r['status'])) {
        $gatewayStatus = (string)$r['status'];
    } elseif (!empty($r['message'])) {
        $gatewayStatus = (string)$r['message'];
    } elseif ($ok) {
        $gatewayStatus = 'Accepted by SMS gateway';
    } else {
        $gatewayStatus = 'Gateway did not accept message';
    }

    $_SESSION['work_sms_flash'] = [
        'ok' => $ok,
        'purpose' => 'On my way',
        'time' => date('j M Y, g:i a'),
        'status' => $gatewayStatus,
        'message' => $msg,
    ];
} else {
    $_SESSION['work_sms_flash'] = [
        'ok' => true,
        'purpose' => 'On my way',
        'time' => date('j M Y, g:i a'),
        'status' => 'Travel session started — no SMS required by communication setting',
        'message' => 'ETA ' . $etaDisplay . ' (about ' . $etaMinutes . ' minutes).',
    ];
}

header("Location: ../../admin/work/manage_job.php?id=$id#on-my-way");
exit;
