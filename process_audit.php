<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// These paths assume you cloned PHPMailer into includes/PHPMailer
require 'includes/PHPMailer/src/Exception.php';
require 'includes/PHPMailer/src/PHPMailer.php';
require 'includes/PHPMailer/src/SMTP.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $mail = new PHPMailer(true);

    try {
        // --- Zoho SMTP Settings ---
        $mail->isSMTP();
        $mail->Host       = 'smtp.zoho.com.au'; 
        $mail->SMTPAuth   = true;
        $mail->Username   = 'mike@mikeofalltrades.com.au'; 
        $mail->Password   = 'WWXrU4C4xQdH'; 
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465; 

        // --- The Handshake ---
        $mail->setFrom('mike@mikeofalltrades.com.au', 'Mike of All Trades');
        $mail->addAddress('mike@mikeofalltrades.com.au'); // Lead goes to your new inbox
        $mail->addReplyTo($_POST['email'], $_POST['name']); // Reply button goes to the client

        // --- The Lead Content ---
        $mail->isHTML(false);
        $mail->Subject = "NEW FACILITY AUDIT: " . $_POST['facility'];
        $mail->Body    = "Name: {$_POST['name']}\nFacility: {$_POST['facility']}\nEmail: {$_POST['email']}\n\nDetails:\n{$_POST['message']}";

        $mail->send();
        echo "<h1>Request Sent!</h1><p>Thanks Mike, I'll be in touch soon. <a href='index.php'>Return to site</a></p>";
    } catch (Exception $e) {
        // Silent failure for users, but you can check logs if needed
        echo "<h1>Oops!</h1><p>We couldn't process your request right now. Please email us directly at mike@mikeofalltrades.com.au</p>";
    }
}