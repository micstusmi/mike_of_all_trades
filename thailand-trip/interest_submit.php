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
if (trim($_POST['website'] ?? '') !== '') {
    header('Location: index.php?success=1#interest');
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
            'Thailand trip interest: '
            . $name;

        $adminHtml = '
            <h2>New Thailand 2027 response</h2>

            <p>
                <strong>Name:</strong>
                ' . $safeName . '
            </p>

            <p>
                <strong>Status:</strong>
                ' . $safeStatus . '
            </p>

            <p>
                <strong>Mobile:</strong>
                ' . $safePhone . '
            </p>

            <p>
                <strong>Email:</strong>
                ' . $safeEmail . '
            </p>

            <p><strong>Selected weeks:</strong></p>

            <ul>
                ' . $weekHtml . '
            </ul>

            <p>
                <strong>Notes:</strong><br>
                ' . nl2br($safeNotes) . '
            </p>

            <p>
                Open the private Attendees page to manage
                this response.
            </p>
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
