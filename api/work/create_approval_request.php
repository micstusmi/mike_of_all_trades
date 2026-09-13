<?php
declare(strict_types=1);

require_once __DIR__ . '/_admin_auth.php';
require_once __DIR__ . '/../../includes/work_tracker.php';
require_once __DIR__ . '/../../includes/work_approvals.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('POST required.');
}

$jobId = (int)(
    $_POST['job_id']
    ?? 0
);

if ($jobId <= 0) {
    http_response_code(400);
    exit('Invalid job.');
}

$job = wt_job(
    $pdo,
    $jobId
);

$subject = trim(
    (string)(
        $_POST['subject']
        ?? ''
    )
);

$requestText = trim(
    (string)(
        $_POST['request_text']
        ?? ''
    )
);

$requestType = (string)(
    $_POST['request_type']
    ?? 'additional_work'
);

$urgency = (string)(
    $_POST['urgency']
    ?? 'normal'
);

$approvalRequired =
    (
        $_POST['approval_required']
        ?? '1'
    ) === '1';

$workHoldRequired =
    $approvalRequired
    && (
        (
            $_POST['work_hold_required']
            ?? '1'
        ) === '1'
    );

$taskId = (int)(
    $_POST['task_id']
    ?? 0
);

$taskId =
    $taskId > 0
    ? $taskId
    : null;


$allowedTypes = [
    'variation',
    'additional_work',
    'acknowledgement',
];

if (
    !in_array(
        $requestType,
        $allowedTypes,
        true
    )
) {
    $requestType =
        'additional_work';
}

if (
    !in_array(
        $urgency,
        [
            'normal',
            'time_sensitive'
        ],
        true
    )
) {
    $urgency = 'normal';
}

if (
    $requestType ===
    'acknowledgement'
) {
    $approvalRequired = false;
    $workHoldRequired = false;
}

if ($subject === '') {
    http_response_code(400);
    exit(
        'Approval subject is required.'
    );
}

if ($requestText === '') {
    http_response_code(400);
    exit(
        'Approval details are required.'
    );
}


if ($taskId !== null) {
    $q = $pdo->prepare("
        SELECT id
        FROM work_tasks
        WHERE id=?
          AND job_id=?
        LIMIT 1
    ");

    $q->execute([
        $taskId,
        $jobId
    ]);

    if (!$q->fetchColumn()) {
        http_response_code(400);
        exit(
            'Selected task does not '
            .'belong to this job.'
        );
    }
}


function approval_nullable_number(
    mixed $value
): ?float {
    if (
        $value === null
        || trim((string)$value) === ''
    ) {
        return null;
    }

    if (!is_numeric($value)) {
        return null;
    }

    return round(
        max(
            0,
            (float)$value
        ),
        2
    );
}


$hoursLow =
    approval_nullable_number(
        $_POST[
            'estimated_hours_low'
        ] ?? null
    );

$hoursHigh =
    approval_nullable_number(
        $_POST[
            'estimated_hours_high'
        ] ?? null
    );

$amountLow =
    approval_nullable_number(
        $_POST[
            'estimated_amount_low'
        ] ?? null
    );

$amountHigh =
    approval_nullable_number(
        $_POST[
            'estimated_amount_high'
        ] ?? null
    );


if (
    $hoursLow !== null
    && $hoursHigh !== null
    && $hoursHigh < $hoursLow
) {
    [
        $hoursLow,
        $hoursHigh
    ] = [
        $hoursHigh,
        $hoursLow
    ];
}


if (
    $amountLow !== null
    && $amountHigh !== null
    && $amountHigh < $amountLow
) {
    [
        $amountLow,
        $amountHigh
    ] = [
        $amountHigh,
        $amountLow
    ];
}


$phone = trim(
    (string)(
        $job['customer_phone']
        ?? ''
    )
);

if ($phone === '') {
    http_response_code(400);

    exit(
        'Customer has no phone number recorded. '
        .'Approval draft was not created.'
    );
}


$termsVersion = trim(
    (string)(
        $job['agreement_version']
        ?? ''
    )
);

if ($termsVersion === '') {
    $termsVersion = 'current';
}

$termsUrl =
    'https://www.mikeofalltrades.com.au/terms';

$schedule =
    wt_approval_default_reminder_schedule(
        $urgency
    );

$scheduleJson = json_encode(
    $schedule,
    JSON_UNESCAPED_SLASHES
    | JSON_UNESCAPED_UNICODE
);

if ($scheduleJson === false) {
    throw new RuntimeException(
        'Could not encode reminder schedule.'
    );
}


$pdo->beginTransaction();

try {
    $q = $pdo->prepare("
        INSERT INTO work_approval_requests
        (
            job_id,
            task_id,
            request_type,
            approval_required,
            work_hold_required,
            status,
            subject,
            request_text,
            estimated_hours_low,
            estimated_hours_high,
            estimated_amount_low,
            estimated_amount_high,
            customer_name_snapshot,
            customer_phone_snapshot,
            terms_version,
            terms_url,
            urgency,
            reminder_schedule_json,
            reminder_count,
            max_reminders,
            mike_attention_required
        )
        VALUES(
            ?,?,?,?,?,
            'draft',
            ?,?,?,?,?,?,
            ?,?,?,?,?,?,
            0,3,0
        )
    ");

    $q->execute([
        $jobId,
        $taskId,
        $requestType,

        $approvalRequired ? 1 : 0,
        $workHoldRequired ? 1 : 0,

        $subject,
        $requestText,

        $hoursLow,
        $hoursHigh,

        $amountLow,
        $amountHigh,

        (string)(
            $job['customer_name']
            ?? ''
        ),

        $phone,

        $termsVersion,
        $termsUrl,

        $urgency,
        $scheduleJson,
    ]);

    $approvalId =
        (int)$pdo->lastInsertId();

    wt_log_approval_event(
        $pdo,
        $approvalId,
        $jobId,
        'draft_created',
        'mike',
        'admin',
        'internal',
        $requestText,
        [
            'subject' =>
                $subject,

            'request_type' =>
                $requestType,

            'approval_required' =>
                $approvalRequired,

            'work_hold_required' =>
                $workHoldRequired,

            'urgency' =>
                $urgency,
        ]
    );

    $pdo->commit();

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    throw $e;
}


/*
 * Nothing has been sent yet.
 *
 * Mike is deliberately taken to a second screen where the exact
 * outgoing SMS — now including the permanent request number —
 * can be reviewed and edited.
 */
header(
    'Location: ../../admin/work/review_approval.php?id='
    .$approvalId
);

exit;
