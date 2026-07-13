<?php

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/includes/trip_email.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

/*
 * Honeypot spam protection.
 */
if (trim($_POST['trip_company_field'] ?? '') !== '') {
    error_log(
        'Thailand trip interest rejected by honeypot.'
    );

    header('Location: index.php?submitted=1#interest');
    exit;
}

$tripId = 1;
$name = trim($_POST['name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$email = trim($_POST['email'] ?? '');
$status = $_POST['status'] ?? 'interested';
$notes = trim($_POST['notes'] ?? '');
$weekIds = $_POST['weeks'] ?? [];

$allowedStatuses = [
    'interested',
    'likely',
    'confirmed',
    'not_coming'
];

if (
    $name === ''
    || !is_array($weekIds)
    || count($weekIds) === 0
    || !in_array($status, $allowedStatuses, true)
) {
    error_log(
        'Thailand trip interest validation failed: '
        . 'missing name, status or selected weeks.'
    );

    header('Location: index.php?error=1#interest');
    exit;
}

if (
    $email !== ''
    && !filter_var($email, FILTER_VALIDATE_EMAIL)
) {
    header('Location: index.php?error=1#interest');
    exit;
}

$weekIds = array_values(
    array_unique(
        array_filter(
            array_map('intval', $weekIds),
            fn (int $id): bool => $id > 0
        )
    )
);

if (!$weekIds) {
    header('Location: index.php?error=1#interest');
    exit;
}

$placeholders = implode(
    ',',
    array_fill(0, count($weekIds), '?')
);

$validationStmt = $pdo->prepare("
    SELECT id
    FROM trip_weeks
    WHERE trip_id = ?
      AND id IN ($placeholders)
");

$validationStmt->execute([
    $tripId,
    ...$weekIds
]);

$validWeekIds = array_map(
    'intval',
    $validationStmt->fetchAll(PDO::FETCH_COLUMN)
);

if (!$validWeekIds) {
    header('Location: index.php?error=1#interest');
    exit;
}

$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$ipHash = hash('sha256', $ip . '|thailand-trip');

try {
    $pdo->beginTransaction();

    $responseStmt = $pdo->prepare("
        INSERT INTO trip_interest_responses (
            trip_id,
            name,
            phone,
            email,
            attendance_status,
            notes,
            submitted_ip_hash
        ) VALUES (
            :trip_id,
            :name,
            :phone,
            :email,
            :attendance_status,
            :notes,
            :submitted_ip_hash
        )
    ");

    $responseStmt->execute([
        'trip_id' => $tripId,
        'name' => $name,
        'phone' => $phone !== '' ? $phone : null,
        'email' => $email !== '' ? $email : null,
        'attendance_status' => $status,
        'notes' => $notes !== '' ? $notes : null,
        'submitted_ip_hash' => $ipHash
    ]);

    $responseId = (int) $pdo->lastInsertId();

    $weekStmt = $pdo->prepare("
        INSERT INTO trip_interest_weeks (
            response_id,
            trip_week_id
        ) VALUES (?, ?)
    ");

    foreach ($validWeekIds as $weekId) {
        $weekStmt->execute([
            $responseId,
            $weekId
        ]);
    }

    $pdo->commit();

    error_log(
        'Thailand trip interest saved successfully. '
        . 'Response ID: '
        . $responseId
    );

    /*
     * Build a readable list of the selected weeks.
     */
    $selectedWeeksStmt = $pdo->prepare("
        SELECT
            week_number,
            title
        FROM trip_weeks
        WHERE trip_id = ?
          AND id IN (
              SELECT trip_week_id
              FROM trip_interest_weeks
              WHERE response_id = ?
          )
        ORDER BY week_number
    ");

    $selectedWeeksStmt->execute([
        $tripId,
        $responseId
    ]);

    $selectedWeeks = [];

    foreach (
        $selectedWeeksStmt->fetchAll(PDO::FETCH_ASSOC)
        as $selectedWeek
    ) {
        $selectedWeeks[] =
            'Week '
            . (int) $selectedWeek['week_number']
            . ': '
            . $selectedWeek['title'];
    }

    $statusLabels = [
        'interested' => 'Interested',
        'likely' => 'Likely to attend',
        'confirmed' => 'Confirmed',
        'not_coming' => 'Not coming'
    ];

    $statusLabel =
        $statusLabels[$status]
        ?? 'Interested';

    $safeName = htmlspecialchars(
        $name,
        ENT_QUOTES,
        'UTF-8'
    );

    $safePhone = htmlspecialchars(
        $phone !== '' ? $phone : 'Not supplied',
        ENT_QUOTES,
        'UTF-8'
    );

    $safeEmail = htmlspecialchars(
        $email !== '' ? $email : 'Not supplied',
        ENT_QUOTES,
        'UTF-8'
    );

    $safeStatus = htmlspecialchars(
        $statusLabel,
        ENT_QUOTES,
        'UTF-8'
    );

    $safeNotes = htmlspecialchars(
        $notes !== '' ? $notes : 'No notes supplied',
        ENT_QUOTES,
        'UTF-8'
    );

    $weekHtml = '';

    foreach ($selectedWeeks as $selectedWeek) {
        $weekHtml .= '<li>'
            . htmlspecialchars(
                $selectedWeek,
                ENT_QUOTES,
                'UTF-8'
            )
            . '</li>';
    }

    $weekPlain = implode(
        "\n",
        array_map(
            fn (string $week): string => '- ' . $week,
            $selectedWeeks
        )
    );

    /*
     * Notify the organiser.
     */
    $organiserEmail = tripOrganiserEmail();

    if (
        $organiserEmail !== ''
        && filter_var(
            $organiserEmail,
            FILTER_VALIDATE_EMAIL
        )
    ) {
        $adminSubject =
            'Thailand 2027 - '
            . $name
            . ' is '
            . $statusLabel;

        $submittedAt = date(
            'j F Y, g:i a'
        );

        $attendeesUrl =
            'https://www.mikeofalltrades.com.au'
            . '/thailand-trip/attendees.php';

        $plannerUrl =
            'https://www.mikeofalltrades.com.au'
            . '/thailand-trip/planner.php';

        $adminHtml = '
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >
</head>

<body style="
    margin:0;
    padding:0;
    background:#eef3f6;
    font-family:Arial,Helvetica,sans-serif;
    color:#20313d;
">

<table
    role="presentation"
    width="100%"
    cellspacing="0"
    cellpadding="0"
    style="background:#eef3f6;padding:24px 12px;"
>
<tr>
<td align="center">

<table
    role="presentation"
    width="100%"
    cellspacing="0"
    cellpadding="0"
    style="
        max-width:620px;
        background:#ffffff;
        border-radius:16px;
        overflow:hidden;
        box-shadow:0 8px 24px rgba(20,55,75,.12);
    "
>
    <tr>
        <td style="
            padding:26px;
            background:#103b54;
            color:#ffffff;
        ">
            <div style="
                color:#ffc21a;
                font-size:12px;
                font-weight:bold;
                letter-spacing:1.4px;
                text-transform:uppercase;
            ">
                Thailand 2027
            </div>

            <h1 style="
                margin:8px 0 0;
                font-size:28px;
                line-height:1.2;
            ">
                New expression of interest
            </h1>
        </td>
    </tr>

    <tr>
        <td style="padding:26px;">

            <table
                role="presentation"
                width="100%"
                cellspacing="0"
                cellpadding="0"
            >
                <tr>
                    <td style="
                        padding:0 0 16px;
                        color:#6a7a84;
                        font-size:12px;
                        font-weight:bold;
                        text-transform:uppercase;
                    ">
                        Traveller
                    </td>
                </tr>

                <tr>
                    <td style="
                        padding:0 0 22px;
                        font-size:24px;
                        font-weight:bold;
                    ">
                        ' . $safeName . '
                    </td>
                </tr>
            </table>

            <table
                role="presentation"
                width="100%"
                cellspacing="0"
                cellpadding="0"
                style="
                    border-collapse:collapse;
                    background:#f6f9fa;
                    border-radius:12px;
                "
            >
                <tr>
                    <td style="
                        padding:14px;
                        border-bottom:1px solid #dfe7eb;
                        font-weight:bold;
                        width:150px;
                    ">
                        Status
                    </td>
                    <td style="
                        padding:14px;
                        border-bottom:1px solid #dfe7eb;
                    ">
                        ' . $safeStatus . '
                    </td>
                </tr>

                <tr>
                    <td style="
                        padding:14px;
                        border-bottom:1px solid #dfe7eb;
                        font-weight:bold;
                    ">
                        Mobile
                    </td>
                    <td style="
                        padding:14px;
                        border-bottom:1px solid #dfe7eb;
                    ">
                        ' . $safePhone . '
                    </td>
                </tr>

                <tr>
                    <td style="
                        padding:14px;
                        border-bottom:1px solid #dfe7eb;
                        font-weight:bold;
                    ">
                        Email
                    </td>
                    <td style="
                        padding:14px;
                        border-bottom:1px solid #dfe7eb;
                        word-break:break-word;
                    ">
                        ' . $safeEmail . '
                    </td>
                </tr>

                <tr>
                    <td style="
                        padding:14px;
                        font-weight:bold;
                    ">
                        Submitted
                    </td>
                    <td style="padding:14px;">
                        ' . htmlspecialchars(
                            $submittedAt,
                            ENT_QUOTES,
                            'UTF-8'
                        ) . '
                    </td>
                </tr>
            </table>

            <h2 style="
                margin:26px 0 10px;
                font-size:18px;
            ">
                Selected weeks
            </h2>

            <ul style="
                margin:0;
                padding:16px 16px 16px 36px;
                background:#f6f9fa;
                border-radius:12px;
                line-height:1.7;
            ">
                ' . $weekHtml . '
            </ul>

            <h2 style="
                margin:26px 0 10px;
                font-size:18px;
            ">
                Questions or notes
            </h2>

            <div style="
                padding:16px;
                background:#f6f9fa;
                border-radius:12px;
                line-height:1.6;
            ">
                ' . nl2br($safeNotes) . '
            </div>

            <table
                role="presentation"
                cellspacing="0"
                cellpadding="0"
                style="margin-top:26px;"
            >
                <tr>
                    <td style="padding-right:10px;">
                        <a
                            href="' . $attendeesUrl . '"
                            style="
                                display:inline-block;
                                padding:13px 18px;
                                border-radius:8px;
                                background:#ffc21a;
                                color:#132f40;
                                font-weight:bold;
                                text-decoration:none;
                            "
                        >
                            View attendees
                        </a>
                    </td>

                    <td>
                        <a
                            href="' . $plannerUrl . '"
                            style="
                                display:inline-block;
                                padding:12px 18px;
                                border:1px solid #174d69;
                                border-radius:8px;
                                color:#174d69;
                                font-weight:bold;
                                text-decoration:none;
                            "
                        >
                            Open planner
                        </a>
                    </td>
                </tr>
            </table>

        </td>
    </tr>
</table>

</td>
</tr>
</table>

</body>
</html>
        ';

        $adminPlain =
            "New Thailand 2027 response\n\n"
            . "Name: {$name}\n"
            . "Status: {$statusLabel}\n"
            . "Mobile: "
            . ($phone !== '' ? $phone : 'Not supplied')
            . "\n"
            . "Email: "
            . ($email !== '' ? $email : 'Not supplied')
            . "\n\n"
            . "Selected weeks:\n"
            . $weekPlain
            . "\n\n"
            . "Notes: "
            . ($notes !== '' ? $notes : 'No notes supplied');

        sendTripEmail(
            $organiserEmail,
            'Mike',
            $adminSubject,
            $adminHtml,
            $adminPlain
        );
    } else {
        error_log(
            'Thailand trip organiser notification skipped: '
            . 'TRIP_ORGANISER_EMAIL is not configured.'
        );
    }

    /*
     * Send confirmation to the traveller when an email was supplied.
     */
    if (
        $email !== ''
        && filter_var($email, FILTER_VALIDATE_EMAIL)
    ) {
        $confirmationSubject =
            'Thailand 2027 interest received';

        $confirmationHtml = '
            <h2>Thanks, ' . $safeName . '</h2>

            <p>
                Your response for the Thailand 2027 trip
                has been received.
            </p>

            <p>
                <strong>Your current response:</strong>
                ' . $safeStatus . '
            </p>

            <p><strong>Weeks selected:</strong></p>

            <ul>
                ' . $weekHtml . '
            </ul>

            <p>
                Mike will keep you updated as the itinerary,
                accommodation and transport plans develop.
            </p>

            <p>
                Regards,<br>
                Mike
            </p>
        ';

        $confirmationPlain =
            "Thanks, {$name}\n\n"
            . "Your response for the Thailand 2027 trip "
            . "has been received.\n\n"
            . "Current response: {$statusLabel}\n\n"
            . "Weeks selected:\n"
            . $weekPlain
            . "\n\n"
            . "Mike will keep you updated as the plans develop.";

        sendTripEmail(
            $email,
            $name,
            $confirmationSubject,
            $confirmationHtml,
            $confirmationPlain
        );
    }

    header('Location: index.php?success=1#interest');
    exit;

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Trip interest submission failed: '
        . $e->getMessage()
    );

    header('Location: index.php?error=1#interest');
    exit;
}
