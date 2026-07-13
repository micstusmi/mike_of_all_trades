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

$dayId = (int) ($_POST['trip_day_id'] ?? 0);
$category = $_POST['category'] ?? 'other';
$title = trim($_POST['title'] ?? '');
$provider = trim($_POST['provider'] ?? '');
$price = trim($_POST['approximate_price'] ?? '');
$currency = $_POST['currency'] ?? 'THB';
$websiteUrl = trim($_POST['website_url'] ?? '');
$mapUrl = trim($_POST['map_url'] ?? '');
$notes = trim($_POST['notes'] ?? '');

$allowedCategories = [
    'accommodation',
    'transport',
    'restaurant',
    'activity',
    'other'
];

$allowedCurrencies = [
    'THB',
    'AUD'
];

if (
    $dayId <= 0
    || $title === ''
    || !in_array($category, $allowedCategories, true)
    || !in_array($currency, $allowedCurrencies, true)
) {
    $_SESSION['itinerary_error'] =
        'The suggestion could not be saved. Enter a title and category.';

    header('Location: ../itinerary_editor.php');
    exit;
}

$dayStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM trip_days
    WHERE id = ?
      AND trip_id = ?
");

$dayStmt->execute([
    $dayId,
    $tripId
]);

if ((int) $dayStmt->fetchColumn() === 0) {
    $_SESSION['itinerary_error'] =
        'The related itinerary day could not be found.';

    header('Location: ../itinerary_editor.php');
    exit;
}

if (
    $websiteUrl !== ''
    && !filter_var($websiteUrl, FILTER_VALIDATE_URL)
) {
    $websiteUrl = '';
}

if (
    $mapUrl !== ''
    && !filter_var($mapUrl, FILTER_VALIDATE_URL)
) {
    $mapUrl = '';
}

$priceValue =
    $price !== ''
        ? max(0, (float) $price)
        : null;

$stmt = $pdo->prepare("
    INSERT INTO trip_day_suggestions (
        trip_id,
        trip_day_id,
        category,
        title,
        provider,
        approximate_price,
        currency,
        website_url,
        map_url,
        notes,
        suggested_by_member_id
    ) VALUES (
        :trip_id,
        :day_id,
        :category,
        :title,
        :provider,
        :price,
        :currency,
        :website_url,
        :map_url,
        :notes,
        :member_id
    )
");

$stmt->execute([
    'trip_id' => $tripId,
    'day_id' => $dayId,
    'category' => $category,
    'title' => $title,
    'provider' => $provider ?: null,
    'price' => $priceValue,
    'currency' => $currency,
    'website_url' => $websiteUrl ?: null,
    'map_url' => $mapUrl ?: null,
    'notes' => $notes ?: null,
    'member_id' => $memberId
]);

$_SESSION['itinerary_message'] =
    'Suggestion added successfully.';

header(
    'Location: ../itinerary_editor.php#day-'
    . $dayId
);
exit;
