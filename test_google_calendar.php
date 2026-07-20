<?php
declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/includes/google_calendar.php';

echo '<h1>Google Calendar Test</h1>';

try {
    $timezone = new DateTimeZone(
        GOOGLE_CALENDAR_TIMEZONE
    );

    $start = new DateTimeImmutable('now', $timezone);
    $end = $start->modify('+30 days');

    $events = getGoogleEvents(
        $start->format(DateTimeInterface::RFC3339),
        $end->format(DateTimeInterface::RFC3339)
    );

    echo '<h2>Connected successfully!</h2>';

    if (empty($events)) {
        echo '<p>No events found in the next 30 days.</p>';
    }

    foreach ($events as $event) {
        $eventStart = $event->getStart()->getDateTime()
            ?: $event->getStart()->getDate()
            ?: 'Unknown time';

        $summary = $event->getSummary()
            ?: '(Untitled event)';

        echo '<p>';
        echo '<strong>'
            . htmlspecialchars($eventStart, ENT_QUOTES, 'UTF-8')
            . '</strong><br>';

        echo htmlspecialchars(
            $summary,
            ENT_QUOTES,
            'UTF-8'
        );

        echo '</p>';
    }
} catch (Throwable $exception) {
    http_response_code(500);

    echo '<h2>Connection failed</h2>';
    echo '<pre>'
        . htmlspecialchars(
            $exception->getMessage(),
            ENT_QUOTES,
            'UTF-8'
        )
        . '</pre>';
}
