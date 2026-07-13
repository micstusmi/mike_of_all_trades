<?php

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/trip_auth.php';

requireTripLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../planner.php');
    exit;
}

$tripId = (int) $_SESSION['trip_id'];
$memberId = (int) $_SESSION['trip_member_id'];

$fromId = (int) ($_POST['from_person_id'] ?? 0);
$toId = (int) ($_POST['to_person_id'] ?? 0);
$amount = (float) ($_POST['amount'] ?? 0);
$currency = $_POST['currency'] ?? 'THB';
$notes = trim($_POST['notes'] ?? '');

if (
    $fromId <= 0
    || $toId <= 0
    || $fromId === $toId
    || $amount <= 0
    || !in_array($currency, ['THB', 'AUD'], true)
) {
    $_SESSION['planner_message'] =
        'Payment was not recorded. Check the details.';

    header('Location: ../planner.php');
    exit;
}

$stmt = $pdo->prepare("
    INSERT INTO trip_payments (
        trip_id,
        payment_date,
        from_person_id,
        to_person_id,
        amount,
        currency,
        notes,
        created_by_member_id
    ) VALUES (
        :trip_id,
        CURDATE(),
        :from_id,
        :to_id,
        :amount,
        :currency,
        :notes,
        :member_id
    )
");

$stmt->execute([
    'trip_id' => $tripId,
    'from_id' => $fromId,
    'to_id' => $toId,
    'amount' => $amount,
    'currency' => $currency,
    'notes' => $notes ?: null,
    'member_id' => $memberId
]);

$_SESSION['planner_message'] =
    'Payment recorded successfully.';

header('Location: ../planner.php');
exit;
