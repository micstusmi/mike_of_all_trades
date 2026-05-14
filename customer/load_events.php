<?php
require '../includes/auth_user.php';
require_once '../includes/db.php';

header('Content-Type: application/json');

$currentUserId = (int)($_SESSION['user_id'] ?? 0);

$stmt = $pdo->query("
    SELECT *
    FROM calendar_events
    ORDER BY start_datetime ASC
");

$events = [];

foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {

    $isBuffer = (int)$row['is_buffer'] === 1;
    $isOwnMainBooking = ((int)($row['customer_id'] ?? 0) === $currentUserId && !$isBuffer);

    if ($isOwnMainBooking) {
        $title = 'Your Booking';
        $color = '#0d6efd';
    } elseif ($isBuffer) {
        $title = 'Driving / buffer time';
        $color = '#999999';
    } else {
        $title = 'Unavailable';
        $color = '#999999';
    }

    $events[] = [
        'id' => (string)$row['id'],
        'title' => $title,
        'start' => str_replace(' ', 'T', $row['start_datetime']),
        'end' => str_replace(' ', 'T', $row['end_datetime']),
        'backgroundColor' => $color,
        'borderColor' => $color,
        'editable' => false,
        'extendedProps' => [
            'is_own_booking' => $isOwnMainBooking ? 1 : 0,
            'is_buffer' => $isBuffer ? 1 : 0
        ]
    ];
}

echo json_encode($events);