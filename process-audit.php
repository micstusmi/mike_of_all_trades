<?php
// Turn on error reporting for this test so we can see what's wrong
ini_set('display_errors', 1);
error_reporting(E_ALL);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // 1. COLLECT DATA
    $name = $_POST["name"] ?? 'No Name';
    $facility = $_POST["facility"] ?? 'No Facility';
    $email = $_POST["email"] ?? 'No Email';
    $message = $_POST["message"] ?? 'No Message';
    $honeypot = $_POST["website_verification_field"] ?? '';

    // 2. CHECK HONEYPOT (Only if it actually has content)
    if (!empty($honeypot)) {
        die("Spam bot detected. If you are human, please clear your browser cache and try again.");
    }

    // 3. SEND THE MAIL
    $recipient = "michaelssmith@icloud.com"; 
    $subject = "NEW AUDIT REQUEST: $facility";
    $email_content = "Name: $name\nFacility: $facility\nEmail: $email\n\nDetails:\n$message";
    
    // The -f flag is crucial for AWS/iCloud trust
    $headers = "From: web-form@mikeofalltrades.com.au";
    $params = "-fweb-form@mikeofalltrades.com.au";

    if (mail($recipient, $subject, $email_content, $headers, $params)) {
        echo "<h1>Request Sent!</h1><p>Thanks Mike, I'll be in touch soon. <a href='index.php'>Return to site</a></p>";
    } else {
        $last_error = error_get_last();
        echo "Oops! Mail failed. Error: " . $last_error['message'];
    }
}
?>