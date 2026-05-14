<?php
require '../includes/auth_admin.php';
require_once '../includes/db.php';

header('Content-Type: application/json');

try {
    $id = (int)($_POST['id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $date = $_POST['date'] ?? '';
    $startTime = $_POST['start_time'] ?? '';
    $durationHours = (float)($_POST['duration_hours'] ?? 0);
    $type = $_POST['event_type'] ?? 'work';
    $buffer = (int)($_POST['buffer_minutes'] ?? 30);
    $notes = trim($_POST['notes'] ?? '');

    if (!$id || !$title || !$date || !$startTime || $durationHours <= 0) {
        throw new Exception('Please complete all event fields.');
    }

    $startDT = new DateTime($date . ' ' . $startTime);
    $endDT = clone $startDT;

    $durationMinutes = (int)round($durationHours * 60);
    $endDT->modify("+{$durationMinutes} minutes");

    $now = new DateTime();

    if ($startDT < $now) {
        throw new Exception('You cannot move a block into the past.');
    }

    $stmt = $pdo->prepare("
        SELECT *
        FROM calendar_events
        WHERE id = ?
        AND is_buffer = 0
        LIMIT 1
    ");

    $stmt->execute([$id]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$event) {
        throw new Exception('Event not found.');
    }

    $blockedStart = clone $startDT;
    $blockedStart->modify("-{$buffer} minutes");

    $blockedEnd = clone $endDT;
    $blockedEnd->modify("+{$buffer} minutes");

    $overlap = $pdo->prepare("
        SELECT COUNT(*)
        FROM calendar_events
        WHERE id <> ?
        AND NOT (parent_event_id = ? AND is_buffer = 1)
        AND start_datetime < ?
        AND end_datetime > ?
    ");

    $overlap->execute([
        $id,
        $id,
        $blockedEnd->format('Y-m-d H:i:s'),
        $blockedStart->format('Y-m-d H:i:s')
    ]);

    if ((int)$overlap->fetchColumn() > 0) {
        throw new Exception('That time overlaps with an unavailable block.');
    }

    $color = '#f39200';

    if ($type === 'personal') {
        $color = '#dc3545';
    } elseif ($type === 'customer_booking') {
        $color = '#0d6efd';
    }

    $pdo->beginTransaction();

    $pdo->prepare("
        UPDATE calendar_events
        SET title = ?,
            notes = ?,
            event_type = ?,
            start_datetime = ?,
            end_datetime = ?,
            color = ?,
            buffer_minutes = ?
        WHERE id = ?
        AND is_buffer = 0
    ")->execute([
        $title,
        $notes,
        $type,
        $startDT->format('Y-m-d H:i:s'),
        $endDT->format('Y-m-d H:i:s'),
        $color,
        $buffer,
        $id
    ]);

    $pdo->prepare("
        DELETE FROM calendar_events
        WHERE parent_event_id = ?
        AND is_buffer = 1
    ")->execute([$id]);

    if ($buffer > 0) {
        $bufferStmt = $pdo->prepare("
            INSERT INTO calendar_events
            (title, notes, event_type, start_datetime, end_datetime, color, is_buffer, parent_event_id, buffer_minutes, created_by, customer_id)
            VALUES
            (?, ?, 'buffer', ?, ?, '#999999', 1, ?, ?, 'system', ?)
        ");

        $customerId = $event['customer_id'] ?? null;

        $beforeStart = clone $startDT;
        $beforeStart->modify("-{$buffer} minutes");

        $bufferStmt->execute([
            'Driving / buffer time',
            'Automatic buffer before booking',
            $beforeStart->format('Y-m-d H:i:s'),
            $startDT->format('Y-m-d H:i:s'),
            $id,
            $buffer,
            $customerId
        ]);

        $afterEnd = clone $endDT;
        $afterEnd->modify("+{$buffer} minutes");

        $bufferStmt->execute([
            'Driving / buffer time',
            'Automatic buffer after booking',
            $endDT->format('Y-m-d H:i:s'),
            $afterEnd->format('Y-m-d H:i:s'),
            $id,
            $buffer,
            $customerId
        ]);
    }

    $pdo->commit();

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}