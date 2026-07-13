<?php

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/trip_auth.php';

requireTripLogin();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request.'
    ]);
    exit;
}

$tripId = (int) $_SESSION['trip_id'];
$memberId = (int) $_SESSION['trip_member_id'];

$origin = trim($_POST['origin'] ?? '');
$destination = trim($_POST['destination'] ?? '');
$travelMode = $_POST['travel_mode'] ?? 'DRIVE';

$allowedModes = [
    'DRIVE',
    'TWO_WHEELER',
    'WALK',
    'TRANSIT'
];

if (
    $origin === ''
    || $destination === ''
    || !in_array($travelMode, $allowedModes, true)
) {
    echo json_encode([
        'success' => false,
        'message' =>
            'Enter a valid origin, destination and travel mode.'
    ]);
    exit;
}

/*
 * Add this constant to includes/config.php after creating a Google
 * Routes API server key:
 *
 * define('GOOGLE_ROUTES_API_KEY', 'YOUR_RESTRICTED_KEY');
 */
$configPath = dirname(__DIR__, 2) . '/includes/config.php';

if (is_file($configPath)) {
    require_once $configPath;
}

if (
    !defined('GOOGLE_ROUTES_API_KEY')
    || trim((string) GOOGLE_ROUTES_API_KEY) === ''
) {
    echo json_encode([
        'success' => false,
        'message' =>
            'The Google Maps link was created, but automatic distance '
            . 'calculation is not enabled yet. Add the restricted '
            . 'Routes API key to includes/config.php.'
    ]);
    exit;
}

$requestBody = [
    'origin' => [
        'address' => $origin
    ],

    'destination' => [
        'address' => $destination
    ],

    'travelMode' => $travelMode,

    'computeAlternativeRoutes' => false,

    'languageCode' => 'en-AU',

    'units' => 'METRIC'
];

$curl = curl_init(
    'https://routes.googleapis.com/directions/v2:computeRoutes'
);

curl_setopt_array($curl, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 20,

    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'X-Goog-Api-Key: ' . GOOGLE_ROUTES_API_KEY,
        'X-Goog-FieldMask: routes.distanceMeters,routes.duration'
    ],

    CURLOPT_POSTFIELDS => json_encode($requestBody)
]);

$responseBody = curl_exec($curl);
$curlError = curl_error($curl);
$statusCode = (int) curl_getinfo(
    $curl,
    CURLINFO_HTTP_CODE
);

curl_close($curl);

$logStmt = $pdo->prepare("
    INSERT INTO trip_route_calculations (
        trip_id,
        origin,
        destination,
        travel_mode,
        distance_metres,
        duration_seconds,
        response_status,
        error_message,
        requested_by_member_id
    ) VALUES (
        :trip_id,
        :origin,
        :destination,
        :travel_mode,
        :distance_metres,
        :duration_seconds,
        :response_status,
        :error_message,
        :member_id
    )
");

if ($curlError !== '') {
    $logStmt->execute([
        'trip_id' => $tripId,
        'origin' => $origin,
        'destination' => $destination,
        'travel_mode' => $travelMode,
        'distance_metres' => null,
        'duration_seconds' => null,
        'response_status' => 'CURL_ERROR',
        'error_message' => $curlError,
        'member_id' => $memberId
    ]);

    echo json_encode([
        'success' => false,
        'message' => 'Google route request failed.'
    ]);
    exit;
}

$data = json_decode(
    $responseBody,
    true
);

if (
    $statusCode !== 200
    || empty($data['routes'][0])
) {
    $apiMessage =
        $data['error']['message']
        ?? 'No route was returned.';

    $logStmt->execute([
        'trip_id' => $tripId,
        'origin' => $origin,
        'destination' => $destination,
        'travel_mode' => $travelMode,
        'distance_metres' => null,
        'duration_seconds' => null,
        'response_status' =>
            'HTTP_' . $statusCode,
        'error_message' => $apiMessage,
        'member_id' => $memberId
    ]);

    echo json_encode([
        'success' => false,
        'message' => $apiMessage
    ]);
    exit;
}

$route = $data['routes'][0];

$distanceMetres =
    (int) ($route['distanceMeters'] ?? 0);

$durationString =
    (string) ($route['duration'] ?? '0s');

$durationSeconds =
    (int) rtrim($durationString, 's');

$distanceKm = round(
    $distanceMetres / 1000,
    1
);

$durationMinutes = (int) ceil(
    $durationSeconds / 60
);

$hours = intdiv($durationMinutes, 60);
$minutes = $durationMinutes % 60;

$durationText =
    ($hours > 0 ? $hours . ' hr ' : '')
    . ($minutes > 0 ? $minutes . ' min' : '');

$logStmt->execute([
    'trip_id' => $tripId,
    'origin' => $origin,
    'destination' => $destination,
    'travel_mode' => $travelMode,
    'distance_metres' => $distanceMetres,
    'duration_seconds' => $durationSeconds,
    'response_status' => 'SUCCESS',
    'error_message' => null,
    'member_id' => $memberId
]);

echo json_encode([
    'success' => true,
    'distance_km' => $distanceKm,
    'duration_minutes' => $durationMinutes,
    'duration_text' => trim($durationText)
]);
