<?php
header('Content-Type: application/json');
require_once __DIR__ . '/includes/db.php';

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

    $to = 'mike@mikeofalltrades.com.au';
    $subject = 'New AI chat submitted';

    $message =
        "A customer has sent an AI chat to Mike.\n\n" .
        "Name: " . ($name ?: 'Not provided') . "\n" .
        "Email: " . ($email ?: 'Not provided') . "\n" .
        "Phone: " . ($phone ?: 'Not provided') . "\n\n" .
        "View chat:\n" . $chatLink . "\n\n" .
        "Admin list:\nhttps://mikeofalltrades.com.au/admin_ai_chats.php";

    $headers = "From: website@mikeofalltrades.com.au\r\n";
    $headers .= "Reply-To: " . ($email ?: 'mike@mikeofalltrades.com.au') . "\r\n";

    @mail($to, $subject, $message, $headers);

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