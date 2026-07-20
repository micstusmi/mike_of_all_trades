<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_admin.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/google_calendar.php';

header('Content-Type: application/json; charset=utf-8');

$events = [];

try {
    /*
     * Existing website calendar events.
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
            'title' => $row['title'],
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
            'backgroundColor' => $row['color'],
            'borderColor' => $row['color'],
            'classNames' => $isBuffer
                ? ['buffer-event']
                : [],
            'editable' => !$isBuffer,
            'extendedProps' => [
                'source' => 'database',
                'notes' => $row['notes'],
                'event_type' => $row['event_type'],
                'is_buffer' => (int)$row['is_buffer'],
                'parent_event_id' => $row['parent_event_id'],
                'buffer_minutes' => (int)$row['buffer_minutes']
            ]
        ];
    }

    /*
     * FullCalendar normally supplies the visible date range as
     * ?start=...&end=...
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
     * Google Calendar events are displayed as private,
     * non-editable availability blocks.
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

        $events[] = $googleEvent;
    }
} catch (Throwable $exception) {
    /*
     * Do not break the whole admin calendar if Google has
     * a temporary problem. Database events still remain visible.
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

        $eventStart = new DateTimeImmutable($eventStartValue);
        $eventEnd = new DateTimeImmutable($eventEndValue);

        if ($start < $eventEnd && $end > $eventStart) {
            return true;
        }
    }

    return false;
}