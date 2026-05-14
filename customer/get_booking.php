<?php
require '../includes/auth_user.php';
require_once '../includes/db.php';

header('Content-Type: application/json');

try {
    $userId = (int)$_SESSION['user_id'];
    $id = (int)($_POST['id'] ?? 0);

    if (!$id) {
        throw new Exception('Missing booking ID.');
    }

    $stmt = $pdo->prepare("
        SELECT *
        FROM calendar_events
        WHERE id = ?
        AND customer_id = ?
        AND is_buffer = 0
        LIMIT 1
    ");

    $stmt->execute([$id, $userId]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        throw new Exception('Booking not found.');
    }

    $start = new DateTime($booking['start_datetime']);
    $end = new DateTime($booking['end_datetime']);

    $durationHours = ($end->getTimestamp() - $start->getTimestamp()) / 3600;

    echo json_encode([
        'success' => true,
        'booking' => [
            'id' => $booking['id'],
            'date' => $start->format('Y-m-d'),
            'start_time' => $start->format('H:i'),
            'duration_hours' => rtrim(rtrim(number_format($durationHours, 1), '0'), '.'),
            'notes' => $booking['notes'] ?? ''
        ]
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}