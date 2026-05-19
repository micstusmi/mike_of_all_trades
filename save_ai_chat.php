<?php
header('Content-Type: application/json');

require_once __DIR__ . '/includes/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$userId = $_SESSION['user_id'] ?? null;

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
            (conversation_token, user_id, conversation_text)
            VALUES (?, ?, ?)
        ");

        $stmt->execute([$token, $userId, $chat]);

    } else {
        $check = $pdo->prepare("
            SELECT id
            FROM ai_conversations
            WHERE conversation_token = ?
            LIMIT 1
        ");
        $check->execute([$token]);
        $existing = $check->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $stmt = $pdo->prepare("
                UPDATE ai_conversations
                SET conversation_text = ?,
                    user_id = COALESCE(user_id, ?)
                WHERE conversation_token = ?
            ");

            $stmt->execute([$chat, $userId, $token]);

        } else {
            $stmt = $pdo->prepare("
                INSERT INTO ai_conversations
                (conversation_token, user_id, conversation_text)
                VALUES (?, ?, ?)
            ");

            $stmt->execute([$token, $userId, $chat]);
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