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
    $total = $_POST['total'] ?? 0;
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

    /*
     * Double-booking protection including buffer window
     */
    $bufferStart = clone $startDT;
    $bufferStart->modify("-{$buffer} minutes");

    $bufferEnd = clone $endDT;
    $bufferEnd->modify("+{$buffer} minutes");

    $overlapStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM calendar_events
        WHERE is_buffer = 0
        AND start_datetime < ?
        AND end_datetime > ?
    ");

    $overlapStmt->execute([
        $bufferEnd->format('Y-m-d H:i:s'),
        $bufferStart->format('Y-m-d H:i:s')
    ]);

    if ((int)$overlapStmt->fetchColumn() > 0) {
        throw new Exception('Sorry, that time is no longer available. Please choose another time.');
    }

    /*
     * Save booking locally first
     */
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

    /*
     * Create Zoho estimate / booking document
     */
    $dateText = $startDT->format('l, d/m/Y');
    $timeText = $startDT->format('g:i A') . ' - ' . $endDT->format('g:i A');

    $bookingDetails =
"BOOKING CONFIRMATION

Service: {$service}
Customer: {$user['name']}
Phone: {$user['phone']}
Email: {$user['email']}

Date: {$dateText}
Time: {$timeText}

Notes:
{$description}

Booking ID: {$parentId}";

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
        WHERE id = ?
    ");
    $updateZohoStmt->execute([$estimateId, $parentId]);

    $send = sendZohoEstimate($estimateId, $user['email']);

    if (($send['code'] ?? 0) >= 400) {
        throw new Exception('Booking saved and Zoho estimate created, but Zoho email failed: ' . ($send['raw'] ?? 'Unknown Zoho send error'));
    }

    echo json_encode([
        'success' => true,
        'booking_id' => $parentId,
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