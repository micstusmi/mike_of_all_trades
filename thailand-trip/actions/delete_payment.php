<?php

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/trip_auth.php';

requireTripAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../planner.php');
    exit;
}

$tripId = (int) $_SESSION['trip_id'];
$paymentId = (int) ($_POST['payment_id'] ?? 0);

if ($paymentId <= 0) {
    $_SESSION['planner_message'] =
        'Invalid payment selected.';

    header('Location: ../planner.php');
    exit;
}

$stmt = $pdo->prepare("
    DELETE FROM trip_payments
    WHERE id = ?
      AND trip_id = ?
");

$stmt->execute([
    $paymentId,
    $tripId
]);

$_SESSION['planner_message'] =
    $stmt->rowCount() > 0
        ? 'Payment deleted successfully.'
        : 'The payment could not be found.';

header('Location: ../planner.php');
exit;
