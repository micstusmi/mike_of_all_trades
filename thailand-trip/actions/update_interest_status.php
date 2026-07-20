<?php

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/trip_auth.php';

requireTripAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../attendees.php');
    exit;
}

$tripId = (int) ($_SESSION['trip_id'] ?? 0);
$responseId = (int) ($_POST['response_id'] ?? 0);
$status = $_POST['attendance_status'] ?? '';

$allowedStatuses = [
    'interested',
    'likely',
    'confirmed',
    'not_coming'
];

if (
    $responseId <= 0
    || !in_array($status, $allowedStatuses, true)
) {
    $_SESSION['attendees_error'] =
        'The attendee status could not be updated.';

    header('Location: ../attendees.php');
    exit;
}

$stmt = $pdo->prepare("
    UPDATE trip_interest_responses
    SET attendance_status = ?
    WHERE id = ?
      AND trip_id = ?
");

$stmt->execute([
    $status,
    $responseId,
    $tripId
]);

if ($stmt->rowCount() > 0) {
    $_SESSION['attendees_message'] =
        'Attendee status updated.';
} else {
    $_SESSION['attendees_error'] =
        'No matching attendee registration was found.';
}

header('Location: ../attendees.php');
exit;
