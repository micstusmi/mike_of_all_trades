<?php
session_start();

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

try {
    if (($_SESSION['user_role'] ?? '') !== 'admin') {
        throw new Exception('Admin access required.');
    }

    $contactName = trim($_POST['contact_name'] ?? '');
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $billingAddress = trim($_POST['billing_address'] ?? '');
    $hourlyRate = (float)($_POST['hourly_rate'] ?? 0);
    $minimumHours = (float)($_POST['minimum_hours'] ?? 4);
    $serviceZone = trim($_POST['service_zone'] ?? 'south_east');

    if (!$contactName || !$email || $hourlyRate <= 0) {
        throw new Exception('Contact name, email and hourly rate are required.');
    }

    $token = bin2hex(random_bytes(32));

    $stmt = $pdo->prepare("
        INSERT INTO special_customer_invites
        (
            token,
            contact_name,
            first_name,
            last_name,
            email,
            phone,
            billing_address,
            hourly_rate,
            minimum_hours,
            service_zone
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $token,
        $contactName,
        $firstName,
        $lastName,
        $email,
        $phone,
        $billingAddress,
        $hourlyRate,
        $minimumHours,
        $serviceZone
    ]);

    $link = 'https://mikeofalltrades.com.au/special_customer_register.php?token=' . urlencode($token);

    $mail = new PHPMailer(true);

    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USERNAME;
    $mail->Password   = SMTP_PASSWORD;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = SMTP_PORT;

    $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
    $mail->addAddress($email, $contactName);

    $mail->isHTML(false);
    $mail->Subject = 'Your Mike Of All Trades customer booking access';

    $mail->Body =
        "Hi {$contactName},\n\n" .
        "Mike has set up special customer booking access for you.\n\n" .
        "Your agreed rate is $" . number_format($hourlyRate, 2) . " per hour with a minimum booking of {$minimumHours} hours.\n\n" .
        "Please complete your account setup here:\n{$link}\n\n" .
        "Thanks,\nMike Of All Trades";

    $mail->send();

    echo json_encode([
        'success' => true,
        'message' => 'Invite saved and emailed successfully.',
        'link' => $link
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}