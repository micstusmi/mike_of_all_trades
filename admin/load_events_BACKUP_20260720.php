<?php
require '../includes/auth_admin.php';
require_once '../includes/db.php';

header('Content-Type: application/json');

$stmt = $pdo->query("
    SELECT *
    FROM calendar_events
    ORDER BY start_datetime ASC
");

$events = [];

foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $events[] = [
        'id' => $row['id'],
        'title' => $row['title'],
        'start' => str_replace(' ', 'T', $row['start_datetime']),
        'end' => str_replace(' ', 'T', $row['end_datetime']),
        'backgroundColor' => $row['color'],
        'borderColor' => $row['color'],
        'classNames' => ((int)$row['is_buffer'] === 1) ? ['buffer-event'] : [],
        'editable' => ((int)$row['is_buffer'] !== 1),
        'extendedProps' => [
            'notes' => $row['notes'],
            'event_type' => $row['event_type'],
            'is_buffer' => (int)$row['is_buffer'],
            'parent_event_id' => $row['parent_event_id'],
            'buffer_minutes' => (int)$row['buffer_minutes']
        ]
    ];
}

echo json_encode($events);