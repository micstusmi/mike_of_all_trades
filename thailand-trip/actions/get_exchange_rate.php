<?php

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/trip_auth.php';
require_once __DIR__ . '/../includes/exchange_rate.php';

requireTripLogin();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    $rateData = getAudToThbRate();

    echo json_encode([
        'success' => true,
        'rate' => round((float) $rateData['rate'], 6),
        'date' => $rateData['date'],
        'is_live' => (bool) $rateData['available'],
        'source' => $rateData['source']
    ]);

} catch (Throwable $e) {
    error_log(
        'Exchange-rate request failed: '
        . $e->getMessage()
    );

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' =>
            'The exchange rate could not be retrieved.'
    ]);
}
