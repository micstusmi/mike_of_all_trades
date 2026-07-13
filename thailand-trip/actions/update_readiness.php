<?php

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/trip_auth.php';

requireTripAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../planner.php');
    exit;
}

$tripId = (int) $_SESSION['trip_id'];
$memberId = (int) $_SESSION['trip_member_id'];
$itemId = (int) ($_POST['item_id'] ?? 0);
$status = $_POST['status'] ?? '';
$notes = trim($_POST['notes'] ?? '');

$allowedStatuses = [
    'not_started',
    'researching',
    'shortlisted',
    'booked',
    'not_required'
];

if (
    $itemId <= 0
    || !in_array($status, $allowedStatuses, true)
) {
    header('Location: ../planner.php');
    exit;
}

$stmt = $pdo->prepare("
    UPDATE trip_readiness_items
    SET
        status = :status,
        notes = :notes,
        updated_by_member_id = :member_id
    WHERE id = :item_id
      AND trip_id = :trip_id
");

$stmt->execute([
    'status' => $status,
    'notes' => $notes ?: null,
    'member_id' => $memberId,
    'item_id' => $itemId,
    'trip_id' => $tripId
]);

$_SESSION['planner_message'] =
    'Trip-readiness item updated.';

header('Location: ../planner.php#readiness');
exit;
