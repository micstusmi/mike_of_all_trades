<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // 1. Collect and Clean Data
    $name = strip_tags(trim($_POST["name"] ?? ''));
    $facility = strip_tags(trim($_POST["facility"] ?? ''));
    $email = filter_var(trim($_POST["email"] ?? ''), FILTER_SANITIZE_EMAIL);
    $message = strip_tags(trim($_POST["message"] ?? ''));
    $honeypot = $_POST["website_verification_field"] ?? '';

    // 2. Honeypot check
    if (!empty($honeypot)) {
        die("Spam detected.");
    }

    // 3. Setup Email
    $recipient = "michaelssmith@icloud.com"; 
    $subject = "NEW AUDIT REQUEST: $facility";
    $email_content = "Name: $name\nFacility: $facility\nEmail: $email\n\nDetails:\n$message";
    
    // Essential for AWS and iCloud delivery
    $headers = "From: web-form@mikeofalltrades.com.au\r\n";
    $headers .= "Reply-To: $email";
    $params = "-fweb-form@mikeofalltrades.com.au";

    // 4. Send
    if (mail($recipient, $subject, $email_content, $headers, $params)) {
        echo "<h1>Request Sent!</h1><p>Thanks Mike, I'll be in touch soon. <a href='index.php'>Return to site</a></p>";
    } else {
        echo "<h1>Oops!</h1><p>The mail server didn't respond. This usually happens on local computers (XAMPP). Please test this on the live AWS server.</p>";
    }
}
?>