<?php
session_start();

require_once __DIR__ . '/../includes/db.php';

$pageTitle = "AI Estimate Requests";

$pdo = getDbConnection();

$stmt = $pdo->query("
    SELECT 
        c.*,
        COUNT(DISTINCT u.id) AS upload_count,
        COUNT(DISTINCT a.id) AS answer_count
    FROM ai_conversations c
    LEFT JOIN ai_uploads u ON u.conversation_id = c.id
    LEFT JOIN ai_answers a ON a.conversation_id = c.id
    GROUP BY c.id
    ORDER BY c.created_at DESC
    LIMIT 100
");

$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>AI Estimate Requests</title>
    <style>
        body{font-family:Arial,sans-serif;background:#f4f6f8;margin:0;padding:30px;color:#2f343a}
        .wrap{max-width:1200px;margin:0 auto}
        .card{background:#fff;border-radius:14px;padding:22px;box-shadow:0 2px 10px rgba(0,0,0,.08);margin-bottom:18px}
        table{width:100%;border-collapse:collapse;background:#fff}
        th,td{padding:12px;border-bottom:1px solid #ddd;text-align:left}
        th{background:#111;color:#fff}
        .badge{display:inline-block;padding:5px 9px;border-radius:999px;background:#eaf4ff;color:#155a91;font-size:12px;font-weight:bold}
        .btn{display:inline-block;background:#1d72b8;color:#fff;padding:8px 12px;border-radius:7px;text-decoration:none}
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <h1>🤖 AI Estimate Requests</h1>
        <p>Review AI-assisted quote conversations, uploaded plans/photos, and customer answers.</p>
    </div>

    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>Reference</th>
                    <th>Service</th>
                    <th>Status</th>
                    <th>Answers</th>
                    <th>Uploads</th>
                    <th>Created</th>
                    <th>Review</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$conversations): ?>
                <tr>
                    <td colspan="7">No AI estimate requests yet.</td>
                </tr>
            <?php endif; ?>

            <?php foreach ($conversations as $c): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($c['reference']) ?></strong></td>
                    <td><?= htmlspecialchars($c['service']) ?></td>
                    <td><span class="badge"><?= htmlspecialchars($c['status']) ?></span></td>
                    <td><?= (int)$c['answer_count'] ?></td>
                    <td><?= (int)$c['upload_count'] ?></td>
                    <td><?= htmlspecialchars($c['created_at']) ?></td>
                    <td>
                        <a class="btn" href="ai_estimate_view.php?ref=<?= urlencode($c['reference']) ?>">Open</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
