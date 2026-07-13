<?php

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/trip_auth.php';

requireTripLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../attendance.php');
    exit;
}

$tripId = (int) $_SESSION['trip_id'];
$memberId = (int) $_SESSION['trip_member_id'];

$personStmt = $pdo->prepare("
    SELECT id
    FROM trip_people
    WHERE trip_id = ?
      AND trip_member_id = ?
    LIMIT 1
");

$personStmt->execute([
    $tripId,
    $memberId
]);

$personId = (int) $personStmt->fetchColumn();

if (!$personId) {
    header('Location: ../attendance.php');
    exit;
}

$attendance = $_POST['attendance'] ?? [];

$allowedStatuses = [
    'interested',
    'likely',
    'confirmed',
    'not_coming'
];

$validWeeksStmt = $pdo->prepare("
    SELECT id
    FROM trip_weeks
    WHERE trip_id = ?
");

$validWeeksStmt->execute([$tripId]);

$validWeekIds = array_map(
    'intval',
    $validWeeksStmt->fetchAll(PDO::FETCH_COLUMN)
);

$saveStmt = $pdo->prepare("
    INSERT INTO trip_person_weeks (
        person_id,
        trip_week_id,
        attendance_status
    ) VALUES (
        :person_id,
        :week_id,
        :status
    )
    ON DUPLICATE KEY UPDATE
        attendance_status = VALUES(attendance_status)
");

foreach ($attendance as $weekId => $status) {
    $weekId = (int) $weekId;

    if (
        !in_array($weekId, $validWeekIds, true)
        || !in_array($status, $allowedStatuses, true)
    ) {
        continue;
    }

    $saveStmt->execute([
        'person_id' => $personId,
        'week_id' => $weekId,
        'status' => $status
    ]);
}

$_SESSION['attendance_message'] =
    'Your attendance choices have been saved.';

header('Location: ../attendance.php');
exit;
