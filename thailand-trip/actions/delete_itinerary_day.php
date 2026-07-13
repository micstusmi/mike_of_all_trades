<?php

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/trip_auth.php';

requireTripAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../itinerary_editor.php');
    exit;
}

$tripId = (int) $_SESSION['trip_id'];
$dayId = (int) ($_POST['day_id'] ?? 0);

if ($dayId > 0) {
    $stmt = $pdo->prepare("
        DELETE FROM trip_days
        WHERE id = ?
          AND trip_id = ?
    ");

    $stmt->execute([
        $dayId,
        $tripId
    ]);

    $_SESSION['itinerary_message'] =
        'Itinerary day deleted.';
}

header('Location: ../itinerary_editor.php');
exit;
