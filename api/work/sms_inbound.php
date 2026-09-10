<?php
require_once __DIR__.'/../../includes/work_tracker.php';
require_once __DIR__.'/_sms_webhook_common.php';

if (!mot_webhook_authorised()) {
    http_response_code(401);
    header('Content-Type: text/plain');
    exit('Unauthorized');
}

$env = mot_webhook_payload();
$flat = mot_webhook_flatten($env['data']);

$from = mot_webhook_first($flat, [
    'from','originator','source','sender','sender_number','source_number','mobile_from'
]);
$to = mot_webhook_first($flat, [
    'to','destination','recipient','recipient_number','destination_number','mobile_to'
]);
$message = mot_webhook_first($flat, [
    'message','body','text','content','message_text','payload'
]);
$smsref = mot_webhook_first($flat, [
    'smsref','message_id','messageid','id','message.id'
]);
$event = mot_webhook_event_name($flat) ?: 'customer_reply';

require_once __DIR__ . '/../../includes/sms_broadcast.php';

/*
 * SMS Broadcast may retry the same webhook.
 * If we have already processed this inbound provider reference,
 * acknowledge it without logging or forwarding it again.
 */
if (trim((string)$smsref) !== '') {
    try {
        $dup = $pdo->prepare("
            SELECT id
            FROM work_sms_gateway_events
            WHERE direction='inbound'
              AND smsref=?
            LIMIT 1
        ");
        $dup->execute([$smsref]);

        if ($dup->fetchColumn()) {
            http_response_code(200);
            header('Content-Type: text/plain');
            exit('OK');
        }
    } catch (Throwable $e) {
        error_log('Inbound SMS duplicate check failed: '.$e->getMessage());
    }
}

$incomingMobile = mot_sms_normalise_au_mobile((string)$from);
$matchedJob = null;

/*
 * First try to associate the reply with the most recent outbound
 * Work Tracker SMS sent to this mobile number.
 */
try {
    $q = $pdo->query("
        SELECT m.job_id,m.phone,j.customer_name,j.customer_phone,j.status,j.planned_start_at
        FROM work_sms_messages m
        LEFT JOIN work_jobs j ON j.id=m.job_id
        WHERE m.direction='outbound'
          AND m.job_id IS NOT NULL
        ORDER BY m.id DESC
        LIMIT 200
    ");

    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (
            $incomingMobile !== '' &&
            mot_sms_normalise_au_mobile((string)($row['phone'] ?? '')) === $incomingMobile
        ) {
            $matchedJob = $row;
            break;
        }
    }
} catch (Throwable $e) {
    error_log('Inbound SMS recent-message job match failed: '.$e->getMessage());
}

/*
 * Fall back to matching the customer's phone number on the job itself.
 */
if (!$matchedJob) {
    try {
        $q = $pdo->query("
            SELECT id AS job_id,customer_name,customer_phone,status,planned_start_at
            FROM work_jobs
            WHERE customer_phone IS NOT NULL
              AND customer_phone <> ''
            ORDER BY
              CASE
                WHEN status IN ('active','paused','awaiting_agreement','draft') THEN 0
                ELSE 1
              END,
              COALESCE(planned_start_at,created_at) DESC,
              id DESC
        ");

        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (
                $incomingMobile !== '' &&
                mot_sms_normalise_au_mobile((string)$row['customer_phone']) === $incomingMobile
            ) {
                $matchedJob = $row;
                break;
            }
        }
    } catch (Throwable $e) {
        error_log('Inbound SMS customer-phone job match failed: '.$e->getMessage());
    }
}

$jobId = !empty($matchedJob['job_id']) ? (int)$matchedJob['job_id'] : null;
$rawPayload = mot_webhook_store_raw($env);

/* Gateway audit record */
$st=$pdo->prepare("
    INSERT INTO work_sms_gateway_events
    (job_id,direction,event_kind,mobile_to,mobile_from,message,smsref,provider_status,provider_response)
    VALUES(?,'inbound',?,?,?,?,?,?,?)
");
$st->execute([
    $jobId,
    $event,
    $to,
    $from,
    $message,
    $smsref,
    $event,
    $rawPayload
]);

/* Normal Work Tracker conversation record */
try {
    $st=$pdo->prepare("
        INSERT INTO work_sms_messages
        (job_id,direction,phone,message,purpose,provider_ref,delivery_status,received_at,raw_payload)
        VALUES(?,'inbound',? ,?,'customer_reply',?,'received',NOW(),?)
    ");
    $st->execute([
        $jobId,
        $from,
        $message,
        $smsref,
        $rawPayload
    ]);
} catch (Throwable $e) {
    error_log('Inbound SMS conversation log failed: '.$e->getMessage());
}

/*
 * Immediately forward customer replies to Mike's private mobile.
 * Failure here must never make the inbound webhook fail.
 */
try {
    $ownerMobile = defined('WORK_TRACKER_OWNER_MOBILE')
        ? trim((string)WORK_TRACKER_OWNER_MOBILE)
        : '';

    if ($ownerMobile !== '' && trim((string)$message) !== '') {
        $customerName = trim((string)($matchedJob['customer_name'] ?? 'Unknown customer'));

        $alert = "Customer SMS reply";
        if ($jobId) {
            $alert .= " - Job #".$jobId;
        }

        $alert .= "\n".$customerName;
        $alert .= "\n".trim((string)$message);

        $forward = mot_sms_broadcast_send(
            $ownerMobile,
            $alert,
            mot_sms_ref('owner-reply')
        );

        try {
            $g = $pdo->prepare("
                INSERT INTO work_sms_gateway_events
                (job_id,direction,event_kind,mobile_to,mobile_from,message,our_ref,smsref,provider_status,provider_response)
                VALUES(?,'outbound','owner_inbound_alert',?,?,?,?,?,?,?)
            ");

            $g->execute([
                $jobId,
                $forward['to'] ?? $ownerMobile,
                $to,
                $alert,
                $forward['ref'] ?? null,
                $forward['smsref'] ?? null,
                $forward['status'] ?? (!empty($forward['ok']) ? 'accepted' : 'failed'),
                $forward['response'] ?? $forward['error'] ?? null
            ]);
        } catch (Throwable $e) {
            error_log('Owner SMS forward audit failed: '.$e->getMessage());
        }
    }
} catch (Throwable $e) {
    error_log('Owner inbound SMS forward failed: '.$e->getMessage());
}

http_response_code(200);
header('Content-Type: text/plain');
echo "OK";
