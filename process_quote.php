<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Content-Type: application/json');

include 'includes/zoho_functions.php';

try {

    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        throw new Exception("Invalid request method");
    }

    $name    = $_POST['name'] ?? '';
    $email   = $_POST['email'] ?? '';
    $phone   = $_POST['phone'] ?? '';
    $address = $_POST['address'] ?? '';
    $type    = $_POST['customer_type'] ?? 'once';
    $service = $_POST['service'] ?? 'General Trades';
    $total   = $_POST['total'] ?? 0;

    $customer_id = null;
    $contact_person_id = null;

    /**
     * CUSTOMER LOGIC
     */
    if ($type === 'repeat') {

        // NOTE: you still need your own createZohoCustomer()
        $customer_id = createZohoCustomer($name, $email, $phone, $address);

        if (!$customer_id) {
            throw new Exception("Failed to create Zoho customer");
        }

    } else {

        // Web Leads customer ID (your master bucket)
        $customer_id = '127145000000499006';

        $contact_person_id = addContactToWebLeads($email, $name, $phone);
    }

    /**
     * CREATE ESTIMATE
     */
    $estimateResult = createZohoEstimate(
        $customer_id,
        $service,
        $total,
        $contact_person_id
    );

    if (($estimateResult['code'] ?? 0) >= 400) {
        throw new Exception("Zoho estimate failed: " . $estimateResult['raw']);
    }

    $estimate_id = $estimateResult['body']['estimate']['estimate_id'] ?? null;

    if (!$estimate_id) {
        throw new Exception("No estimate ID returned from Zoho");
    }

    /**
     * SEND EMAIL (THIS IS NOW EXPLICIT)
     */
    $sendResult = sendZohoEstimate($estimate_id, $email);
    
    if (($sendResult['code'] ?? 0) >= 400) {
        throw new Exception("Estimate created but email failed: " . $sendResult['raw']);
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