<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/includes/google_calendar.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/zoho_functions.php';

header('Content-Type: application/json; charset=utf-8');

date_default_timezone_set(GOOGLE_CALENDAR_TIMEZONE);

/**
 * Check whether a proposed period overlaps an existing
 * website calendar event.
 */
function hasDatabaseOverlap(
    PDO $pdo,
    DateTimeInterface $startDT,
    DateTimeInterface $endDT
): bool {
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM calendar_events
        WHERE start_datetime < ?
          AND end_datetime > ?
    ");

    $stmt->execute([
        $endDT->format('Y-m-d H:i:s'),
        $startDT->format('Y-m-d H:i:s')
    ]);

    return (int)$stmt->fetchColumn() > 0;
}

/**
 * Retrieve Google Calendar busy periods once for a requested range.
 *
 * @return array<int, array{
 *     start: DateTimeImmutable,
 *     end: DateTimeImmutable
 * }>
 */
function getGoogleBusyIntervals(
    DateTimeInterface $rangeStart,
    DateTimeInterface $rangeEnd
): array {
    $googleEvents = getGoogleEvents(
        $rangeStart->format(DateTimeInterface::RFC3339),
        $rangeEnd->format(DateTimeInterface::RFC3339)
    );

    $busyIntervals = [];

    foreach ($googleEvents as $event) {
        $startValue = $event->getStart()->getDateTime()
            ?: $event->getStart()->getDate();

        $endValue = $event->getEnd()->getDateTime()
            ?: $event->getEnd()->getDate();

        if (!$startValue || !$endValue) {
            continue;
        }

        $busyIntervals[] = [
            'start' => new DateTimeImmutable($startValue),
            'end' => new DateTimeImmutable($endValue)
        ];
    }

    return $busyIntervals;
}

/**
 * Check whether a proposed period overlaps any previously loaded
 * Google Calendar busy interval.
 *
 * @param array<int, array{
 *     start: DateTimeImmutable,
 *     end: DateTimeImmutable
 * }> $googleBusyIntervals
 */
function hasGoogleOverlap(
    DateTimeInterface $requestedStart,
    DateTimeInterface $requestedEnd,
    array $googleBusyIntervals
): bool {
    foreach ($googleBusyIntervals as $busyInterval) {
        if (
            $requestedStart < $busyInterval['end'] &&
            $requestedEnd > $busyInterval['start']
        ) {
            return true;
        }
    }

    return false;
}

/**
 * Check both the website database and Google Calendar.
 *
 * @param array<int, array{
 *     start: DateTimeImmutable,
 *     end: DateTimeImmutable
 * }> $googleBusyIntervals
 */
function hasAnyAvailabilityConflict(
    PDO $pdo,
    DateTimeInterface $start,
    DateTimeInterface $end,
    array $googleBusyIntervals
): bool {
    if ($end <= $start) {
        throw new InvalidArgumentException(
            'Availability end must be after the start.'
        );
    }

    if (hasDatabaseOverlap($pdo, $start, $end)) {
        return true;
    }

    return hasGoogleOverlap(
        $start,
        $end,
        $googleBusyIntervals
    );
}

/**
 * Insert the main customer booking and its before/after travel buffers.
 */
