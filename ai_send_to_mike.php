<?php
header('Content-Type: application/json');

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request.');
    }

    $name  = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $chat  = trim($_POST['chat'] ?? '');

    if (!$chat) {
        throw new Exception('No chat conversation was supplied.');
    }

    $token = bin2hex(random_bytes(16));

    $stmt = $pdo->prepare("
        INSERT INTO ai_conversations
        (conversation_token, customer_name, customer_email, customer_phone, conversation_text)
        VALUES (?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $token,
        $name,
        $email,
        $phone,
        $chat
    ]);

$chatLink = 'https://mikeofalltrades.com.au/view_ai_conversation.php?token=' . urlencode($token);

$mail = new PHPMailer(true);

try {

    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USERNAME;
    $mail->Password   = SMTP_PASSWORD;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = SMTP_PORT;

    $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);

    // Send to Mike
    $mail->addAddress('mike@mikeofalltrades.com.au', 'Mike');

    // Reply directly to customer if provided
    if (!empty($email)) {
        $mail->addReplyTo($email, $name ?: 'Customer');
    }

    $mail->isHTML(false);

    $mail->Subject = 'New AI chat submitted';

    $mail->Body =
        "A customer has sent an AI chat to Mike.\n\n" .
        "Name: " . ($name ?: 'Not provided') . "\n" .
        "Email: " . ($email ?: 'Not provided') . "\n" .
        "Phone: " . ($phone ?: 'Not provided') . "\n\n" .
        "View chat:\n" . $chatLink . "\n\n" .
        "Admin list:\nhttps://mikeofalltrades.com.au/admin_ai_chats.php";

    $mail->send();

    // Optional customer confirmation email
    if (!empty($email)) {

        $customerMail = new PHPMailer(true);

        $customerMail->isSMTP();
        $customerMail->Host       = SMTP_HOST;
        $customerMail->SMTPAuth   = true;
        $customerMail->Username   = SMTP_USERNAME;
        $customerMail->Password   = SMTP_PASSWORD;
        $customerMail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $customerMail->Port       = SMTP_PORT;

        $customerMail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);

        $customerMail->addAddress($email, $name ?: 'Customer');

        $customerMail->Subject = 'Your AI chat has been received';

        $customerMail->Body =
            "Thanks for contacting Mike Of All Trades.\n\n" .
            "Mike has received your AI chat submission and will review it shortly.\n\n" .
            "Conversation link:\n" . $chatLink;

        $customerMail->send();
    }

} catch (MailException $e) {

    error_log('Mailer Error: ' . $mail->ErrorInfo);

}

    echo json_encode([
        'success' => true,
        'message' => 'Thanks — this chat has been saved for Mike to review.',
        'token' => $token,
        'link' => $chatLink
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}