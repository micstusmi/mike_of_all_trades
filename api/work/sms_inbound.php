<?php
require_once __DIR__.'/../../includes/work_tracker.php';
require_once __DIR__ . '/../../includes/work_approvals.php';
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
 * Customer approval commands by SMS.
 *
 * Supported:
 *
 *   YES 1042
 *   NO 1042
 *   SNOOZE 1042 20
 *
 * The request number is mandatory. A plain YES remains available
 * to the existing booking-confirmation workflow below.
 *
 * Security:
 * - approval request must belong to the matched job;
 * - incoming mobile must match the customer phone for that request/job;
 * - only awaiting/snoozed requests can change state;
 * - job agreements are NOT signed merely by SMS. They retain the
 *   stronger web signature + Terms acceptance workflow.
 */

$approvalCommandHandled = false;
$ownerApprovalNotice = '';

if ($jobId && trim((string)$message) !== '') {
    try {
        $approvalReply = strtoupper(
            trim((string)$message)
        );

        /*
         * Allow harmless punctuation at the end:
         *
         * YES 1042.
         * NO 1042!
         * SNOOZE 1042 20
         */
        $approvalReply = preg_replace(
            '/[.!?,]+$/',
            '',
            $approvalReply
        );

        $approvalReply = preg_replace(
            '/\s+/',
            ' ',
            trim($approvalReply)
        );

        $approvalCommand = null;
        $approvalId = null;
        $snoozeMinutes = null;

        if (
            preg_match(
                '/^YES\s+([0-9]+)$/',
                $approvalReply,
                $m
            )
        ) {
            $approvalCommand = 'approve';
            $approvalId = (int)$m[1];

        } elseif (
            preg_match(
                '/^NO\s+([0-9]+)$/',
                $approvalReply,
                $m
            )
        ) {
            $approvalCommand = 'decline';
            $approvalId = (int)$m[1];

        } elseif (
            preg_match(
                '/^SNOOZE\s+([0-9]+)\s+([0-9]+)$/',
                $approvalReply,
                $m
            )
        ) {
            $approvalCommand = 'snooze';
            $approvalId = (int)$m[1];
            $snoozeMinutes = (int)$m[2];
        }

        /*
         * Only claim the SMS if it matches one of our explicit
         * approval command formats.
         */
        if (
            $approvalCommand !== null
            && $approvalId
        ) {
            $approvalCommandHandled = true;

            $q = $pdo->prepare("
                SELECT
                    a.*,
                    j.customer_name AS live_customer_name,
                    j.customer_phone AS live_customer_phone,
                    j.agreement_signed_at,
                    j.public_token
                FROM work_approval_requests a
                JOIN work_jobs j
                  ON j.id=a.job_id
                WHERE a.id=?
                  AND a.job_id=?
                LIMIT 1
            ");

            $q->execute([
                $approvalId,
                $jobId
            ]);

            $approval = $q->fetch(PDO::FETCH_ASSOC);

            /*
             * A command with a real-looking request number must not
             * be allowed to approve a different customer's request.
             */
            if (!$approval) {
                $ownerApprovalNotice =
                    "Approval command could not be matched to "
                    ."Approval #".$approvalId
                    ." for this job.";

                wt_send_sms(
                    $pdo,
                    $jobId,
                    (string)$matchedJob['customer_phone'],
                    "Mike of All Trades: Approval Request #"
                    .$approvalId
                    ." could not be matched to this job. "
                    ."No approval status was changed.",
                    'approval_reply_invalid'
                );

            } else {
                $requestPhone = trim(
                    (string)(
                        $approval['customer_phone_snapshot']
                        ?: $approval['live_customer_phone']
                    )
                );

                $requestMobile =
                    mot_sms_normalise_au_mobile(
                        $requestPhone
                    );

                if (
                    $incomingMobile === ''
                    || $requestMobile === ''
                    || $incomingMobile !== $requestMobile
                ) {
                    /*
                     * Do not reveal approval content to an unmatched
                     * phone number.
                     */
                    $ownerApprovalNotice =
                        "SECURITY: SMS approval command for Request #"
                        .$approvalId
                        ." came from a phone number that did not "
                        ."match the approval customer. No change made.";

                    wt_log_approval_event(
                        $pdo,
                        $approvalId,
                        $jobId,
                        'sms_phone_mismatch',
                        'customer',
                        'sms',
                        'inbound',
                        (string)$message,
                        [
                            'incoming_mobile' => $incomingMobile,
                            'expected_mobile' => $requestMobile,
                        ],
                        $rawPayload
                    );

                } elseif (
                    ($approval['request_type'] ?? '')
                    === 'job_agreement'
                ) {
                    /*
                     * Master agreement remains a proper web signature.
                     * SMS cannot substitute for Terms acceptance.
                     */
                    $ownerApprovalNotice =
                        "Customer replied about Job Agreement #"
                        .$approvalId
                        .", but the master agreement still requires "
                        ."the secure web signature.";

                    wt_log_approval_event(
                        $pdo,
                        $approvalId,
                        $jobId,
                        'job_agreement_sms_attempt',
                        'customer',
                        'sms',
                        'inbound',
                        (string)$message,
                        null,
                        $rawPayload
                    );

                    $jobForUrl = wt_job(
                        $pdo,
                        $jobId
                    );

                    wt_send_sms(
                        $pdo,
                        $jobId,
                        $requestPhone,
                        "Mike of All Trades: the main job agreement "
                        ."must be reviewed and signed online. "
                        ."An SMS reply alone cannot sign the agreement. "
                        ."Job record: "
                        .wt_public_url($jobForUrl),
                        'agreement_signature_required'
                    );

                } elseif (
                    !in_array(
                        (string)$approval['status'],
                        ['awaiting', 'snoozed'],
                        true
                    )
                ) {
                    /*
                     * Preserve immutable completed states.
                     */
                    $ownerApprovalNotice =
                        "Approval #".$approvalId
                        ." received another SMS response, but its "
                        ."current status is "
                        .wt_approval_status_label(
                            (string)$approval['status']
                        )
                        .". No status change was made.";

                    wt_log_approval_event(
                        $pdo,
                        $approvalId,
                        $jobId,
                        'sms_response_after_final_state',
                        'customer',
                        'sms',
                        'inbound',
                        (string)$message,
                        [
                            'existing_status' =>
                                $approval['status'],
                            'command' =>
                                $approvalCommand,
                        ],
                        $rawPayload
                    );

                    wt_send_sms(
                        $pdo,
                        $jobId,
                        $requestPhone,
                        "Mike of All Trades: Approval Request #"
                        .$approvalId
                        ." is already "
                        .strtolower(
                            wt_approval_status_label(
                                (string)$approval['status']
                            )
                        )
                        .". No further change was made.",
                        'approval_reply_already_final'
                    );

                } elseif (
                    $approvalCommand === 'approve'
                ) {
                    /*
                     * Freeze exactly what was approved.
                     */
                    $snapshot = [
                        'approval_request_id' =>
                            $approvalId,

                        'approved_at' =>
                            date('c'),

                        'response_source' =>
                            'sms',

                        'response_text' =>
                            trim((string)$message),

                        'subject' =>
                            $approval['subject'],

                        'request_text' =>
                            $approval['request_text'],

                        'request_type' =>
                            $approval['request_type'],

                        'approval_required' =>
                            (bool)$approval['approval_required'],

                        'work_hold_required' =>
                            (bool)$approval['work_hold_required'],

                        'estimated_hours_low' =>
                            $approval['estimated_hours_low'],

                        'estimated_hours_high' =>
                            $approval['estimated_hours_high'],

                        'estimated_amount_low' =>
                            $approval['estimated_amount_low'],

                        'estimated_amount_high' =>
                            $approval['estimated_amount_high'],

                        'terms_version' =>
                            $approval['terms_version'],

                        'terms_url' =>
                            $approval['terms_url'],

                        'customer_name_snapshot' =>
                            $approval['customer_name_snapshot']
                            ?: $approval['live_customer_name'],

                        'customer_phone_snapshot' =>
                            $requestPhone,

                        'master_agreement_signed_at' =>
                            $approval['agreement_signed_at'],
                    ];

                    $snapshotJson = json_encode(
                        $snapshot,
                        JSON_UNESCAPED_SLASHES
                        | JSON_UNESCAPED_UNICODE
                    );

                    if ($snapshotJson === false) {
                        throw new RuntimeException(
                            'Could not encode approval snapshot.'
                        );
                    }

                    $pdo->beginTransaction();

                    $u = $pdo->prepare("
                        UPDATE work_approval_requests
                        SET
                            status='approved',
                            approved_at=NOW(),
                            declined_at=NULL,
                            expired_at=NULL,
                            snoozed_until=NULL,
                            next_reminder_at=NULL,
                            response_source='sms',
                            response_text=?,
                            mike_attention_required=1,
                            approval_snapshot_json=?
                        WHERE id=?
                          AND job_id=?
                          AND status IN ('awaiting','snoozed')
                    ");

                    $u->execute([
                        trim((string)$message),
                        $snapshotJson,
                        $approvalId,
                        $jobId
                    ]);

                    if ($u->rowCount() !== 1) {
                        throw new RuntimeException(
                            'Approval state changed before SMS '
                            .'approval could be recorded.'
                        );
                    }

                    wt_log_approval_event(
                        $pdo,
                        $approvalId,
                        $jobId,
                        'approved_by_sms',
                        'customer',
                        'sms',
                        'inbound',
                        (string)$message,
                        [
                            'snapshot' => $snapshot,
                        ],
                        $rawPayload
                    );

                    $pdo->commit();

                    $masterSigned =
                        !empty(
                            $approval['agreement_signed_at']
                        );

                    $ownerApprovalNotice =
                        "APPROVED - Approval #"
                        .$approvalId
                        ." - "
                        .$approval['subject'];

                    if (!$masterSigned) {
                        $ownerApprovalNotice .=
                            "\nWARNING: master job agreement "
                            ."is still unsigned.";
                    } else {
                        $ownerApprovalNotice .=
                            "\nAffected approved work may proceed.";
                    }

                    $customerConfirmation =
                        "Mike of All Trades: thank you. "
                        ."Approval Request #"
                        .$approvalId
                        ." has been approved in writing.";

                    if ($masterSigned) {
                        $customerConfirmation .=
                            " The approved work may proceed.";
                    } else {
                        $customerConfirmation .=
                            " The separate main job agreement "
                            ."still needs to be signed before "
                            ."authorised work can proceed.";
                    }

                    wt_send_sms(
                        $pdo,
                        $jobId,
                        $requestPhone,
                        $customerConfirmation,
                        'approval_confirmed'
                    );

                } elseif (
                    $approvalCommand === 'decline'
                ) {
                    $pdo->beginTransaction();

                    $u = $pdo->prepare("
                        UPDATE work_approval_requests
                        SET
                            status='declined',
                            declined_at=NOW(),
                            approved_at=NULL,
                            snoozed_until=NULL,
                            next_reminder_at=NULL,
                            response_source='sms',
                            response_text=?,
                            mike_attention_required=1
                        WHERE id=?
                          AND job_id=?
                          AND status IN ('awaiting','snoozed')
                    ");

                    $u->execute([
                        trim((string)$message),
                        $approvalId,
                        $jobId
                    ]);

                    if ($u->rowCount() !== 1) {
                        throw new RuntimeException(
                            'Approval state changed before decline '
                            .'could be recorded.'
                        );
                    }

                    wt_log_approval_event(
                        $pdo,
                        $approvalId,
                        $jobId,
                        'declined_by_sms',
                        'customer',
                        'sms',
                        'inbound',
                        (string)$message,
                        null,
                        $rawPayload
                    );

                    $pdo->commit();

                    $ownerApprovalNotice =
                        "DECLINED - Approval #"
                        .$approvalId
                        ." - "
                        .$approval['subject']
                        ."\nAffected work remains on hold.";

                    wt_send_sms(
                        $pdo,
                        $jobId,
                        $requestPhone,
                        "Mike of All Trades: Approval Request #"
                        .$approvalId
                        ." has been recorded as declined. "
                        ."The affected work will not proceed "
                        ."unless a new approval is agreed.",
                        'approval_declined'
                    );

                } elseif (
                    $approvalCommand === 'snooze'
                ) {
                    /*
                     * Customer can request between 5 minutes and
                     * 24 hours. Snooze is NOT approval.
                     */
                    $snoozeMinutes = max(
                        5,
                        min(
                            1440,
                            (int)$snoozeMinutes
                        )
                    );

                    $nextAt = date(
                        'Y-m-d H:i:s',
                        time() + ($snoozeMinutes * 60)
                    );

                    $pdo->beginTransaction();

                    $u = $pdo->prepare("
                        UPDATE work_approval_requests
                        SET
                            status='snoozed',
                            snoozed_until=?,
                            next_reminder_at=?,
                            response_source='sms',
                            response_text=?,
                            mike_attention_required=1
                        WHERE id=?
                          AND job_id=?
                          AND status IN ('awaiting','snoozed')
                    ");

                    $u->execute([
                        $nextAt,
                        $nextAt,
                        trim((string)$message),
                        $approvalId,
                        $jobId
                    ]);

                    if ($u->rowCount() !== 1) {
                        throw new RuntimeException(
                            'Approval state changed before snooze '
                            .'could be recorded.'
                        );
                    }

                    wt_log_approval_event(
                        $pdo,
                        $approvalId,
                        $jobId,
                        'snoozed_by_sms',
                        'customer',
                        'sms',
                        'inbound',
                        (string)$message,
                        [
                            'snooze_minutes' =>
                                $snoozeMinutes,

                            'next_reminder_at' =>
                                $nextAt,
                        ],
                        $rawPayload
                    );

                    $pdo->commit();

                    $ownerApprovalNotice =
                        "SNOOZED - Approval #"
                        .$approvalId
                        ." - "
                        .$approval['subject']
                        ."\nCustomer requested "
                        .$snoozeMinutes
                        ." more minutes."
                        ."\nApproval has NOT been given.";

                    wt_send_sms(
                        $pdo,
                        $jobId,
                        $requestPhone,
                        "Mike of All Trades: Approval Request #"
                        .$approvalId
                        ." is still awaiting your approval. "
                        ."We'll remind you again in "
                        .$snoozeMinutes
                        ." minutes. "
                        ."The affected work remains on hold.",
                        'approval_snoozed'
                    );
                }
            }
        }

    } catch (Throwable $e) {
        if (
            isset($pdo)
            && $pdo instanceof PDO
            && $pdo->inTransaction()
        ) {
            $pdo->rollBack();
        }

        error_log(
            'Inbound approval command processing failed: '
            .$e->getMessage()
        );

        /*
         * The webhook itself must remain healthy.
         * Mike receives the ordinary inbound forward below.
         */
        $ownerApprovalNotice =
            "WARNING: approval SMS command processing failed. "
            ."Please review this reply manually.";
    }
}


/*
 * Booking confirmation by SMS reply.
 *
 * If we are waiting for confirmation:
 *   YES / Y / YEP / YEAH / CONFIRM / CONFIRMED -> confirmed
 *   Any other meaningful reply -> needs_change
 *
 * The original inbound message is still retained above and is still
 * forwarded to Mike below.
 */
if (
    !$approvalCommandHandled
    && $jobId
    && trim((string)$message) !== ''
) {
    try {
        $q = $pdo->prepare("
            SELECT customer_confirmation_status, planned_start_at, agreement_signed_at
            FROM work_jobs
            WHERE id=?
            LIMIT 1
        ");
        $q->execute([$jobId]);
        $confirmationJob = $q->fetch(PDO::FETCH_ASSOC);

        if (
            $confirmationJob &&
            ($confirmationJob['customer_confirmation_status'] ?? '') === 'awaiting' &&
            !empty($confirmationJob['planned_start_at']) &&
            !empty($confirmationJob['agreement_signed_at'])
        ) {
            $reply = strtoupper(trim((string)$message));
            $reply = preg_replace('/[.!?,]+$/', '', $reply);
            $reply = trim($reply);

            $yesReplies = [
                'Y',
                'YES',
                'YES PLEASE',
                'YEP',
                'YEAH',
                'YEA',
                'CONFIRM',
                'CONFIRMED'
            ];

            if (in_array($reply, $yesReplies, true)) {
                $u = $pdo->prepare("
                    UPDATE work_jobs
                    SET customer_confirmation_status='confirmed',
                        confirmation_received_at=NOW()
                    WHERE id=?
                      AND customer_confirmation_status='awaiting'
                ");
                $u->execute([$jobId]);

                error_log('Booking confirmed by SMS reply for Job #'.$jobId);
            } else {
                $u = $pdo->prepare("
                    UPDATE work_jobs
                    SET customer_confirmation_status='needs_change',
                        confirmation_received_at=NOW()
                    WHERE id=?
                      AND customer_confirmation_status='awaiting'
                ");
                $u->execute([$jobId]);

                error_log('Booking change requested by SMS reply for Job #'.$jobId);
            }
        }
    } catch (Throwable $e) {
        error_log('Inbound booking confirmation processing failed: '.$e->getMessage());
    }
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

        if (
            isset($ownerApprovalNotice)
            && trim((string)$ownerApprovalNotice) !== ''
        ) {
            $alert .= "\n\n".trim(
                (string)$ownerApprovalNotice
            );
        }

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
