<?php
require_once __DIR__ . '/includes/db.php';

$token = trim($_GET['token'] ?? '');
$conversation = null;

if ($token !== '') {
    $stmt = $pdo->prepare("
        SELECT *
        FROM ai_conversations
        WHERE conversation_token = ?
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $conversation = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Chat Conversation</title>
    <style>
        body{font-family:Arial;background:#111;color:white;padding:24px}
        .box{max-width:850px;margin:auto;background:#1f1f1f;padding:24px;border-radius:16px;overflow:hidden;}
        pre{white-space:pre-wrap;font-family:Arial;line-height:1.5;background:#111;padding:18px;border-radius:12px}
        .hint{color:#aaa}
        a{color:#4db8ff}
    </style>
</head>
<body>
<div class="box">
    <h1>AI Chat Conversation</h1>

    <?php if (!$conversation): ?>
        <p class="hint">Conversation not found.</p>
    <?php else: ?>
        <p class="hint">
    This is a saved copy of the AI chat so the details do not need to be repeated.
    Mike has a permanent copy of this conversation for future reference if needed.
    If you would like to discuss this conversation further, feel free to contact Mike on +61 405 283 013.
</p>

        <p>
            <strong>Name:</strong>
            <?= htmlspecialchars($conversation['customer_name'] ?: 'Not provided') ?><br>

            <strong>Email:</strong>
            <?= htmlspecialchars($conversation['customer_email'] ?: 'Not provided') ?><br>

            <strong>Phone:</strong>
            <?= htmlspecialchars($conversation['customer_phone'] ?: 'Not provided') ?><br>

            <strong>Created:</strong>
            <?= htmlspecialchars($conversation['created_at'] ?? '') ?>
        </p>

pre{
    white-space:pre-wrap;
    word-wrap:break-word;
    overflow-wrap:break-word;
    font-family:Arial;
    line-height:1.5;
    background:#111;
    padding:18px;
    border-radius:12px;
}

        <p>
            <a href="/test_ai_intake.html">Start another conversation with the AI Helper</a>
        </p>
    <?php endif; ?>
</div>
</body>
</html>