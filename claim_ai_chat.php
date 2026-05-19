<?php
session_start();

require_once __DIR__ . '/includes/db.php';

$token = trim($_GET['token'] ?? '');

if (empty($_SESSION['user_id'])) {
    header('Location: login.php?claim_ai_chat=' . urlencode($token));
    exit;
}

if ($token !== '') {
    $stmt = $pdo->prepare("
        UPDATE ai_conversations
        SET user_id = ?
        WHERE conversation_token = ?
    ");

    $stmt->execute([
        $_SESSION['user_id'],
        $token
    ]);
}

header('Location: customer/ai_chats.php');
exit;