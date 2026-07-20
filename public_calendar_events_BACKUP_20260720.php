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
        'title' => $isBuffer ? '🚗 travel' : 'Unavailable',
        'classNames' => $isBuffer ? ['travel-buffer-event'] : [],
        'start' => str_replace(' ', 'T', $row['start_datetime']),
        'end' => str_replace(' ', 'T', $row['end_datetime']),
        'backgroundColor' => $isBuffer ? '#d9d9d9' : '#999999',
        'borderColor' => $isBuffer ? '#cccccc' : '#999999',
        'textColor' => $isBuffer ? '#333333' : '#ffffff',
        'editable' => false
    ];
}

echo json_encode($events);