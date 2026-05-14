<?php
session_start();
require_once __DIR__ . '/includes/db.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    $stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $update = $pdo->prepare("
            UPDATE users
            SET reset_token_hash = ?,
                reset_token_expires = ?
            WHERE id = ?
        ");
        $update->execute([$tokenHash, $expires, $user['id']]);

        $resetLink = "https://mikeofalltrades.com.au/reset_password.php?token=" . urlencode($token);

        $subject = "Reset your Mike Of All Trades password";
        $body =
"Hi {$user['name']},

You requested a password reset.

Click this link to reset your password:

{$resetLink}

This link will expire in 1 hour.

If you did not request this, you can ignore this email.

Mike Of All Trades";

        $headers = "From: Mike Of All Trades <mike@mikeofalltrades.com.au>\r\n";
        $headers .= "Reply-To: mike@mikeofalltrades.com.au\r\n";

        @mail($user['email'], $subject, $body, $headers);
    }

    $message = 'If that email exists in our system, a password reset link has been sent.';
}

include __DIR__ . '/includes/header.php';
?>

<main class="py-5 bg-dark text-white">
    <div class="container" style="max-width:500px;">
        <h2 class="mb-4">Forgot Password</h2>

        <?php if ($message): ?>
            <div class="alert alert-info"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <form method="POST" class="card p-4 bg-secondary border-0 rounded-4">
            <label class="form-label fw-bold">Email Address</label>
            <input type="email" name="email" class="form-control mb-3" required>

            <button class="btn btn-warning fw-bold rounded-pill">
                Send Reset Link
            </button>
        </form>

        <p class="mt-3">
            <a href="login.php">Back to login</a>
        </p>
    </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>