<?php

error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Content-Type: application/json');

include 'includes/zoho_functions.php';

try {

    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        throw new Exception("Invalid request");
    }

    $name        = $_POST['name'] ?? '';
    $email       = $_POST['email'] ?? '';
    $phone       = $_POST['phone'] ?? '';
    $address     = $_POST['address'] ?? '';
    $service     = $_POST['service'] ?? 'General Works';
    $description = $_POST['description'] ?? '';   // 👈 NEW FIELD
    $total       = $_POST['total'] ?? 0;

    /**
     * 1. CUSTOMER (dedupe-safe)
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
     */
    $send = sendZohoEstimate($estimate_id, $email);

    if (($send['code'] ?? 0) >= 400) {
        throw new Exception("Estimate created but email failed: " . $send['raw']);
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