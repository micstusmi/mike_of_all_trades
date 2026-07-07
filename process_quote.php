<?php

error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Content-Type: application/json');

include 'includes/zoho_functions.php';

try {
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        throw new Exception("Invalid request");
    }

    // Honeypot spam field. Real users should never fill this.
    if (!empty($_POST['website'] ?? '') || !empty($_POST['website_url'] ?? '')) {
        throw new Exception("Spam detected");
    }

    $name        = trim($_POST['name'] ?? '');
    $email       = trim($_POST['email'] ?? '');
    $phone       = trim($_POST['phone'] ?? '');
    $address     = trim($_POST['address'] ?? '');
    $service     = trim($_POST['service'] ?? 'General Works');
    $description = trim($_POST['description'] ?? '');
    $total       = (float)($_POST['total'] ?? 0);

    if ($name === '' || strlen($name) < 2) {
        throw new Exception("Name is required");
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception("Valid email is required");
    }

    if ($phone === '' || strlen($phone) < 8) {
        throw new Exception("Valid phone number is required");
    }

    if ($address === '' || strlen($address) < 3) {
        throw new Exception("Address or suburb is required");
    }

    if ($service === '') {
        throw new Exception("Service is required");
    }

    if ($total <= 0) {
        throw new Exception("Quote total must be greater than zero");
    }

    if ($total < 50) {
        throw new Exception("Quote total is too low");
    }

    // Basic rate limit by IP address.
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $rateDir = __DIR__ . '/quote_rate_limits';

    if (!is_dir($rateDir)) {
        mkdir($rateDir, 0755, true);
    }

    $rateFile = $rateDir . '/' . md5($ip) . '.json';
    $now = time();
    $windowSeconds = 3600;
    $maxRequests = 5;

    $history = [];

    if (file_exists($rateFile)) {
        $history = json_decode(file_get_contents($rateFile), true);
        if (!is_array($history)) {
            $history = [];
        }
    }

    $history = array_filter($history, function($timestamp) use ($now, $windowSeconds) {
        return ($now - $timestamp) < $windowSeconds;
    });

    if (count($history) >= $maxRequests) {
        throw new Exception("Too many quote requests. Please try again later.");
    }

    $history[] = $now;
    file_put_contents($rateFile, json_encode(array_values($history)));

    /**
     * 1. CUSTOMER
     */
    $customer_id = getOrCreateZohoCustomer($name, $email, $phone, $address);

    if (!$customer_id) {
        throw new Exception("Failed to create/find customer");
    }

    /**
     * 2. CREATE ESTIMATE
     */
    $estimate = createZohoEstimate(
        $customer_id,
        $name,
        $service . " - " . $description,
        $total
    );

    if (($estimate['code'] ?? 0) >= 400) {
        throw new Exception("Estimate failed: " . $estimate['raw']);
    }

    $estimate_id = $estimate['json']['estimate']['estimate_id'] ?? null;

    if (!$estimate_id) {
        throw new Exception("No estimate ID returned");
    }

    /**
     * 3. SEND EMAIL
     * If Zoho email fails, do not fail the whole quote.
     */
    $send = sendZohoEstimate($estimate_id, $email);

    $email_sent = true;
    $email_warning = '';

    if (($send['code'] ?? 0) >= 400) {
        $email_sent = false;
        $email_warning = $send['raw'] ?? 'Zoho email failed.';
        error_log("Zoho estimate created but email failed: " . $email_warning);
    }

    echo json_encode([
        'success' => true,
        'estimate_id' => $estimate_id,
        'email_sent' => $email_sent,
        'email_warning' => $email_warning,
        'message' => $email_sent
            ? 'Quote created and emailed successfully.'
            : 'Quote created in Zoho, but the email was not sent.'
    ]);

} catch (Exception $e) {
    error_log("process_quote.php error: " . $e->getMessage());

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
