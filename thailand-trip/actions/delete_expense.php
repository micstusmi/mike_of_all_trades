<?php

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/trip_auth.php';

requireTripAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../planner.php');
    exit;
}

$tripId = (int) $_SESSION['trip_id'];
$expenseId = (int) ($_POST['expense_id'] ?? 0);

if ($expenseId <= 0) {
    $_SESSION['planner_message'] =
        'Invalid expense selected.';

    header('Location: ../planner.php');
    exit;
}

$stmt = $pdo->prepare("
    DELETE FROM trip_expenses
    WHERE id = ?
      AND trip_id = ?
");

$stmt->execute([
    $expenseId,
    $tripId
]);

$_SESSION['planner_message'] =
    $stmt->rowCount() > 0
        ? 'Expense deleted successfully.'
        : 'The expense could not be found.';

header('Location: ../planner.php');
exit;
