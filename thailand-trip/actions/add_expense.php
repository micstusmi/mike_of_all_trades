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

$description = trim($_POST['description'] ?? '');
$amount = (float) ($_POST['amount'] ?? 0);
$currency = $_POST['currency'] ?? 'THB';
$paidBy = (int) ($_POST['paid_by_person_id'] ?? 0);
$weekId = (int) ($_POST['trip_week_id'] ?? 0);
$notes = trim($_POST['notes'] ?? '');
$personIds = array_values(array_unique(array_map(
    'intval',
    $_POST['people'] ?? []
)));

if (
    $description === ''
    || $amount <= 0
    || $paidBy <= 0
    || !$personIds
    || !in_array($currency, ['THB', 'AUD'], true)
) {
    $_SESSION['planner_message'] =
        'Expense was not added. Complete all required fields.';

    header('Location: ../planner.php');
    exit;
}

$shareAmount = round($amount / count($personIds), 2);

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        INSERT INTO trip_expenses (
            trip_id,
            trip_week_id,
            expense_date,
            description,
            amount,
            currency,
            paid_by_person_id,
            notes,
            created_by_member_id
        ) VALUES (
            :trip_id,
            :trip_week_id,
            CURDATE(),
            :description,
            :amount,
            :currency,
            :paid_by,
            :notes,
            :member_id
        )
    ");

    $stmt->execute([
        'trip_id' => $tripId,
        'trip_week_id' => $weekId ?: null,
        'description' => $description,
        'amount' => $amount,
        'currency' => $currency,
        'paid_by' => $paidBy,
        'notes' => $notes ?: null,
        'member_id' => $memberId
    ]);

    $expenseId = (int) $pdo->lastInsertId();

    $shareStmt = $pdo->prepare("
        INSERT INTO trip_expense_people (
            expense_id,
            person_id,
            share_amount
        ) VALUES (?, ?, ?)
    ");

    $remaining = $amount;

    foreach ($personIds as $index => $personId) {
        $share = $index === count($personIds) - 1
            ? $remaining
            : $shareAmount;

        $shareStmt->execute([
            $expenseId,
            $personId,
            $share
        ]);

        $remaining -= $share;
    }

    $pdo->commit();

    $_SESSION['planner_message'] =
        'Expense added successfully.';

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log($e->getMessage());

    $_SESSION['planner_message'] =
        'The expense could not be saved.';
}

header('Location: ../planner.php');
exit;
