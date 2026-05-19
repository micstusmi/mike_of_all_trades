<?php
require_once __DIR__ . '/includes/db.php';

$stmt = $pdo->query("
    SELECT *
    FROM ai_conversations
    ORDER BY created_at DESC
");

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>AI Chats Admin</title>

    <style>
        body{
            font-family:Arial;
            background:#111;
            color:white;
            padding:30px;
        }

        .chat{
            background:#1f1f1f;
            padding:20px;
            border-radius:14px;
            margin-bottom:20px;
        }

        .meta{
            color:#aaa;
            font-size:14px;
            margin-bottom:15px;
        }

        pre{
            white-space:pre-wrap;
            font-family:Arial;
            line-height:1.5;
            color:#e5e5e5;
        }

        a{
            color:#4db8ff;
        }
    </style>
</head>

<body>

<h1>AI Chat Submissions</h1>

<?php foreach($rows as $row): ?>

<div class="chat">

    <div class="meta">
        <strong>Name:</strong>
        <?= htmlspecialchars($row['customer_name'] ?: 'Unknown') ?>
        <br>

        <strong>Email:</strong>
        <?= htmlspecialchars($row['customer_email'] ?: 'Not provided') ?>
        <br>

        <strong>Phone:</strong>
        <?= htmlspecialchars($row['customer_phone'] ?: 'Not provided') ?>
        <br>

        <strong>Created:</strong>
        <?= htmlspecialchars($row['created_at']) ?>
        <br><br>

        <a href="view_ai_conversation.php?token=<?= urlencode($row['conversation_token']) ?>" target="_blank">
            Open conversation page
        </a>
    </div>

    <pre><?= htmlspecialchars($row['conversation_text']) ?></pre>

</div>

<?php endforeach; ?>

</body>
</html>