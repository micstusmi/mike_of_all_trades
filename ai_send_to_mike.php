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

    echo json_encode([
        'success' => true,
        'message' => 'Thanks — this chat has been saved for Mike to review.',
        'token' => $token,
        'link' => 'view_ai_conversation.php?token=' . $token
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}