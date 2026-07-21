<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_admin.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/google_calendar.php';

header('Content-Type: application/json; charset=utf-8');

$events = [];

try {
    /*
     * Load website/database calendar events.
     */
    $stmt = $pdo->query("
        SELECT *
        FROM calendar_events
        ORDER BY start_datetime ASC
    ");

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $isBuffer = (int)$row['is_buffer'] === 1;

        $events[] = [
            'id' => (string)$row['id'],

            'title' => $isBuffer
                ? '🚗 travel'
                : $row['title'],

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
                : $row['color'],

            'borderColor' => $isBuffer
                ? '#cccccc'
                : $row['color'],

            'textColor' => $isBuffer
                ? '#333333'
                : '#ffffff',

            /*
             * Keep both class names for compatibility with
             * the older admin calendar styling and the newer
             * travel-buffer styling.
             */
            'classNames' => $isBuffer
                ? [
                    'buffer-event',
                    'travel-buffer-event'
                ]
                : [],

            'editable' => !$isBuffer,
            'startEditable' => !$isBuffer,
            'durationEditable' => !$isBuffer,
            'overlap' => false,

            'extendedProps' => [
                'source' => 'database',
                'notes' => $row['notes'],
                'event_type' => $row['event_type'],
                'is_buffer' => $isBuffer,
                'parent_event_id' => $row['parent_event_id'],
                'buffer_minutes' => (int)$row['buffer_minutes']
            ]
        ];
    }

    /*
     * FullCalendar normally supplies the currently visible
     * date range as ?start=...&end=...
     */
    $timezone = new DateTimeZone(
        GOOGLE_CALENDAR_TIMEZONE
    );

    $rangeStart = !empty($_GET['start'])
        ? new DateTimeImmutable($_GET['start'])
        : new DateTimeImmutable('now', $timezone);

    $rangeEnd = !empty($_GET['end'])
        ? new DateTimeImmutable($_GET['end'])
        : $rangeStart->modify('+90 days');

    /*
     * Load Google Calendar events as private, non-editable
     * unavailable blocks.
     */
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
        $googleEvent['startEditable'] = false;
        $googleEvent['durationEditable'] = false;
        $googleEvent['overlap'] = false;

        $events[] = $googleEvent;
    }
} catch (Throwable $exception) {
    /*
     * If Google Calendar temporarily fails, keep returning
     * the database events so the admin calendar still works.
     */
    error_log(
        'Admin calendar event loading failed: ' .
        $exception->getMessage()
    );
}

echo json_encode(
    $events,
    JSON_UNESCAPED_SLASHES |
    JSON_UNESCAPED_UNICODE
);

/**
 * Check whether a proposed booking overlaps a Google Calendar event.
 */
function overlapsGoogleCalendar(
    string $requestedStart,
    string $requestedEnd
): bool {
    $start = new DateTimeImmutable($requestedStart);
    $end = new DateTimeImmutable($requestedEnd);

    if ($end <= $start) {
        throw new InvalidArgumentException(
            'Requested end time must be after the start time.'
        );
    }

    $events = getGoogleEvents(
        $start->format(DateTimeInterface::RFC3339),
        $end->format(DateTimeInterface::RFC3339)
    );

    foreach ($events as $event) {
        $eventStartValue =
            $event->getStart()->getDateTime()
            ?: $event->getStart()->getDate();

        $eventEndValue =
            $event->getEnd()->getDateTime()
            ?: $event->getEnd()->getDate();

        if (!$eventStartValue || !$eventEndValue) {
            continue;
        }

        $eventStart = new DateTimeImmutable(
            $eventStartValue
        );

        $eventEnd = new DateTimeImmutable(
            $eventEndValue
        );

        if (
            $start < $eventEnd &&
            $end > $eventStart
        ) {
            return true;
        }
    }

    return false;
}