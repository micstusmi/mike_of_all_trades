<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'includes/PHPMailer/src/Exception.php';
require 'includes/PHPMailer/src/PHPMailer.php';
require 'includes/PHPMailer/src/SMTP.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // 1. Spam Verification
    $honeypot = $_POST["website_url"] ?? '';
    $math_answer = intval($_POST["math_answer"] ?? 0);

    if (!empty($honeypot)) {
        die("Spam bot detected."); 
    }

    if ($math_answer !== 10) {
        die("Incorrect math answer. Please go back and try again.");
    }

    // 2. SMTP Send via Zoho
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.zoho.com.au';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'mike@mikeofalltrades.com.au';
        $mail->Password   = 'WWXrU4C4xQdH'; 
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;

        $mail->setFrom('mike@mikeofalltrades.com.au', 'Mike of All Trades (Quotes)');
        $mail->addAddress('mike@mikeofalltrades.com.au');
        $mail->addReplyTo($_POST['email'], $_POST['name']);

        $mail->isHTML(false);
        $mail->Subject = "NEW QUOTE: " . $_POST['service'] . " - " . $_POST['name'];
        $mail->Body    = "Quote Request Details:\n\nName: {$_POST['name']}\nEmail: {$_POST['email']}\nService: {$_POST['service']}\n\nMessage:\n{$_POST['message']}";

        $mail->send();
        echo "<h1>Quote Request Sent!</h1><p>Thanks Mike, I'll review this and get back to you soon. <a href='index.php'>Return to site</a></p>";
    } catch (Exception $e) {
        echo "Message could not be sent. Please email mike@mikeofalltrades.com.au directly.";
    }
}