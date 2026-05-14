<?php
session_start();
require_once __DIR__ . '/includes/db.php';

header('Content-Type: application/json');

try {
    if (empty($_SESSION['user_id'])) {
        throw new Exception('Please log in before booking.');
    }

    $userId = (int)$_SESSION['user_id'];

    $userStmt = $pdo->prepare("SELECT name, email, phone FROM users WHERE id = ? LIMIT 1");
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception('Customer account not found.');
    }

    $service = trim($_POST['service'] ?? 'Booking');
    $description = trim($_POST['description'] ?? '');
    $start = $_POST['requested_start'] ?? '';
    $end = $_POST['requested_end'] ?? '';
    $buffer = 30;

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

    $pdo->beginTransaction();

    $title = 'Customer Booking - ' . $service;

    $stmt = $pdo->prepare("
        INSERT INTO calendar_events
        (title, notes, event_type, start_datetime, end_datetime, color, is_buffer, parent_event_id, buffer_minutes, created_by, customer_id)
        VALUES
        (?, ?, 'customer_booking', ?, ?, '#0d6efd', 0, NULL, ?, 'customer', ?)
    ");

    $stmt->execute([
        $title,
        $description,
        $startDT->format('Y-m-d H:i:s'),
        $endDT->format('Y-m-d H:i:s'),
        $buffer,
        $userId
    ]);

    $parentId = $pdo->lastInsertId();

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
        $parentId,
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
        $parentId,
        $buffer,
        $userId
    ]);

    $pdo->commit();

    $dateText = $startDT->format('l, d/m/Y');
    $timeText = $startDT->format('g:i A') . ' - ' . $endDT->format('g:i A');

    $customerSubject = 'Booking Confirmation - Mike Of All Trades';
    $customerMessage =
"Hi {$user['name']},

Your booking has been received.

Service: {$service}
Date: {$dateText}
Time: {$timeText}
Notes: {$description}

Thanks,
Mike Of All Trades";

    $adminSubject = 'New Customer Booking';
    $adminMessage =
"New booking received.

Customer: {$user['name']}
Email: {$user['email']}
Phone: {$user['phone']}

Service: {$service}
Date: {$dateText}
Time: {$timeText}
Notes: {$description}

Booking ID: {$parentId}";

    $headers = "From: Mike Of All Trades <mike@mikeofalltrades.com.au>\r\n";
    $headers .= "Reply-To: mike@mikeofalltrades.com.au\r\n";

    @mail($user['email'], $customerSubject, $customerMessage, $headers);
    @mail('mike@mikeofalltrades.com.au', $adminSubject, $adminMessage, $headers);

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