<?php
session_start();

require_once __DIR__ . '/../includes/db.php';
include __DIR__ . '/../includes/header.php';

if (empty($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$userId = $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT *
    FROM ai_conversations
    WHERE user_id = ?
    ORDER BY created_at DESC
");

$stmt->execute([$userId]);
$chats = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<main class="py-5 bg-dark text-white">
    <div class="container" style="max-width:900px;">
        <h2 class="text-info mb-4">My Saved AI Chats</h2>

        <?php if (!$chats): ?>
            <div class="alert alert-secondary">
                You do not have any saved AI chats yet.
            </div>
        <?php else: ?>
            <?php foreach ($chats as $chat): ?>
                <div class="card bg-secondary text-white mb-3 p-3 rounded-4">
                    <div class="small text-warning mb-2">
                        Saved: <?= htmlspecialchars($chat['created_at'] ?? '') ?>
                    </div>

                    <pre style="white-space:pre-wrap;max-height:220px;overflow:auto;background:#111;color:white;padding:12px;border-radius:10px;"><?= htmlspecialchars($chat['conversation_text']) ?></pre>

                    <a class="btn btn-info rounded-pill fw-bold mt-2"
                       href="../view_ai_conversation.php?token=<?= urlencode($chat['conversation_token']) ?>">
                        Open saved chat
                    </a>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>