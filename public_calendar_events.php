<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/google_calendar.php';

header('Content-Type: application/json');

$events = [];

try {
    $stmt = $pdo->query("
        SELECT *
        FROM calendar_events
        ORDER BY start_datetime ASC
    ");

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $isBuffer = (int)$row['is_buffer'] === 1;

        $events[] = [
            'id' => 'busy-' . $row['id'],
            'title' => $isBuffer ? '🚗 travel' : 'Unavailable',
            'classNames' => $isBuffer
                ? ['travel-buffer-event']
                : [],
            'start' => str_replace(
                ' ',
                'T',
                $row['start_datetime']
            ),
            'end' => str_replace(
                ' ',
                'T',
                $row['end_datetime']
            ),
            'backgroundColor' => $isBuffer
                ? '#d9d9d9'
                : '#999999',
            'borderColor' => $isBuffer
                ? '#cccccc'
                : '#999999',
            'textColor' => $isBuffer
                ? '#333333'
                : '#ffffff',
            'editable' => false,
            'extendedProps' => [
                'source' => 'database',
                'is_buffer' => $isBuffer
            ]
        ];
    }

    $timezone = new DateTimeZone(
        GOOGLE_CALENDAR_TIMEZONE
    );

    $rangeStart = new DateTimeImmutable(
        'now',
        $timezone
    );

    $rangeEnd = $rangeStart->modify('+180 days');

    $googleEvents = getGoogleFullCalendarEvents(
        $rangeStart->format(DateTimeInterface::RFC3339),
        $rangeEnd->format(DateTimeInterface::RFC3339)
    );

    foreach ($googleEvents as $googleEvent) {
        $googleEvent['title'] = 'Unavailable';
        $googleEvent['backgroundColor'] = '#777777';
        $googleEvent['borderColor'] = '#666666';
        $googleEvent['textColor'] = '#ffffff';
        $googleEvent['editable'] = false;

        $events[] = $googleEvent;
    }
} catch (Throwable $exception) {
    error_log(
        'Public calendar event loading failed: '
        . $exception->getMessage()
    );

    /*
     * Important:
     * If Google fails temporarily, still return the database events
     * rather than breaking the customer calendar completely.
     */
}

echo json_encode(
    $events,
    JSON_UNESCAPED_SLASHES
);