<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Content-Type: application/json');

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/zoho_functions.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request.');
    }

    $email = trim($_POST['email'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $hours = (float)($_POST['hours'] ?? 0);
    $price = (float)($_POST['price'] ?? 0);
    $conversationToken = trim($_POST['conversation_token'] ?? '');

    if (!$email) {
        throw new Exception('Customer email is required.');
    }

    if (!$notes) {
        throw new Exception('Quote notes are required.');
    }

    if ($price <= 0) {
        throw new Exception('Estimated price must be greater than zero.');
    }

    $name = 'AI Quote Customer';
    $phone = '';
    $address = '';

    $conversationLink = '';

    if ($conversationToken) {
        $conversationLink =
            'https://mikeofalltrades.com.au/view_ai_conversation.php?token=' .
            urlencode($conversationToken);
    }

    $description =
        "AI-assisted quote request\n\n" .
        $notes . "\n\n" .
        "Estimated labour hours: " . $hours . "\n\n" .
        "Estimated pricing and timeframes are a guide only. Final pricing may vary depending on materials, access, existing conditions, and any unexpected issues discovered during the job.";

    if ($conversationLink) {
        $description .= "\n\nAI conversation link:\n" . $conversationLink;
    }

    $customer_id = getOrCreateZohoCustomer($name, $email, $phone, $address);

    if (!$customer_id) {
        throw new Exception('Failed to create or find Zoho customer.');
    }

    $estimate = createZohoEstimate(
        $customer_id,
        $name,
        $description,
        $price
    );

    if (($estimate['code'] ?? 0) >= 400) {
        throw new Exception('Zoho estimate failed: ' . ($estimate['raw'] ?? 'Unknown error'));
    }

    $estimate_id = $estimate['json']['estimate']['estimate_id'] ?? null;

    if (!$estimate_id) {
        throw new Exception('No Zoho estimate ID returned.');
    }

    $send = sendZohoEstimate($estimate_id, $email);

    if (($send['code'] ?? 0) >= 400) {
        throw new Exception('Estimate created but email failed: ' . ($send['raw'] ?? 'Unknown error'));
    }

    echo json_encode([
        'success' => true,
        'estimate_id' => $estimate_id
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}