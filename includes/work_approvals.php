<?php
declare(strict_types=1);

/*
 * Mike Of All Trades
 * Work Tracker approval / acknowledgement subsystem.
 *
 * This file deliberately does not send SMS by itself.
 * It contains the common approval state and audit helpers.
 */

function wt_approval_status_label(string $status): string
{
    return match ($status) {
        'draft'     => 'Draft',
        'awaiting'  => 'Awaiting customer',
        'snoozed'   => 'Snoozed by customer',
        'approved'  => 'Approved',
        'declined'  => 'Declined',
        'expired'   => 'Expired / work hold',
        'cancelled' => 'Cancelled',
        default     => ucwords(
            str_replace('_', ' ', $status)
        ),
    };
}


function wt_approval_request(
    PDO $pdo,
    int $approvalId
): ?array {
    $q = $pdo->prepare("
        SELECT *
        FROM work_approval_requests
        WHERE id=?
        LIMIT 1
    ");

    $q->execute([$approvalId]);

    $row = $q->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}


function wt_job_approval_requests(
    PDO $pdo,
    int $jobId
): array {
    $q = $pdo->prepare("
        SELECT *
        FROM work_approval_requests
        WHERE job_id=?
        ORDER BY
            CASE status
                WHEN 'awaiting' THEN 1
                WHEN 'snoozed' THEN 2
                WHEN 'declined' THEN 3
                WHEN 'expired' THEN 4
                WHEN 'draft' THEN 5
                WHEN 'approved' THEN 6
                ELSE 7
            END,
            id DESC
    ");

    $q->execute([$jobId]);

    return $q->fetchAll(PDO::FETCH_ASSOC);
}


function wt_job_approval_summary(
    PDO $pdo,
    int $jobId
): array {
    $q = $pdo->prepare("
        SELECT
            COUNT(*) AS total,

            SUM(status='approved') AS approved,

            SUM(status='awaiting') AS awaiting,

            SUM(status='snoozed') AS snoozed,

            SUM(status='declined') AS declined,

            SUM(status='expired') AS expired,

            SUM(
                approval_required=1
                AND work_hold_required=1
                AND status IN (
                    'awaiting',
                    'snoozed',
                    'declined',
                    'expired'
                )
            ) AS holds,

            SUM(
                mike_attention_required=1
            ) AS mike_attention

        FROM work_approval_requests
        WHERE job_id=?
          AND status <> 'cancelled'
    ");

    $q->execute([$jobId]);

    $row = $q->fetch(PDO::FETCH_ASSOC) ?: [];

    return [
        'total' =>
            (int)($row['total'] ?? 0),

        'approved' =>
            (int)($row['approved'] ?? 0),

        'awaiting' =>
            (int)($row['awaiting'] ?? 0),

        'snoozed' =>
            (int)($row['snoozed'] ?? 0),

        'declined' =>
            (int)($row['declined'] ?? 0),

        'expired' =>
            (int)($row['expired'] ?? 0),

        'holds' =>
            (int)($row['holds'] ?? 0),

        'mike_attention' =>
            (int)($row['mike_attention'] ?? 0),
    ];
}


function wt_log_approval_event(
    PDO $pdo,
    int $approvalId,
    int $jobId,
    string $eventType,
    string $actorType = 'system',
    string $channel = 'system',
    string $direction = 'internal',
    ?string $message = null,
    mixed $metadata = null,
    ?string $rawPayload = null
): int {
    $allowedActors = [
        'system',
        'customer',
        'mike',
    ];

    $allowedChannels = [
        'sms',
        'web',
        'admin',
        'system',
    ];

    $allowedDirections = [
        'inbound',
        'outbound',
        'internal',
    ];

    if (!in_array(
        $actorType,
        $allowedActors,
        true
    )) {
        $actorType = 'system';
    }

    if (!in_array(
        $channel,
        $allowedChannels,
        true
    )) {
        $channel = 'system';
    }

    if (!in_array(
        $direction,
        $allowedDirections,
        true
    )) {
        $direction = 'internal';
    }

    $metadataJson = null;

    if ($metadata !== null) {
        $metadataJson = json_encode(
            $metadata,
            JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
        );
    }

    $q = $pdo->prepare("
        INSERT INTO work_approval_events
        (
            approval_request_id,
            job_id,
            event_type,
            actor_type,
            channel,
            direction,
            message,
            raw_payload,
            metadata_json
        )
        VALUES(?,?,?,?,?,?,?,?,?)
    ");

    $q->execute([
        $approvalId,
        $jobId,
        trim($eventType),
        $actorType,
        $channel,
        $direction,
        $message,
        $rawPayload,
        $metadataJson,
    ]);

    return (int)$pdo->lastInsertId();
}


function wt_approval_reply_commands(
    int $approvalId
): array {
    return [
        'approve' =>
            'YES ' . $approvalId,

        'decline' =>
            'NO ' . $approvalId,

        'snooze_example' =>
            'SNOOZE ' . $approvalId . ' 20',
    ];
}


function wt_approval_default_reminder_schedule(
    string $urgency
): array {
    /*
     * Values are minutes after the previous message.
     *
     * Time-sensitive:
     * initial request
     * +20 min
     * +10 min
     * +5 min final reminder
     *
     * Normal:
     * initial request
     * +60 min
     * +180 min
     * +720 min final reminder
     */
    if ($urgency === 'time_sensitive') {
        return [20, 10, 5];
    }

    return [60, 180, 720];
}


function wt_approval_next_reminder_minutes(
    array $approval
): ?int {
    $schedule = json_decode(
        (string)(
            $approval['reminder_schedule_json']
            ?? '[]'
        ),
        true
    );

    if (!is_array($schedule)) {
        return null;
    }

    $count =
        (int)(
            $approval['reminder_count']
            ?? 0
        );

    if (!array_key_exists($count, $schedule)) {
        return null;
    }

    $minutes = (int)$schedule[$count];

    return $minutes > 0
        ? $minutes
        : null;
}


function wt_approval_is_unresolved(
    array $approval
): bool {
    return in_array(
        (string)($approval['status'] ?? ''),
        [
            'awaiting',
            'snoozed',
            'declined',
            'expired',
        ],
        true
    );
}


function wt_approval_blocks_work(
    array $approval
): bool {
    if (
        empty($approval['approval_required'])
        || empty($approval['work_hold_required'])
    ) {
        return false;
    }

    return wt_approval_is_unresolved(
        $approval
    );
}

function wt_approval_format_number(
    float $value
): string {
    return rtrim(
        rtrim(
            number_format(
                $value,
                2,
                '.',
                ''
            ),
            '0'
        ),
        '.'
    );
}


function wt_build_approval_sms(
    array $approval
): string {
    $id = (int)(
        $approval['id']
        ?? 0
    );

    $subject = trim(
        (string)(
            $approval['subject']
            ?? ''
        )
    );

    $requestText = trim(
        (string)(
            $approval['request_text']
            ?? ''
        )
    );

    $approvalRequired =
        !empty(
            $approval['approval_required']
        );

    $workHoldRequired =
        !empty(
            $approval['work_hold_required']
        );

    $agreedRate =
        isset($approval['agreed_hourly_rate'])
        && is_numeric(
            $approval['agreed_hourly_rate']
        )
        && (float)$approval['agreed_hourly_rate'] > 0
            ? (float)$approval['agreed_hourly_rate']
            : null;


    if ($approvalRequired) {

        $sms =
            "Mike of All Trades - Approval Request #"
            .$id
            .". An additional task has been added to your job: "
            .$subject
            .". Additional work: "
            .$requestText
            ." ";

        if ($agreedRate !== null) {
            $sms .=
                "If approved, this additional task will be charged "
                ."for time actually worked at the agreed job rate of "
                .wt_money($agreedRate)
                ."/hr, plus applicable materials/expenses.";
        } else {
            $sms .=
                "If approved, this additional task will be charged "
                ."for time actually worked at the agreed job hourly "
                ."rate, plus applicable materials/expenses.";
        }

        $sms .=
            " Reply YES "
            .$id
            ." to approve, NO "
            .$id
            ." to decline, or SNOOZE "
            .$id
            ." 20 if you need more time.";

        if ($workHoldRequired) {
            $sms .=
                " The affected work will remain on hold "
                ."until written approval is received.";
        }

    } else {

        $sms =
            "Mike of All Trades - Information #"
            .$id
            .". "
            .$subject
            .". "
            .$requestText
            .". This message is for your information "
            ."and does not itself place the work on hold.";
    }

    return trim($sms);
}
