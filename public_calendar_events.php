<?php
require_once __DIR__ . '/includes/db.php';

header('Content-Type: application/json');

$stmt = $pdo->query("
    SELECT *
    FROM calendar_events
    ORDER BY start_datetime ASC
");

$events = [];

foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $isBuffer = (int)$row['is_buffer'] === 1;

    $events[] = [
        'id' => 'busy-' . $row['id'],
        'title' => $isBuffer ? 'Driving / buffer time' : 'Unavailable',
        'start' => str_replace(' ', 'T', $row['start_datetime']),
        'end' => str_replace(' ', 'T', $row['end_datetime']),
        'backgroundColor' => '#999999',
        'borderColor' => '#999999',
        'editable' => false
    ];
}

echo json_encode($events);