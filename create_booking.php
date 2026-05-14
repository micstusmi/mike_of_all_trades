<?php
session_start();

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/zoho_functions.php';

header('Content-Type: application/json');

try {
    if (empty($_SESSION['user_id'])) {
        throw new Exception('Please log in before booking.');
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
        throw new Exception('Customer account not found.');
    }

    $service = trim($_POST['service'] ?? 'Booking');
    $description = trim($_POST['description'] ?? '');
    $start = $_POST['requested_start'] ?? '';
    $end = $_POST['requested_end'] ?? '';
    $total = (float)($_POST['total'] ?? 0);

    $bookingMode = $_POST['booking_mode'] ?? 'hours';
    $durationUnits = (float)($_POST['duration_units'] ?? 0);
    $billableHours = (float)($_POST['billable_hours'] ?? 0);

    $buffer = 30;
    $bookingGroupId = uniqid('booking_', true);
    $title = 'Customer Booking - ' . $service;

    if (!$start || !$end) {
        throw new Exception('Please select a booking time first.');
    }

    $startDT = new DateTime($start);
    $endDT = new DateTime($end);
    $now = new DateTime('now');

    if ($startDT < $now) {
        throw new Exception('You cannot create a booking in the past.');
    }

    if ($endDT <= $startDT) {
        throw new Exception('Invalid booking time.');
    }

    function hasOverlap($pdo, $startDT, $endDT) {
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

    function insertBookingBlock($pdo, $title, $description, $startDT, $endDT, $buffer, $userId, $bookingGroupId, $bookingMode, $blockHours) {
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
            (?, ?, 'customer_booking', ?, ?, '#0d6efd', 0, NULL, ?, 'customer', ?, ?, ?, ?)
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

        $parentId = $pdo->lastInsertId();

        $bufferStmt = $pdo->prepare("
            INSERT INTO calendar_events
            (title, notes, event_type, start_datetime, end_datetime, color, is_buffer, parent_event_id, buffer_minutes, created_by, customer_id, booking_group_id, booking_mode)
            VALUES
            (?, ?, 'buffer', ?, ?, '#999999', 1, ?, ?, 'system', ?, ?, ?)
        ");

        $beforeStart = clone $startDT;
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

        $afterEnd = clone $endDT;
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

    function buildDayModeBlocks($pdo, $startDT, $requiredHours, $buffer) {
        $blocks = [];
        $remainingMinutes = (int)round($requiredHours * 60);

        $day = clone $startDT;
        $day->setTime(8, 0, 0);

        $maxDaysToSearch = 60;
        $searchedDays = 0;

        while ($remainingMinutes > 0 && $searchedDays < $maxDaysToSearch) {
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

                    $cursor = new DateTime($day->format('Y-m-d') . ' ' . $window[0]);
                    $windowEnd = new DateTime($day->format('Y-m-d') . ' ' . $window[1]);

                    while ($cursor < $windowEnd && $remainingMinutes > 0) {
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

                        if (!hasOverlap($pdo, $checkStart, $checkEnd)) {
                            if (empty($blocks) || end($blocks)['end']->format('Y-m-d H:i:s') !== $slotStart->format('Y-m-d H:i:s')) {
                                $blocks[] = [
                                    'start' => clone $slotStart,
                                    'end' => clone $slotEnd,
                                    'minutes' => 30
                                ];
                            } else {
                                $lastIndex = count($blocks) - 1;
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
            throw new Exception('Sorry, there is not enough available time in the next 60 weekdays for this multi-day booking.');
        }

        return $blocks;
    }

    $pdo->beginTransaction();

    $bookingIds = [];
    $bookingLines = [];

    if ($bookingMode === 'days') {
        $blocks = buildDayModeBlocks($pdo, $startDT, $billableHours, $buffer);

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
                ' (' . number_format($blockHours, 1) . ' hrs)';
        }
    } else {
        $bufferStart = clone $startDT;
        $bufferStart->modify("-{$buffer} minutes");

        $bufferEnd = clone $endDT;
        $bufferEnd->modify("+{$buffer} minutes");

        if (hasOverlap($pdo, $bufferStart, $bufferEnd)) {
            throw new Exception('Sorry, that time is no longer available. Please choose another time.');
        }

        $blockHours = ($endDT->getTimestamp() - $startDT->getTimestamp()) / 3600;

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
            ' (' . number_format($blockHours, 1) . ' hrs)';
    }

    $parentId = $bookingIds[0] ?? null;

    $pdo->commit();

    $bookingScheduleText = implode("\n", $bookingLines);

    $bookingDetails =
"BOOKING CONFIRMATION

Service: {$service}
Customer: {$user['name']}
Phone: {$user['phone']}
Email: {$user['email']}

Booking Mode: " . strtoupper($bookingMode) . "
Requested Duration: {$durationUnits} " . ($bookingMode === 'days' ? 'day(s)' : 'hour(s)') . "
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
        throw new Exception('Booking saved, but Zoho customer could not be created/found.');
    }

    $estimate = createZohoEstimate(
        $customerId,
        $user['name'],
        $bookingDetails,
        $total
    );

    if (($estimate['code'] ?? 0) >= 400) {
        throw new Exception('Booking saved, but Zoho estimate failed: ' . ($estimate['raw'] ?? 'Unknown Zoho error'));
    }

    $estimateId = $estimate['json']['estimate']['estimate_id'] ?? null;

    if (!$estimateId) {
        throw new Exception('Booking saved, but no Zoho estimate ID returned.');
    }

    $updateZohoStmt = $pdo->prepare("
        UPDATE calendar_events
        SET zoho_estimate_id = ?
        WHERE booking_group_id = ?
    ");
    $updateZohoStmt->execute([$estimateId, $bookingGroupId]);

    $send = sendZohoBookingEstimate($estimateId, $user['email']);

    if (($send['code'] ?? 0) >= 400) {
        throw new Exception('Booking saved and Zoho estimate created, but Zoho email failed: ' . ($send['raw'] ?? 'Unknown Zoho send error'));
    }

    echo json_encode([
        'success' => true,
        'booking_id' => $parentId,
        'booking_group_id' => $bookingGroupId,
        'estimate_id' => $estimateId
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}