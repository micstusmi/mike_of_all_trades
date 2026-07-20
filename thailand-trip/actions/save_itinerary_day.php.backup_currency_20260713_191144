<?php

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/trip_auth.php';

requireTripLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../itinerary_editor.php');
    exit;
}

$tripId = (int) $_SESSION['trip_id'];
$memberId = (int) $_SESSION['trip_member_id'];
$isAdmin = ($_SESSION['trip_role'] ?? '') === 'admin';

$dayId = (int) ($_POST['day_id'] ?? 0);
$weekId = (int) ($_POST['trip_week_id'] ?? 0);
$date = trim($_POST['trip_date'] ?? '');
$title = trim($_POST['title'] ?? '');
$origin = trim($_POST['origin'] ?? '');
$destination = trim($_POST['destination'] ?? '');
$travelMode = $_POST['travel_mode'] ?? 'DRIVE';
$distanceKm = trim($_POST['distance_km'] ?? '');
$driveMinutes = trim($_POST['drive_minutes'] ?? '');
$mapUrl = trim($_POST['map_url'] ?? '');
$summary = trim($_POST['summary'] ?? '');
$estimatedCost =
    trim($_POST['estimated_cost_per_person'] ?? '');

$allowedModes = [
    'DRIVE',
    'TWO_WHEELER',
    'WALK',
    'TRANSIT'
];

$dateObject = DateTime::createFromFormat(
    'Y-m-d',
    $date
);

if (
    !$dateObject
    || $dateObject->format('Y-m-d') !== $date
    || $title === ''
    || !in_array($travelMode, $allowedModes, true)
) {
    $_SESSION['itinerary_error'] =
        'Please enter a valid date, title and travel mode.';

    header(
        'Location: ../itinerary_editor.php'
        . ($dayId ? '?edit=' . $dayId : '?new=1')
    );
    exit;
}

if ($weekId) {
    $weekStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM trip_weeks
        WHERE id = ?
          AND trip_id = ?
    ");

    $weekStmt->execute([
        $weekId,
        $tripId
    ]);

    if ((int) $weekStmt->fetchColumn() === 0) {
        $weekId = 0;
    }
}

$proposedData = [
    'trip_week_id' => $weekId ?: null,
    'trip_date' => $date,
    'title' => $title,
    'origin' => $origin ?: null,
    'destination' => $destination ?: null,
    'travel_mode' => $travelMode,

    'distance_km' =>
        $distanceKm !== ''
            ? max(0, (float) $distanceKm)
            : null,

    'drive_minutes' =>
        $driveMinutes !== ''
            ? max(0, (int) $driveMinutes)
            : null,

    'summary' => $summary ?: null,
    'map_url' => $mapUrl ?: null,

    'estimated_cost_per_person' =>
        $estimatedCost !== ''
            ? max(0, (float) $estimatedCost)
            : null
];

$originalData = null;

