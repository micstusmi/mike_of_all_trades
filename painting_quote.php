<?php
require_once __DIR__ . '/includes/painting_quote.php';
require_once __DIR__ . '/includes/painting_zoho.php';

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException('Invalid request method.');
    }

    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true);

    if (!is_array($payload)) {
        throw new RuntimeException('Invalid quote data.');
    }

    $customer = $payload['customer'] ?? [];
    if (empty($customer['name']) || empty($customer['phone']) || empty($customer['email'])) {
        throw new RuntimeException('Missing customer name, phone or email.');
    }

    $quotePackage = painting_build_quote_package($payload);
    $zohoResult = painting_create_zoho_estimate($quotePackage);

    echo json_encode([
        'success' => true,
        'reference' => $quotePackage['reference'],
        'scope_of_works' => $quotePackage['scope_of_works'],
        'line_items' => $quotePackage['line_items'],
        'estimate' => $quotePackage['estimate'],
        'zoho' => $zohoResult
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
