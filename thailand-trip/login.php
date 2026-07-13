<?php

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/trip_auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: access.php');
    exit;
}

$phone = normalisePhoneNumber($_POST['phone'] ?? '');
$pin   = trim($_POST['pin'] ?? '');

if ($phone === '' || $pin === '') {
    $_SESSION['trip_login_error'] =
        'Please enter your mobile number and PIN.';

    header('Location: access.php');
    exit;
}

/*
 * This allows Australian numbers to be entered as either:
 * 0400 000 000
 * +61 400 000 000
 */
$phoneVariations = [$phone];

if (str_starts_with($phone, '0')) {
    $phoneVariations[] = '+61' . substr($phone, 1);
}

if (str_starts_with($phone, '+61')) {
    $phoneVariations[] = '0' . substr($phone, 3);
}

$placeholders = implode(
    ',',
    array_fill(0, count($phoneVariations), '?')
);

$sql = "
    SELECT
        tm.id,
        tm.trip_id,
        tm.name,
        tm.phone,
        tm.pin_hash,
        tm.role,
        t.name AS trip_name
    FROM trip_members tm
    INNER JOIN trips t ON t.id = tm.trip_id
    WHERE tm.phone IN ($placeholders)
      AND tm.is_active = 1
    LIMIT 1
";

$stmt = $pdo->prepare($sql);
$stmt->execute($phoneVariations);

$member = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$member || !password_verify($pin, $member['pin_hash'])) {
    $_SESSION['trip_login_attempts'] =
        ($_SESSION['trip_login_attempts'] ?? 0) + 1;

    /*
     * Small delay makes repeated password guessing slower.
     */
    usleep(500000);

    $_SESSION['trip_login_error'] =
        'The mobile number or PIN was not recognised.';

    header('Location: access.php');
    exit;
}

session_regenerate_id(true);

$_SESSION['trip_member_id']   = (int) $member['id'];
$_SESSION['trip_id']          = (int) $member['trip_id'];
$_SESSION['trip_member_name'] = $member['name'];
$_SESSION['trip_role']        = $member['role'];
$_SESSION['trip_name']        = $member['trip_name'];
$_SESSION['trip_login_time']  = time();

unset($_SESSION['trip_login_attempts']);

header('Location: dashboard.php');
exit;