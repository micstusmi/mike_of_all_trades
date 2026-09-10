<?php
require_once __DIR__ . '/../../includes/work_tracker.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('POST required.');
}

$token  = trim((string)($_POST['token'] ?? ''));
$action = trim((string)($_POST['action'] ?? ''));

if ($token === '' || !in_array($action, ['confirm', 'needs_change'], true)) {
    http_response_code(400);
    die('Invalid request.');
}

try {
    $job = wt_job_by_token($pdo, $token);
} catch (Throwable $e) {
    http_response_code(404);
    die('Job not found.');
}

if (empty($job['planned_start_at'])) {
    http_response_code(409);
    die('This booking does not currently have a scheduled start time.');
}

$status = $action === 'confirm' ? 'confirmed' : 'needs_change';

$q = $pdo->prepare("
    UPDATE work_jobs
    SET customer_confirmation_status=?,
        confirmation_received_at=NOW()
    WHERE id=?
");
$q->execute([$status, (int)$job['id']]);

/*
 * If the customer needs something changed, alert Mike immediately.
 * This is an owner/admin alert, not a message in the customer's SMS thread.
 */
if ($status === 'needs_change') {
    try {
        require_once __DIR__ . '/../../includes/sms_broadcast.php';

        $ownerMobile = defined('WORK_TRACKER_OWNER_MOBILE')
            ? trim((string)WORK_TRACKER_OWNER_MOBILE)
            : '';

        if ($ownerMobile !== '') {
            $customerName = trim((string)($job['customer_name'] ?? 'Customer'));

            $message =
                "Booking change requested - Job #" . (int)$job['id'] .
                "\n" . $customerName .
                "\nCustomer says the scheduled booking needs to change.";

            $result = mot_sms_broadcast_send(
                $ownerMobile,
                $message,
                mot_sms_ref('bookchange')
            );

            try {
                $g = $pdo->prepare("
                    INSERT INTO work_sms_gateway_events
                    (job_id,direction,event_kind,mobile_to,message,our_ref,smsref,provider_status,provider_response)
                    VALUES(?,'outbound','booking_change_alert',?,?,?,?,?,?)
                ");

                $g->execute([
                    (int)$job['id'],
                    $result['to'] ?? $ownerMobile,
                    $message,
                    $result['ref'] ?? null,
                    $result['smsref'] ?? null,
                    $result['status'] ?? (!empty($result['ok']) ? 'accepted' : 'failed'),
                    $result['response'] ?? $result['error'] ?? null
                ]);
            } catch (Throwable $e) {
                error_log('Booking change alert audit failed: '.$e->getMessage());
            }
        }
    } catch (Throwable $e) {
        error_log('Booking change owner alert failed: '.$e->getMessage());
    }
}

header(
    'Location: ../../work/job.php?t=' .
    urlencode($token) .
    '&booking_confirmation=' .
    urlencode($status) .
    '#customer-schedule'
);
exit;
