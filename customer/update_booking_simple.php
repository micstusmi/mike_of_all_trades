<?php
require '../includes/auth_user.php';
require_once '../includes/db.php';

header('Content-Type: application/json');

try {
    $userId = (int)$_SESSION['user_id'];

    $id = (int)($_POST['id'] ?? 0);
    $date = $_POST['date'] ?? '';
    $startTime = $_POST['start_time'] ?? '';
    $durationHours = (float)($_POST['duration_hours'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');

    if (!$id || !$date || !$startTime || $durationHours <= 0) {
        throw new Exception('Please complete all booking fields.');
    }

    $startDT = new DateTime($date . ' ' . $startTime);
    $endDT = clone $startDT;

    $durationMinutes = (int)round($durationHours * 60);
    $endDT->modify("+{$durationMinutes} minutes");

    $now = new DateTime();

    if ($startDT < $now) {
        throw new Exception('You cannot move a booking into the past.');
    }

    $stmt = $pdo->prepare("
        SELECT *
        FROM calendar_events
        WHERE id = ?
        AND customer_id = ?
        AND is_buffer = 0
        LIMIT 1
    ");

    $stmt->execute([$id, $userId]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        throw new Exception('You can only edit your own booking.');
    }

    $buffer = (int)($booking['buffer_minutes'] ?? 30);

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

    $pdo->beginTransaction();

    $pdo->prepare("
        UPDATE calendar_events
        SET notes = ?,
            start_datetime = ?,
            end_datetime = ?
        WHERE id = ?
        AND customer_id = ?
        AND is_buffer = 0
    ")->execute([
        $notes,
        $startDT->format('Y-m-d H:i:s'),
        $endDT->format('Y-m-d H:i:s'),
        $id,
        $userId
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

        $beforeStart = clone $startDT;
        $beforeStart->modify("-{$buffer} minutes");

        $bufferStmt->execute([
            'Driving / buffer time',
            'Automatic buffer before customer booking',
            $beforeStart->format('Y-m-d H:i:s'),
            $startDT->format('Y-m-d H:i:s'),
            $id,
            $buffer,
            $userId
        ]);

        $afterEnd = clone $endDT;
        $afterEnd->modify("+{$buffer} minutes");

        $bufferStmt->execute([
            'Driving / buffer time',
            'Automatic buffer after customer booking',
            $endDT->format('Y-m-d H:i:s'),
            $afterEnd->format('Y-m-d H:i:s'),
            $id,
            $buffer,
            $userId
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