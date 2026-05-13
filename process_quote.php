<?php
// process_quote.php
error_reporting(E_ALL);
ini_set('display_errors', 0); 

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'includes/PHPMailer/src/Exception.php';
require 'includes/PHPMailer/src/PHPMailer.php';
require 'includes/PHPMailer/src/SMTP.php';
include 'includes/zoho_functions.php';

header('Content-Type: application/json');

try {
    if ($_SERVER["REQUEST_METHOD"] !== "POST") throw new Exception("Invalid request.");

    $name    = $_POST['name'] ?? '';
    $email   = $_POST['email'] ?? '';
    $phone   = $_POST['phone'] ?? '';
    $address = $_POST['address'] ?? '';
    $type    = $_POST['customer_type'] ?? 'once';
    $service = $_POST['service'] ?? 'General Trades';
    $total   = $_POST['total'] ?? '0';

    $final_customer_id = '';
    $contact_person_id = null;

    if ($type === 'repeat') {
        $final_customer_id = createZohoCustomer($name, $email, $phone, $address);
    } else {
        // !!! REPLACE THIS ID WITH YOUR 19-DIGIT WEB LEADS ID !!!
        $final_customer_id = '127145000000499006'; 
        // Add the guest to the master account so we can send the email
        $contact_person_id = addContactToWebLeads($email, $name, $phone);
    }

    if (!$final_customer_id) throw new Exception("Zoho Account creation failed.");

    $zohoResult = createZohoEstimate($email, $service, $total, $final_customer_id, $contact_person_id);
    $zohoResponse = json_decode($zohoResult, true);

    if (isset($zohoResponse['code']) && $zohoResponse['code'] == 0) {
        // (PHPMailer notification code stays here...)
        echo json_encode(['success' => true]);
    } else {
        throw new Exception($zohoResponse['message'] ?? 'Zoho Error');
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}