<?php
require '../includes/auth_admin.php';
require_once '../includes/db.php';

header('Content-Type: application/json');

try {
    $id = (int)($_POST['id'] ?? 0);

    if (!$id) {
        throw new Exception('Missing event ID.');
    }

    $stmt = $pdo->prepare("
        SELECT *
        FROM calendar_events
        WHERE id = ?
        AND is_buffer = 0
        LIMIT 1
    ");

    $stmt->execute([$id]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$event) {
        throw new Exception('Event not found.');
    }

    $start = new DateTime($event['start_datetime']);
    $end = new DateTime($event['end_datetime']);

    $durationHours = ($end->getTimestamp() - $start->getTimestamp()) / 3600;

    echo json_encode([
        'success' => true,
        'event' => [
            'id' => $event['id'],
            'title' => $event['title'],
            'date' => $start->format('Y-m-d'),
            'start_time' => $start->format('H:i'),
            'duration_hours' => rtrim(rtrim(number_format($durationHours, 1), '0'), '.'),
            'event_type' => $event['event_type'],
            'buffer_minutes' => (int)$event['buffer_minutes'],
            'notes' => $event['notes'] ?? ''
        ]
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}