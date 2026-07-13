<?php

require_once __DIR__ . '/../includes/db.php';

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
