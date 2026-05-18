<?php
require_once __DIR__ . '/includes/db.php';

header('Content-Type: application/json');

$token = trim($_POST['token'] ?? '');
$chat = trim($_POST['chat'] ?? '');
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');

if (!$chat) {
    echo json_encode([
        'success' => false,
        'message' => 'No conversation supplied.'
    ]);
    exit;
}

if (!$token) {
    $token = bin2hex(random_bytes(16));
}

$stmt = $pdo->prepare("
    INSERT INTO ai_conversations
    (
        conversation_token,
        customer_name,
        customer_email,
        customer_phone,
        conversation_text
    )
    VALUES
    (?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        customer_name = VALUES(customer_name),
        customer_email = VALUES(customer_email),
        customer_phone = VALUES(customer_phone),
        conversation_text = VALUES(conversation_text)
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
    'token' => $token
]);