if ($dayId) {
    $currentStmt = $pdo->prepare("
        SELECT *
        FROM trip_days
        WHERE id = ?
          AND trip_id = ?
        LIMIT 1
    ");

    $currentStmt->execute([
        $dayId,
        $tripId
    ]);

    $currentDay = $currentStmt->fetch(PDO::FETCH_ASSOC);

    if (!$currentDay) {
        $_SESSION['itinerary_error'] =
            'The itinerary day could not be found.';

        header('Location: ../itinerary_editor.php');
        exit;
    }

    $originalData = [
        'trip_week_id' => $currentDay['trip_week_id'],
        'trip_date' => $currentDay['trip_date'],
        'title' => $currentDay['title'],
        'origin' => $currentDay['origin'],
        'destination' => $currentDay['destination'],
        'travel_mode' => $currentDay['travel_mode'],
        'distance_km' => $currentDay['distance_km'],
        'drive_minutes' => $currentDay['drive_minutes'],
        'summary' => $currentDay['summary'],
        'map_url' => $currentDay['map_url'],
        'estimated_cost_per_person' =>
            $currentDay['estimated_cost_per_person']
    ];
}

/*
 * Administrator changes are applied immediately.
 */
if ($isAdmin) {
    if ($dayId) {
        $stmt = $pdo->prepare("
            UPDATE trip_days
            SET
                trip_week_id = :week_id,
                trip_date = :trip_date,
                title = :title,
                origin = :origin,
                destination = :destination,
                travel_mode = :travel_mode,
                distance_km = :distance_km,
                drive_minutes = :drive_minutes,
                summary = :summary,
                map_url = :map_url,
                estimated_cost_per_person = :estimated_cost,
                updated_by_member_id = :member_id
            WHERE id = :day_id
              AND trip_id = :trip_id
        ");

        $stmt->execute([
            'week_id' => $proposedData['trip_week_id'],
            'trip_date' => $proposedData['trip_date'],
            'title' => $proposedData['title'],
            'origin' => $proposedData['origin'],
            'destination' => $proposedData['destination'],
            'travel_mode' => $proposedData['travel_mode'],
            'distance_km' => $proposedData['distance_km'],
            'drive_minutes' => $proposedData['drive_minutes'],
            'summary' => $proposedData['summary'],
            'map_url' => $proposedData['map_url'],
            'estimated_cost' =>
                $proposedData['estimated_cost_per_person'],
            'member_id' => $memberId,
            'day_id' => $dayId,
            'trip_id' => $tripId
        ]);

        $_SESSION['itinerary_message'] =
            'Itinerary day updated successfully.';
    } else {
        $sortStmt = $pdo->prepare("
            SELECT COALESCE(MAX(sort_order), 0) + 1
            FROM trip_days
            WHERE trip_id = ?
              AND trip_date = ?
        ");

        $sortStmt->execute([
            $tripId,
            $date
        ]);

        $sortOrder = (int) $sortStmt->fetchColumn();

        $stmt = $pdo->prepare("
            INSERT INTO trip_days (
                trip_id,
                trip_week_id,
                trip_date,
                title,
                origin,
                destination,
                travel_mode,
                distance_km,
                drive_minutes,
                summary,
                map_url,
                estimated_cost_per_person,
                sort_order,
                created_by_member_id,
                updated_by_member_id
            ) VALUES (
                :trip_id,
                :week_id,
                :trip_date,
                :title,
                :origin,
                :destination,
                :travel_mode,
                :distance_km,
                :drive_minutes,
                :summary,
                :map_url,
                :estimated_cost,
                :sort_order,
                :member_id,
                :member_id
            )
        ");

        $stmt->execute([
            'trip_id' => $tripId,
            'week_id' => $proposedData['trip_week_id'],
            'trip_date' => $proposedData['trip_date'],
            'title' => $proposedData['title'],
            'origin' => $proposedData['origin'],
            'destination' => $proposedData['destination'],
            'travel_mode' => $proposedData['travel_mode'],
            'distance_km' => $proposedData['distance_km'],
            'drive_minutes' => $proposedData['drive_minutes'],
            'summary' => $proposedData['summary'],
            'map_url' => $proposedData['map_url'],
            'estimated_cost' =>
                $proposedData['estimated_cost_per_person'],
            'sort_order' => $sortOrder,
            'member_id' => $memberId
        ]);

        $_SESSION['itinerary_message'] =
            'Itinerary day added successfully.';
    }

    header('Location: ../itinerary_editor.php');
    exit;
}

/*
 * Ordinary travellers submit proposed changes for approval.
 */
$duplicateStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM trip_itinerary_proposals
    WHERE trip_id = ?
      AND submitted_by_member_id = ?
      AND status = 'pending'
      AND (
          trip_day_id = ?
          OR (
              trip_day_id IS NULL
              AND ? = 0
          )
      )
");

$duplicateStmt->execute([
    $tripId,
    $memberId,
    $dayId ?: null,
    $dayId
]);

if ((int) $duplicateStmt->fetchColumn() > 0) {
    $_SESSION['itinerary_error'] =
        'You already have a pending proposal for this itinerary day.';

    header('Location: ../itinerary_editor.php');
    exit;
}

$proposalStmt = $pdo->prepare("
    INSERT INTO trip_itinerary_proposals (
        trip_id,
        trip_day_id,
        proposal_type,
        proposed_data,
        original_data,
        submitted_by_member_id,
        status
    ) VALUES (
        :trip_id,
        :trip_day_id,
        :proposal_type,
        :proposed_data,
        :original_data,
        :member_id,
        'pending'
    )
");

$proposalStmt->execute([
    'trip_id' => $tripId,
    'trip_day_id' => $dayId ?: null,
    'proposal_type' => $dayId ? 'update' : 'create',
    'proposed_data' => json_encode(
        $proposedData,
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
    ),
    'original_data' => $originalData
        ? json_encode(
            $originalData,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
        )
        : null,
    'member_id' => $memberId
]);

$_SESSION['itinerary_message'] =
    'Your proposed change has been sent to Mike for approval.';

header('Location: ../itinerary_editor.php');
exit;
