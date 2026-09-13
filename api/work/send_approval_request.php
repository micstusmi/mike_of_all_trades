<?php
declare(strict_types=1);

require_once __DIR__ . '/_admin_auth.php';
require_once __DIR__ . '/../../includes/work_tracker.php';
require_once __DIR__ . '/../../includes/work_approvals.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('POST required.');
}

$approvalId = (int)(
    $_POST['approval_id']
    ?? 0
);

$sms = trim(
    (string)(
        $_POST['sms_message']
        ?? ''
    )
);

if (
    $approvalId <= 0
    || $sms === ''
) {
    http_response_code(400);
    exit(
        'Approval request and SMS message are required.'
    );
}


$q = $pdo->prepare("
    SELECT
        a.*,
        j.customer_name,
        j.customer_phone,
        j.agreement_signed_at
    FROM work_approval_requests a
    JOIN work_jobs j
      ON j.id=a.job_id
    WHERE a.id=?
    LIMIT 1
");

$q->execute([
    $approvalId
]);

$approval =
    $q->fetch(PDO::FETCH_ASSOC);

if (!$approval) {
    http_response_code(404);
    exit('Approval request not found.');
}


if (
    ($approval['status'] ?? '')
    !== 'draft'
) {
    http_response_code(409);

    exit(
        'This approval request has already left draft status.'
    );
}


$jobId =
    (int)$approval['job_id'];

$phone = trim(
    (string)(
        $approval[
            'customer_phone_snapshot'
        ]
        ?: $approval['customer_phone']
    )
);

if ($phone === '') {
    http_response_code(400);
    exit(
        'Customer has no phone number recorded.'
    );
}


/*
 * If approval is required, the exact command references must
 * remain in the SMS. This prevents Mike accidentally deleting the
 * request number while editing the wording.
 */
if (
    !empty(
        $approval['approval_required']
    )
) {
    $yesNeedle =
        'YES '.$approvalId;

    $noNeedle =
        'NO '.$approvalId;

    if (
        stripos(
            $sms,
            $yesNeedle
        ) === false
        || stripos(
            $sms,
            $noNeedle
        ) === false
    ) {
        http_response_code(400);

        exit(
            'The message must keep both "'
            .$yesNeedle
            .'" and "'
            .$noNeedle
            .'" so the customer response can be matched safely.'
        );
    }
}


$result = wt_send_sms(
    $pdo,
    $jobId,
    $phone,
    $sms,
    !empty(
        $approval['approval_required']
    )
        ? 'approval_request'
        : 'customer_information'
);


if (!empty($result['ok'])) {

    $approvalRequired =
        !empty(
            $approval['approval_required']
        );

    $schedule = json_decode(
        (string)(
            $approval[
                'reminder_schedule_json'
            ]
            ?? '[]'
        ),
        true
    );

    $firstMinutes =
        is_array($schedule)
        ? ($schedule[0] ?? null)
        : null;

    $nextReminder = null;

    if (
        $approvalRequired
        && $firstMinutes !== null
        && (int)$firstMinutes > 0
    ) {
        $nextReminder = date(
            'Y-m-d H:i:s',
            time()
            + (
                (int)$firstMinutes
                * 60
            )
        );
    }


    $newStatus =
        $approvalRequired
        ? 'awaiting'
        : 'sent';


    $pdo->beginTransaction();

    try {
        $u = $pdo->prepare("
            UPDATE work_approval_requests
            SET
                status=?,
                first_sent_at=NOW(),
                last_sent_at=NOW(),
                next_reminder_at=?,
                mike_attention_required=0
            WHERE id=?
              AND status='draft'
        ");

        $u->execute([
            $newStatus,
            $nextReminder,
            $approvalId
        ]);

        if ($u->rowCount() !== 1) {
            throw new RuntimeException(
                'Approval request changed before '
                .'the send result could be recorded.'
            );
        }


        wt_log_approval_event(
            $pdo,
            $approvalId,
            $jobId,
            'initial_sms_sent',
            'mike',
            'sms',
            'outbound',
            $sms,
            [
                'sms_message_id' =>
                    $result['id']
                    ?? null,

                'provider_ref' =>
                    $result['ref']
                    ?? null,

                'local_ref' =>
                    $result['local_ref']
                    ?? null,

                'next_reminder_at' =>
                    $nextReminder,
            ]
        );

        $pdo->commit();

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }


    $_SESSION[
        'wt_approval_flash'
    ] = [
        'ok' => true,

        'message' =>
            (
                $approvalRequired
                ? 'Approval Request #'
                : 'Information #'
            )
            .$approvalId
            .' was accepted by the SMS gateway.',
    ];

} else {

    $u = $pdo->prepare("
        UPDATE work_approval_requests
        SET mike_attention_required=1
        WHERE id=?
          AND status='draft'
    ");

    $u->execute([
        $approvalId
    ]);


    wt_log_approval_event(
        $pdo,
        $approvalId,
        $jobId,
        'initial_sms_failed',
        'system',
        'sms',
        'outbound',
        $sms,
        [
            'result' => $result,
        ]
    );


    $_SESSION[
        'wt_approval_flash'
    ] = [
        'ok' => false,

        'message' =>
            'Approval Request #'
            .$approvalId
            .' remains a draft because the SMS gateway '
            .'did not accept the message.',
    ];
}


header(
    'Location: ../../admin/work/manage_job.php?id='
    .$jobId
    .'#customer-approvals'
);

exit;
