<?php
require_once __DIR__ . '/includes/db.php';

header('Content-Type: application/json');

try {
    $workStart = 8;
    $workEnd = 17;
    $slotMinutes = 60;
    $daysToCheck = 30;

    $startDate = new DateTime('today');
    $endDate = new DateTime("+{$daysToCheck} days");

    $stmt = $pdo->prepare("
        SELECT start_datetime, end_datetime
        FROM calendar_events
        WHERE start_datetime < ?
        AND end_datetime > ?
        ORDER BY start_datetime ASC
    ");

    $stmt->execute([
        $endDate->format('Y-m-d 23:59:59'),
        $startDate->format('Y-m-d 00:00:00')
    ]);

    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $nextAvailable = null;
    $busyDaysNext7 = 0;

    for ($i = 0; $i <= $daysToCheck; $i++) {
        $day = clone $startDate;
        $day->modify("+{$i} days");

        $dayOfWeek = (int)$day->format('N');

        // Monday-Friday only
        if ($dayOfWeek >= 6) {
            continue;
        }

        $dayStart = clone $day;
        $dayStart->setTime($workStart, 0);

        $dayEnd = clone $day;
        $dayEnd->setTime($workEnd, 0);

        $busyMinutes = 0;

        foreach ($events as $event) {
            $eventStart = new DateTime($event['start_datetime']);
            $eventEnd = new DateTime($event['end_datetime']);

            if ($eventEnd <= $dayStart || $eventStart >= $dayEnd) {
                continue;
            }

            $overlapStart = max($eventStart, $dayStart);
            $overlapEnd = min($eventEnd, $dayEnd);

            $busyMinutes += max(0, ($overlapEnd->getTimestamp() - $overlapStart->getTimestamp()) / 60);
        }

        if ($i < 7 && $busyMinutes >= 240) {
            $busyDaysNext7++;
        }

        // Find first 1-hour free slot
        for ($hour = $workStart; $hour < $workEnd; $hour++) {
            $slotStart = clone $day;
            $slotStart->setTime($hour, 0);

            $slotEnd = clone $slotStart;
            $slotEnd->modify("+{$slotMinutes} minutes");

            $blocked = false;

            foreach ($events as $event) {
                $eventStart = new DateTime($event['start_datetime']);
                $eventEnd = new DateTime($event['end_datetime']);

                if ($eventStart < $slotEnd && $eventEnd > $slotStart) {
                    $blocked = true;
                    break;
                }
            }

            if (!$blocked) {
                $nextAvailable = clone $slotStart;
                break 2;
            }
        }
    }

    if (!$nextAvailable) {
        echo json_encode([
            'success' => true,
            'summary' => "Mike looks fairly booked over the next {$daysToCheck} days. Would you like to view the calendar by day, week, or month?",
            'next_available' => null
        ]);
        exit;
    }

    if ($busyDaysNext7 >= 4) {
        $busyText = "Mike is fairly busy over the next week.";
    } elseif ($busyDaysNext7 >= 2) {
        $busyText = "Mike has some bookings this week.";
    } else {
        $busyText = "Mike is not too busy at the moment.";
    }

    $summary = $busyText . " His next clear opening looks like " .
        $nextAvailable->format('l j F') . " at " .
        $nextAvailable->format('g:i A') .
        ". Would you like to see the calendar by day, week, or month?";

    echo json_encode([
        'success' => true,
        'summary' => $summary,
        'next_available' => $nextAvailable->format('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}