function insertBookingBlock(
    PDO $pdo,
    string $title,
    string $description,
    DateTimeInterface $startDT,
    DateTimeInterface $endDT,
    int $buffer,
    int $userId,
    string $bookingGroupId,
    string $bookingMode,
    float $blockHours
): int {
    $stmt = $pdo->prepare("
        INSERT INTO calendar_events
        (
            title,
            notes,
            event_type,
            start_datetime,
            end_datetime,
            color,
            is_buffer,
            parent_event_id,
            buffer_minutes,
            created_by,
            customer_id,
            booking_group_id,
            booking_mode,
            billable_hours
        )
        VALUES
        (
            ?,
            ?,
            'customer_booking',
            ?,
            ?,
            '#0d6efd',
            0,
            NULL,
            ?,
            'customer',
            ?,
            ?,
            ?,
            ?
        )
    ");

    $stmt->execute([
        $title,
        $description,
        $startDT->format('Y-m-d H:i:s'),
        $endDT->format('Y-m-d H:i:s'),
        $buffer,
        $userId,
        $bookingGroupId,
        $bookingMode,
        $blockHours
    ]);

    $parentId = (int)$pdo->lastInsertId();

    $bufferStmt = $pdo->prepare("
        INSERT INTO calendar_events
        (
            title,
            notes,
            event_type,
            start_datetime,
            end_datetime,
            color,
            is_buffer,
            parent_event_id,
            buffer_minutes,
            created_by,
            customer_id,
            booking_group_id,
            booking_mode
        )
        VALUES
        (
            ?,
            ?,
            'buffer',
            ?,
            ?,
            '#999999',
            1,
            ?,
            ?,
            'system',
            ?,
            ?,
            ?
        )
    ");

    $beforeStart = DateTime::createFromInterface($startDT);
    $beforeStart->modify("-{$buffer} minutes");

    $bufferStmt->execute([
        'Driving / buffer time',
        'Automatic buffer before customer booking',
        $beforeStart->format('Y-m-d H:i:s'),
        $startDT->format('Y-m-d H:i:s'),
        $parentId,
        $buffer,
        $userId,
        $bookingGroupId,
        $bookingMode
    ]);

    $afterEnd = DateTime::createFromInterface($endDT);
    $afterEnd->modify("+{$buffer} minutes");

    $bufferStmt->execute([
        'Driving / buffer time',
        'Automatic buffer after customer booking',
        $endDT->format('Y-m-d H:i:s'),
        $afterEnd->format('Y-m-d H:i:s'),
        $parentId,
        $buffer,
        $userId,
        $bookingGroupId,
        $bookingMode
    ]);

    return $parentId;
}

/**
 * Find suitable weekday blocks for a multi-day booking.
 *
 * @param array<int, array{
 *     start: DateTimeImmutable,
 *     end: DateTimeImmutable
 * }> $googleBusyIntervals
 *
 * @return array<int, array{
 *     start: DateTime,
 *     end: DateTime,
 *     minutes: int
 * }>
 */
function buildDayModeBlocks(
    PDO $pdo,
    DateTime $startDT,
    float $requiredHours,
    int $buffer,
    array $googleBusyIntervals
): array {
    $blocks = [];
    $remainingMinutes = (int)round($requiredHours * 60);

    $day = clone $startDT;
    $day->setTime(8, 0, 0);

    $maxDaysToSearch = 60;
    $searchedDays = 0;

    while (
        $remainingMinutes > 0 &&
        $searchedDays < $maxDaysToSearch
    ) {
        $searchedDays++;

        $dayOfWeek = (int)$day->format('N');

        if ($dayOfWeek <= 5) {
            $windows = [
                ['08:00', '12:00'],
                ['13:00', '17:00']
            ];

            foreach ($windows as $window) {
                if ($remainingMinutes <= 0) {
                    break;
                }

                $cursor = new DateTime(
                    $day->format('Y-m-d') . ' ' . $window[0]
                );

                $windowEnd = new DateTime(
                    $day->format('Y-m-d') . ' ' . $window[1]
                );

                while (
                    $cursor < $windowEnd &&
                    $remainingMinutes > 0
                ) {
                    $slotStart = clone $cursor;
                    $slotEnd = clone $cursor;
                    $slotEnd->modify('+30 minutes');

                    if ($slotEnd > $windowEnd) {
                        break;
                    }

                    $checkStart = clone $slotStart;
                    $checkStart->modify("-{$buffer} minutes");

                    $checkEnd = clone $slotEnd;
                    $checkEnd->modify("+{$buffer} minutes");

                    $hasConflict = hasAnyAvailabilityConflict(
                        $pdo,
                        $checkStart,
                        $checkEnd,
                        $googleBusyIntervals
                    );

                    if (!$hasConflict) {
                        $lastIndex = count($blocks) - 1;

                        $isConsecutive = (
                            $lastIndex >= 0 &&
                            $blocks[$lastIndex]['end']->format(
                                'Y-m-d H:i:s'
                            ) === $slotStart->format('Y-m-d H:i:s')
                        );

                        if (!$isConsecutive) {
                            $blocks[] = [
                                'start' => clone $slotStart,
                                'end' => clone $slotEnd,
                                'minutes' => 30
                            ];
                        } else {
                            $blocks[$lastIndex]['end'] = clone $slotEnd;
                            $blocks[$lastIndex]['minutes'] += 30;
                        }

                        $remainingMinutes -= 30;
                    }

                    $cursor->modify('+30 minutes');
                }
            }
        }

        $day->modify('+1 day');
        $day->setTime(8, 0, 0);
    }

    if ($remainingMinutes > 0) {
        throw new RuntimeException(
            'Sorry, there is not enough available time in the next ' .
            '60 weekdays for this multi-day booking.'
        );
    }

    return $blocks;
}

try {
    if (empty($_SESSION['user_id'])) {
        throw new RuntimeException(
            'Please log in before booking.'
        );
    }

    $userId = (int)$_SESSION['user_id'];

    $userStmt = $pdo->prepare("
        SELECT name, email, phone, address
        FROM users
        WHERE id = ?
        LIMIT 1
    ");

    $userStmt->execute([$userId]);

    $user = $userStmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new RuntimeException(
            'Customer account not found.'
        );
    }

    $service = trim(
        (string)($_POST['service'] ?? 'Booking')
    );

    $description = trim(
        (string)($_POST['description'] ?? '')
    );

    $start = trim(
        (string)($_POST['requested_start'] ?? '')
    );

    $end = trim(
        (string)($_POST['requested_end'] ?? '')
    );

    $total = (float)($_POST['total'] ?? 0);

    $bookingMode = (string)(
        $_POST['booking_mode'] ?? 'hours'
    );

    $durationUnits = (float)(
        $_POST['duration_units'] ?? 0
    );

    $billableHours = (float)(
        $_POST['billable_hours'] ?? 0
    );

    $allowedBookingModes = ['hours', 'days'];

    if (!in_array(
        $bookingMode,
        $allowedBookingModes,
        true
    )) {
        throw new InvalidArgumentException(
            'Invalid booking mode.'
        );
    }

    $buffer = 30;

    $bookingGroupId = uniqid(
        'booking_',
        true
    );

    $title = 'Customer Booking - ' . $service;

    if ($start === '' || $end === '') {
        throw new RuntimeException(
            'Please select a booking time first.'
        );
    }

    $timezone = new DateTimeZone(
        GOOGLE_CALENDAR_TIMEZONE
    );

    $startDT = new DateTime($start, $timezone);
    $endDT = new DateTime($end, $timezone);
    $now = new DateTime('now', $timezone);

    if ($startDT < $now) {
        throw new RuntimeException(
            'You cannot create a booking in the past.'
        );
    }

    if ($endDT <= $startDT) {
        throw new InvalidArgumentException(
            'Invalid booking time.'
        );
    }

    if ($billableHours <= 0) {
        throw new InvalidArgumentException(
            'Invalid billable duration.'
        );
    }

    /*
     * Load Google availability once.
     *
     * For multi-day mode, search the same 60-day range used by
     * buildDayModeBlocks(). For a normal booking, only query the
     * immediate buffered booking range.
     */
    try {
        if ($bookingMode === 'days') {
            $googleRangeStart = clone $startDT;
            $googleRangeStart->setTime(0, 0, 0);

            $googleRangeEnd = clone $googleRangeStart;
            $googleRangeEnd->modify('+61 days');
        } else {
            $googleRangeStart = clone $startDT;
            $googleRangeStart->modify("-{$buffer} minutes");

            $googleRangeEnd = clone $endDT;
            $googleRangeEnd->modify("+{$buffer} minutes");
        }

        $googleBusyIntervals = getGoogleBusyIntervals(
            $googleRangeStart,
            $googleRangeEnd
        );
    } catch (Throwable $exception) {
        error_log(
            'Google Calendar booking check failed: ' .
            $exception->getMessage()
        );

        throw new RuntimeException(
            'Availability could not be confirmed right now. ' .
            'Please wait a moment and try again.'
        );
    }

    $bookingIds = [];
    $bookingLines = [];
    $blocks = [];

    /*
     * Perform availability searching before opening the
     * database write transaction.
     */
    if ($bookingMode === 'days') {
        $blocks = buildDayModeBlocks(
            $pdo,
            $startDT,
            $billableHours,
            $buffer,
            $googleBusyIntervals
        );
    } else {
        $bufferStart = clone $startDT;
        $bufferStart->modify("-{$buffer} minutes");

        $bufferEnd = clone $endDT;
        $bufferEnd->modify("+{$buffer} minutes");

        if (hasAnyAvailabilityConflict(
            $pdo,
            $bufferStart,
            $bufferEnd,
            $googleBusyIntervals
        )) {
            throw new RuntimeException(
                'Sorry, that time is no longer available. ' .
                'Please choose another time.'
            );
        }
    }

    $pdo->beginTransaction();

    if ($bookingMode === 'days') {
        /*
         * Recheck database availability just before inserting,
         * in case another website booking was added while this
         * request was being processed.
         */
        foreach ($blocks as $block) {
            $recheckStart = clone $block['start'];
            $recheckStart->modify("-{$buffer} minutes");

            $recheckEnd = clone $block['end'];
            $recheckEnd->modify("+{$buffer} minutes");

            if (hasDatabaseOverlap(
                $pdo,
                $recheckStart,
                $recheckEnd
            )) {
                throw new RuntimeException(
                    'One of the selected times has just become ' .
                    'unavailable. Please try again.'
                );
            }
        }

        foreach ($blocks as $block) {
            $blockHours = $block['minutes'] / 60;

            $bookingIds[] = insertBookingBlock(
                $pdo,
                $title,
                $description,
                $block['start'],
                $block['end'],
                $buffer,
                $userId,
                $bookingGroupId,
                $bookingMode,
                $blockHours
            );

            $bookingLines[] =
                $block['start']->format('l, d/m/Y') .
                ' — ' .
                $block['start']->format('g:i A') .
                ' to ' .
                $block['end']->format('g:i A') .
                ' (' .
                number_format($blockHours, 1) .
                ' hrs)';
        }
    } else {
        /*
         * Recheck the database immediately before insertion.
         */
        $bufferStart = clone $startDT;
        $bufferStart->modify("-{$buffer} minutes");

        $bufferEnd = clone $endDT;
        $bufferEnd->modify("+{$buffer} minutes");

        if (hasDatabaseOverlap(
            $pdo,
            $bufferStart,
            $bufferEnd
        )) {
            throw new RuntimeException(
                'Sorry, that time has just become unavailable. ' .
                'Please choose another time.'
            );
        }

        $blockHours = (
            $endDT->getTimestamp() -
            $startDT->getTimestamp()
        ) / 3600;

        $bookingIds[] = insertBookingBlock(
            $pdo,
            $title,
            $description,
            $startDT,
            $endDT,
            $buffer,
            $userId,
            $bookingGroupId,
            $bookingMode,
            $blockHours
        );

        $bookingLines[] =
            $startDT->format('l, d/m/Y') .
            ' — ' .
            $startDT->format('g:i A') .
            ' to ' .
            $endDT->format('g:i A') .
            ' (' .
            number_format($blockHours, 1) .
            ' hrs)';
    }

    $parentId = $bookingIds[0] ?? null;

    $pdo->commit();

    $bookingScheduleText = implode(
        "\n",
        $bookingLines
    );

    $bookingDetails =
"BOOKING CONFIRMATION

Service: {$service}
Customer: {$user['name']}
Phone: {$user['phone']}
Email: {$user['email']}

Booking Mode: " . strtoupper($bookingMode) . "
Requested Duration: {$durationUnits} " .
($bookingMode === 'days' ? 'day(s)' : 'hour(s)') . "
Billable Hours: {$billableHours}

Booked Schedule:
{$bookingScheduleText}

Notes:
{$description}

Booking Group ID: {$bookingGroupId}";

    $customerId = getOrCreateZohoCustomer(
        $user['name'],
        $user['email'],
        $user['phone'] ?? '',
        $user['address'] ?? ''
    );

    if (!$customerId) {
        throw new RuntimeException(
            'Booking saved, but the Zoho customer could not be ' .
            'created or found.'
        );
    }

    $estimate = createZohoEstimate(
        $customerId,
        $user['name'],
        $bookingDetails,
        $total
    );

    if (($estimate['code'] ?? 0) >= 400) {
        throw new RuntimeException(
            'Booking saved, but the Zoho estimate failed: ' .
            ($estimate['raw'] ?? 'Unknown Zoho error')
        );
    }

    $estimateId =
        $estimate['json']['estimate']['estimate_id']
        ?? null;

    if (!$estimateId) {
        throw new RuntimeException(
            'Booking saved, but no Zoho estimate ID was returned.'
        );
    }

    $updateZohoStmt = $pdo->prepare("
        UPDATE calendar_events
        SET zoho_estimate_id = ?
        WHERE booking_group_id = ?
    ");

    $updateZohoStmt->execute([
        $estimateId,
        $bookingGroupId
    ]);

    $send = sendZohoBookingEstimate(
        $estimateId,
        $user['email']
    );

    if (($send['code'] ?? 0) >= 400) {
        throw new RuntimeException(
            'Booking saved and the Zoho estimate was created, ' .
            'but the Zoho email failed: ' .
            ($send['raw'] ?? 'Unknown Zoho send error')
        );
    }

    echo json_encode([
        'success' => true,
        'booking_id' => $parentId,
        'booking_group_id' => $bookingGroupId,
        'estimate_id' => $estimateId
    ], JSON_UNESCAPED_SLASHES);

} catch (Throwable $exception) {
    if (
        isset($pdo) &&
        $pdo instanceof PDO &&
        $pdo->inTransaction()
    ) {
        $pdo->rollBack();
    }

    error_log(
        'Booking creation failed: ' .
        $exception->getMessage()
    );

    echo json_encode([
        'success' => false,
        'message' => $exception->getMessage()
    ], JSON_UNESCAPED_SLASHES);
}