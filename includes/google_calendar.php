<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/google_calendar_config.php';

use Google\Client;
use Google\Service\Calendar;

function getGoogleCalendarService(): Calendar
{
    $client = new Client();

    $client->setApplicationName('Mike Of All Trades');

    $client->setAuthConfig(
        GOOGLE_CALENDAR_CREDENTIALS_PATH
    );

    $client->setScopes([
        Calendar::CALENDAR_READONLY
    ]);

    return new Calendar($client);
}
function getGoogleEvents(
    string $timeMin,
    string $timeMax
): array
{
    $service = getGoogleCalendarService();

    $events = $service->events->listEvents(
        GOOGLE_AVAILABILITY_CALENDAR_ID,
        [
            'timeMin' => $timeMin,
            'timeMax' => $timeMax,
            'singleEvents' => true,
            'orderBy' => 'startTime'
        ]
    );

    return $events->getItems();
}
/**
 * Convert Google Calendar events into FullCalendar unavailable blocks.
 *
 * @return array<int, array<string, mixed>>
 */
function getGoogleFullCalendarEvents(
    string $timeMin,
    string $timeMax
): array {
    $googleEvents = getGoogleEvents($timeMin, $timeMax);
    $calendarEvents = [];

    foreach ($googleEvents as $event) {
        $start = $event->getStart()->getDateTime()
            ?: $event->getStart()->getDate();

        $end = $event->getEnd()->getDateTime()
            ?: $event->getEnd()->getDate();

        if (!$start || !$end) {
            continue;
        }

        $calendarEvents[] = [
            'id' => 'google-' . $event->getId(),
            'title' => 'Unavailable',
            'start' => $start,
            'end' => $end,
            'editable' => false,
            'startEditable' => false,
            'durationEditable' => false,
            'overlap' => false,
            'classNames' => [
                'google-calendar-unavailable'
            ],
            'extendedProps' => [
                'source' => 'google_calendar',
                'external' => true,
                'private' => true
            ]
        ];
    }

    return $calendarEvents;
}
