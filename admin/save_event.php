<?php
require '../includes/auth_admin.php';
require_once '../includes/db.php';

header('Content-Type: application/json');

try {
    $title = $_POST['title'] ?? 'Unavailable';
    $notes = $_POST['notes'] ?? '';
    $type = $_POST['event_type'] ?? 'work';
    $start = $_POST['start'] ?? '';
    $end = $_POST['end'] ?? '';
    $buffer = (int)($_POST['buffer_minutes'] ?? 30);

    if (!$start || !$end) {
        throw new Exception('Missing start or end time');
    }

    $colour = $type === 'work' ? '#f39200' : '#dc3545';

    $startDT = new DateTime($start);
    $endDT = new DateTime($end);

    $now = new DateTime();

if ($startDT < $now) {
    throw new Exception('You cannot create or move a booking into the past.');
}

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        INSERT INTO calendar_events
        (title, notes, event_type, start_datetime, end_datetime, color, is_buffer, buffer_minutes, created_by)
        VALUES (?, ?, ?, ?, ?, ?, 0, ?, 'admin')
    ");

    $stmt->execute([
        $title,
        $notes,
        $type,
        $startDT->format('Y-m-d H:i:s'),
        $endDT->format('Y-m-d H:i:s'),
        $colour,
        $buffer
    ]);

    $parentId = $pdo->lastInsertId();

    if ($buffer > 0) {
        $bufferStmt = $pdo->prepare("
            INSERT INTO calendar_events
            (title, notes, event_type, start_datetime, end_datetime, color, is_buffer, parent_event_id, buffer_minutes, created_by)
            VALUES (?, ?, 'buffer', ?, ?, '#999999', 1, ?, ?, 'admin')
        ");

        $beforeStart = clone $startDT;
        $beforeStart->modify("-{$buffer} minutes");

        $bufferStmt->execute([
            'Driving / buffer time',
            'Before booking',
            $beforeStart->format('Y-m-d H:i:s'),
            $startDT->format('Y-m-d H:i:s'),
            $parentId,
            $buffer
        ]);

        $afterEnd = clone $endDT;
        $afterEnd->modify("+{$buffer} minutes");

        $bufferStmt->execute([
            'Driving / buffer time',
            'After booking',
            $endDT->format('Y-m-d H:i:s'),
            $afterEnd->format('Y-m-d H:i:s'),
            $parentId,
            $buffer
        ]);
    }

    $pdo->commit();

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}