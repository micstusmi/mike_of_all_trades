<?php
header('Content-Type: application/json');

require_once __DIR__ . '/includes/db.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request.');
    }

    $token = trim($_POST['token'] ?? '');
    $chat  = trim($_POST['chat'] ?? '');

    if (!$chat) {
        throw new Exception('No chat content was supplied.');
    }

    if (!$token) {
        $token = bin2hex(random_bytes(16));

        $stmt = $pdo->prepare("
            INSERT INTO ai_conversations
            (conversation_token, conversation_text)
            VALUES (?, ?)
        ");

        $stmt->execute([$token, $chat]);

    } else {
        $stmt = $pdo->prepare("
            UPDATE ai_conversations
            SET conversation_text = ?
            WHERE conversation_token = ?
        ");

        $stmt->execute([$chat, $token]);

        if ($stmt->rowCount() === 0) {
            $stmt = $pdo->prepare("
                INSERT INTO ai_conversations
                (conversation_token, conversation_text)
                VALUES (?, ?)
            ");

            $stmt->execute([$token, $chat]);
        }
    }

    echo json_encode([
        'success' => true,
        'token' => $token,
        'link' => 'https://mikeofalltrades.com.au/view_ai_conversation.php?token=' . urlencode($token)
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}