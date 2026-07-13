<?php

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/trip_auth.php';

requireTripAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../itinerary_editor.php');
    exit;
}

$tripId = (int) $_SESSION['trip_id'];
$suggestionId = (int) ($_POST['suggestion_id'] ?? 0);
$dayId = (int) ($_POST['trip_day_id'] ?? 0);

if ($suggestionId > 0) {
    $stmt = $pdo->prepare("
        DELETE FROM trip_day_suggestions
        WHERE id = ?
          AND trip_id = ?
    ");

    $stmt->execute([
        $suggestionId,
        $tripId
    ]);
}

$_SESSION['itinerary_message'] =
    'Suggestion removed.';

header(
    'Location: ../itinerary_editor.php'
    . ($dayId ? '#day-' . $dayId : '')
);
exit;
