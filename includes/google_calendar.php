<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/google_calendar_config.php';

use Google\Client;
use Google\Service\Calendar;
use Google\Service\Calendar\Event;

/**
 * Create an authenticated Google Calendar service.
 */
function getGoogleCalendarService(): Calendar
{
    $client = new Client();

    $client->setApplicationName('Mike Of All Trades');

    $client->setAuthConfig(
        GOOGLE_CALENDAR_CREDENTIALS_PATH
    );

    $client->setScopes([
    Calendar::CALENDAR_EVENTS
]);

    return new Calendar($client);
}

/**
 * Return the Google calendars that should block website availability.
 *
 * @return array<int, string>
 */
function getGoogleAvailabilityCalendarIds(): array
{
    $calendarIds = [
        GOOGLE_AVAILABILITY_CALENDAR_ID
    ];

    if (
        defined('GOOGLE_MAIN_CALENDAR_ID') &&
        trim((string) GOOGLE_MAIN_CALENDAR_ID) !== ''
    ) {
        $calendarIds[] = GOOGLE_MAIN_CALENDAR_ID;
    }

    return array_values(
        array_unique(
            array_filter(
                array_map(
                    static fn($calendarId): string =>
                        trim((string) $calendarId),
                    $calendarIds
                )
            )
        )
    );
}

/**
 * Retrieve blocking events from all configured Google calendars.
 *
 * @return array<int, Event>
 */
function getGoogleEvents(
    string $timeMin,
    string $timeMax
): array {
    if (
        defined('GOOGLE_CALENDAR_ENABLED') &&
        GOOGLE_CALENDAR_ENABLED !== true
    ) {
        return [];
    }

    $service = getGoogleCalendarService();
    $allEvents = [];

    foreach (getGoogleAvailabilityCalendarIds() as $calendarId) {
        try {
            $pageToken = null;

            do {
                $options = [
                    'timeMin' => $timeMin,
                    'timeMax' => $timeMax,
                    'singleEvents' => true,
                    'orderBy' => 'startTime',
                    'showDeleted' => false,
                    'maxResults' => 2500
                ];

                if ($pageToken !== null) {
                    $options['pageToken'] = $pageToken;
                }

                $result = $service->events->listEvents(
                    $calendarId,
                    $options
                );

                foreach ($result->getItems() as $event) {
                    if ($event->getStatus() === 'cancelled') {
                        continue;
                    }

                    /*
                     * Google events marked "Free" should not block bookings.
                     */
                    if ($event->getTransparency() === 'transparent') {
                        continue;
                    }

                    $allEvents[] = $event;
                }

                $pageToken = $result->getNextPageToken();
            } while ($pageToken !== null);

        } catch (Throwable $exception) {
            error_log(
                'Google Calendar loading failed for ' .
                $calendarId .
                ': ' .
                $exception->getMessage()
            );
        }
    }

    usort(
        $allEvents,
        static function (Event $eventA, Event $eventB): int {
            $startA =
                $eventA->getStart()->getDateTime()
                ?: $eventA->getStart()->getDate()
                ?: '';

            $startB =
                $eventB->getStart()->getDateTime()
                ?: $eventB->getStart()->getDate()
                ?: '';

            return strcmp($startA, $startB);
        }
    );

    return $allEvents;
}

/**
 * Convert Google events into private FullCalendar unavailable blocks.
 *
 * @return array<int, array<string, mixed>>
 */
function getGoogleFullCalendarEvents(
    string $timeMin,
    string $timeMax
): array {
    $googleEvents = getGoogleEvents(
        $timeMin,
        $timeMax
    );

    $calendarEvents = [];

    foreach ($googleEvents as $index => $event) {
        $start =
            $event->getStart()->getDateTime()
            ?: $event->getStart()->getDate();

        $end =
            $event->getEnd()->getDateTime()
            ?: $event->getEnd()->getDate();

        if (!$start || !$end) {
            continue;
        }

        /*
         * Include the index because separate calendars can occasionally
         * contain events with the same Google event ID.
         */
        $calendarEvents[] = [
            'id' =>
                'google-' .
                $index .
                '-' .
                $event->getId(),

            'title' => 'Unavailable',
            'start' => $start,
            'end' => $end,

            'editable' => false,
            'startEditable' => false,
            'durationEditable' => false,
            'overlap' => false,

            'backgroundColor' => '#777777',
            'borderColor' => '#666666',
            'textColor' => '#ffffff',

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

/**
 * Create a website booking in the dedicated Google availability calendar.
 *
 * @return string Google Calendar event ID
 */
function createGoogleBookingEvent(
    string $summary,
    string $description,
    DateTimeInterface $start,
    DateTimeInterface $end,
    ?string $location = null
): string {
    $service = getGoogleCalendarService();

    $eventData = [
        'summary' => $summary,
        'description' => $description,

        'start' => [
            'dateTime' => $start->format(DateTimeInterface::RFC3339),
            'timeZone' => GOOGLE_CALENDAR_TIMEZONE
        ],

        'end' => [
            'dateTime' => $end->format(DateTimeInterface::RFC3339),
            'timeZone' => GOOGLE_CALENDAR_TIMEZONE
        ],

        /*
         * Mark it as busy so it blocks website availability.
         */
        'transparency' => 'opaque',

        /*
         * Keep the event private when viewed by other calendar users.
         */
        'visibility' => 'private',

        /*
         * Useful internal marker for future syncing.
         */
        'extendedProperties' => [
            'private' => [
                'source' => 'mike_of_all_trades_website'
            ]
        ]
    ];

    if ($location !== null && trim($location) !== '') {
        $eventData['location'] = trim($location);
    }

    $event = new \Google\Service\Calendar\Event(
        $eventData
    );

    $createdEvent = $service->events->insert(
        GOOGLE_AVAILABILITY_CALENDAR_ID,
        $event
    );

    $eventId = $createdEvent->getId();

    if (!$eventId) {
        throw new RuntimeException(
            'Google Calendar created the event but returned no event ID.'
        );
    }

    return $eventId;
}