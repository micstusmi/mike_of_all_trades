<?php

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/trip_auth.php';

requireTripAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../itinerary_reviews.php');
    exit;
}

$tripId = (int) $_SESSION['trip_id'];
$reviewerId = (int) $_SESSION['trip_member_id'];

$proposalId = (int) ($_POST['proposal_id'] ?? 0);
$decision = $_POST['decision'] ?? '';
$reviewNotes = trim($_POST['review_notes'] ?? '');

if (
    !$proposalId
    || !in_array(
        $decision,
        ['approve', 'reject'],
        true
    )
) {
    header('Location: ../itinerary_reviews.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT *
    FROM trip_itinerary_proposals
    WHERE id = ?
      AND trip_id = ?
      AND status = 'pending'
    LIMIT 1
");

$stmt->execute([
    $proposalId,
    $tripId
]);

$proposal = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$proposal) {
    $_SESSION['review_message'] =
        'That proposal no longer exists or has already been reviewed.';

    header('Location: ../itinerary_reviews.php');
    exit;
}

if ($decision === 'reject') {
    $rejectStmt = $pdo->prepare("
        UPDATE trip_itinerary_proposals
        SET
            status = 'rejected',
            reviewed_by_member_id = ?,
            review_notes = ?,
            reviewed_at = NOW()
        WHERE id = ?
    ");

    $rejectStmt->execute([
        $reviewerId,
        $reviewNotes ?: null,
        $proposalId
    ]);

    $_SESSION['review_message'] =
        'The proposed change was rejected.';

    header('Location: ../itinerary_reviews.php');
    exit;
}

$data = json_decode(
    $proposal['proposed_data'],
    true
);

if (!is_array($data)) {
    $_SESSION['review_message'] =
        'The proposed data could not be read.';

    header('Location: ../itinerary_reviews.php');
    exit;
}

try {
    $pdo->beginTransaction();

    if ($proposal['proposal_type'] === 'update') {
        $dayId = (int) $proposal['trip_day_id'];

        $updateStmt = $pdo->prepare("
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
                updated_by_member_id = :reviewer_id
            WHERE id = :day_id
              AND trip_id = :trip_id
        ");

        $updateStmt->execute([
            'week_id' => $data['trip_week_id'] ?? null,
            'trip_date' => $data['trip_date'],
            'title' => $data['title'],
            'origin' => $data['origin'] ?? null,
            'destination' => $data['destination'] ?? null,
            'travel_mode' =>
                $data['travel_mode'] ?? 'DRIVE',
            'distance_km' =>
                $data['distance_km'] ?? null,
            'drive_minutes' =>
                $data['drive_minutes'] ?? null,
            'summary' =>
                $data['summary'] ?? null,
            'map_url' =>
                $data['map_url'] ?? null,
            'estimated_cost' =>
                $data['estimated_cost_per_person'] ?? null,
            'reviewer_id' => $reviewerId,
            'day_id' => $dayId,
            'trip_id' => $tripId
        ]);
    } else {
        $sortStmt = $pdo->prepare("
            SELECT COALESCE(MAX(sort_order), 0) + 1
            FROM trip_days
            WHERE trip_id = ?
              AND trip_date = ?
        ");

        $sortStmt->execute([
            $tripId,
            $data['trip_date']
        ]);

        $sortOrder = (int) $sortStmt->fetchColumn();

        $insertStmt = $pdo->prepare("
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
                :creator_id,
                :reviewer_id
            )
        ");

        $insertStmt->execute([
            'trip_id' => $tripId,
            'week_id' => $data['trip_week_id'] ?? null,
            'trip_date' => $data['trip_date'],
            'title' => $data['title'],
            'origin' => $data['origin'] ?? null,
            'destination' => $data['destination'] ?? null,
            'travel_mode' =>
                $data['travel_mode'] ?? 'DRIVE',
            'distance_km' =>
                $data['distance_km'] ?? null,
            'drive_minutes' =>
                $data['drive_minutes'] ?? null,
            'summary' =>
                $data['summary'] ?? null,
            'map_url' =>
                $data['map_url'] ?? null,
            'estimated_cost' =>
                $data['estimated_cost_per_person'] ?? null,
            'sort_order' => $sortOrder,
            'creator_id' =>
                (int) $proposal['submitted_by_member_id'],
            'reviewer_id' => $reviewerId
        ]);
    }

    $approveStmt = $pdo->prepare("
        UPDATE trip_itinerary_proposals
        SET
            status = 'approved',
            reviewed_by_member_id = ?,
            review_notes = ?,
            reviewed_at = NOW()
        WHERE id = ?
    ");

    $approveStmt->execute([
        $reviewerId,
        $reviewNotes ?: null,
        $proposalId
    ]);

    $pdo->commit();

    $_SESSION['review_message'] =
        'The proposed itinerary change was approved.';

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Itinerary proposal approval failed: '
        . $e->getMessage()
    );

    $_SESSION['review_message'] =
        'The proposed change could not be applied.';
}

header('Location: ../itinerary_reviews.php');
exit;
