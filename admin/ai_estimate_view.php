<?php
session_start();

require_once __DIR__ . '/../includes/db.php';

$pdo = getDbConnection();
$ref = trim($_GET['ref'] ?? '');

if ($ref === '') {
    die('Missing reference.');
}

$stmt = $pdo->prepare("SELECT * FROM ai_conversations WHERE reference = ?");
$stmt->execute([$ref]);
$conversation = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$conversation) {
    die('AI estimate request not found.');
}

$stmt = $pdo->prepare("SELECT * FROM ai_answers WHERE conversation_id = ? ORDER BY id ASC");
$stmt->execute([$conversation['id']]);
$answers = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT * FROM ai_uploads WHERE conversation_id = ? ORDER BY id ASC");
$stmt->execute([$conversation['id']]);
$uploads = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT * FROM ai_messages WHERE conversation_id = ? ORDER BY id ASC");
$stmt->execute([$conversation['id']]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function pretty_answer($json) {
    $decoded = json_decode($json, true);
    if (is_array($decoded)) {
        return implode(', ', array_map('strval', $decoded));
    }
    return (string)$decoded;
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>AI Estimate <?= h($conversation['reference']) ?></title>
    <style>
        body{font-family:Arial,sans-serif;background:#f4f6f8;margin:0;padding:30px;color:#2f343a}
        .wrap{max-width:1200px;margin:0 auto}
        .card{background:#fff;border-radius:14px;padding:22px;box-shadow:0 2px 10px rgba(0,0,0,.08);margin-bottom:18px}
        .grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}
        table{width:100%;border-collapse:collapse;background:#fff}
        th,td{padding:10px;border-bottom:1px solid #ddd;text-align:left;vertical-align:top}
        th{background:#111;color:#fff}
        .badge{display:inline-block;padding:5px 9px;border-radius:999px;background:#eaf4ff;color:#155a91;font-size:12px;font-weight:bold}
        .btn{display:inline-block;background:#1d72b8;color:#fff;padding:9px 13px;border-radius:7px;text-decoration:none}
        .upload-box{padding:10px;border:1px solid #ddd;border-radius:10px;margin:8px 0;background:#fafafa}
        .small{color:#666;font-size:13px}
        @media(max-width:900px){.grid{grid-template-columns:1fr}}
    </style>
</head>
<body>
<div class="wrap">

    <div class="card">
        <a class="btn" href="ai_estimates.php">← Back to AI Estimate Requests</a>
        <h1>🤖 AI Estimate Review</h1>
        <p><strong>Reference:</strong> <?= h($conversation['reference']) ?></p>
        <p><strong>Service:</strong> <?= h($conversation['service']) ?></p>
        <p><strong>Status:</strong> <span class="badge"><?= h($conversation['status']) ?></span></p>
        <p><strong>Created:</strong> <?= h($conversation['created_at']) ?></p>
    </div>

    <div class="grid">
        <div class="card">
            <h2>Customer Details</h2>
            <p><strong>Name:</strong> <?= h($conversation['customer_name'] ?? '') ?></p>
            <p><strong>Email:</strong> <?= h($conversation['email'] ?? '') ?></p>
            <p><strong>Phone:</strong> <?= h($conversation['phone'] ?? '') ?></p>
        </div>

        <div class="card">
            <h2>Current Summary</h2>
            <p><strong>Answers:</strong> <?= count($answers) ?></p>
            <p><strong>Uploads:</strong> <?= count($uploads) ?></p>
            <p><strong>Messages:</strong> <?= count($messages) ?></p>
        </div>
    </div>

    <div class="card">
        <h2>Answers</h2>
        <table>
            <thead>
                <tr>
                    <th>Question ID</th>
                    <th>Answer</th>
                    <th>Created</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$answers): ?>
                <tr><td colspan="3">No answers yet.</td></tr>
            <?php endif; ?>

            <?php foreach ($answers as $a): ?>
                <tr>
                    <td><?= h($a['question_id']) ?></td>
                    <td><?= h(pretty_answer($a['answer_json'])) ?></td>
                    <td><?= h($a['created_at']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <h2>Uploads</h2>
        <?php if (!$uploads): ?>
            <p>No uploads yet.</p>
        <?php endif; ?>

        <?php foreach ($uploads as $u): ?>
            <div class="upload-box">
                <strong><?= h($u['original_filename']) ?></strong><br>
                <span class="small">
                    Saved: <?= h($u['saved_filename']) ?><br>
                    Type: <?= h($u['mime_type']) ?> |
                    Size: <?= number_format((int)$u['file_size']) ?> bytes<br>
                    Path: <?= h($u['storage_path']) ?>
                </span>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="card">
        <h2>Conversation Messages</h2>
        <?php if (!$messages): ?>
            <p>No messages yet.</p>
        <?php endif; ?>

        <?php foreach ($messages as $m): ?>
            <div class="upload-box">
                <strong><?= h($m['role']) ?></strong><br>
                <?= nl2br(h($m['message'])) ?><br>
                <span class="small"><?= h($m['created_at']) ?></span>
            </div>
        <?php endforeach; ?>
    </div>

</div>
</body>
</html>
