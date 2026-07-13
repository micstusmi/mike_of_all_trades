<?php

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/trip_auth.php';

requireTripAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../members.php');
    exit;
}

$tripId = (int) $_SESSION['trip_id'];

$name = trim($_POST['name'] ?? '');
$phone = preg_replace(
    '/[^0-9+]/',
    '',
    trim($_POST['phone'] ?? '')
);

$pin = trim($_POST['pin'] ?? '');
$role = $_POST['role'] ?? 'viewer';

$allowedRoles = [
    'viewer',
    'editor',
    'admin'
];

if (
    $name === ''
    || $phone === ''
    || !preg_match('/^[0-9]{4,8}$/', $pin)
    || !in_array($role, $allowedRoles, true)
) {
    $_SESSION['member_error'] =
        'Please enter a valid name, phone number and 4–8 digit PIN.';

    header('Location: ../members.php');
    exit;
}

$duplicateStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM trip_members
    WHERE trip_id = ?
      AND phone = ?
");

$duplicateStmt->execute([
    $tripId,
    $phone
]);

if ((int) $duplicateStmt->fetchColumn() > 0) {
    $_SESSION['member_error'] =
        'That mobile number already has a trip login.';

    header('Location: ../members.php');
    exit;
}

try {
    $pdo->beginTransaction();

    $memberStmt = $pdo->prepare("
        INSERT INTO trip_members (
            trip_id,
            name,
            phone,
            pin_hash,
            role,
            is_active
        ) VALUES (
            :trip_id,
            :name,
            :phone,
            :pin_hash,
            :role,
            1
        )
    ");

    $memberStmt->execute([
        'trip_id' => $tripId,
        'name' => $name,
        'phone' => $phone,
        'pin_hash' => password_hash(
            $pin,
            PASSWORD_DEFAULT
        ),
        'role' => $role
    ]);

    $memberId = (int) $pdo->lastInsertId();

    $personStmt = $pdo->prepare("
        INSERT INTO trip_people (
            trip_id,
            trip_member_id,
            name,
            status
        ) VALUES (
            :trip_id,
            :member_id,
            :name,
            'interested'
        )
        ON DUPLICATE KEY UPDATE
            trip_member_id = VALUES(trip_member_id)
    ");

    $personStmt->execute([
        'trip_id' => $tripId,
        'member_id' => $memberId,
        'name' => $name
    ]);

    $pdo->commit();

    $_SESSION['member_message'] =
        $name . ' now has a group-member login.';

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Trip member creation failed: '
        . $e->getMessage()
    );

    $_SESSION['member_error'] =
        'The traveller account could not be created.';
}

header('Location: ../members.php');
exit;
