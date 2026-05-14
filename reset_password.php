<?php
session_start();
require_once __DIR__ . '/includes/db.php';

$error = '';
$success = '';

$token = $_GET['token'] ?? $_POST['token'] ?? '';
$tokenHash = hash('sha256', $token);

$stmt = $pdo->prepare("
    SELECT id
    FROM users
    WHERE reset_token_hash = ?
    AND reset_token_expires > NOW()
    LIMIT 1
");
$stmt->execute([$tokenHash]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    $error = 'This reset link is invalid or has expired.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) {
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $update = $pdo->prepare("
            UPDATE users
            SET password_hash = ?,
                reset_token_hash = NULL,
                reset_token_expires = NULL
            WHERE id = ?
        ");
        $update->execute([$hash, $user['id']]);

        $success = 'Password updated. You can now log in.';
    }
}

include __DIR__ . '/includes/header.php';
?>

<main class="py-5 bg-dark text-white">
    <div class="container" style="max-width:500px;">
        <h2 class="mb-4">Reset Password</h2>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($success) ?><br>
                <a href="login.php">Login here</a>
            </div>
        <?php endif; ?>

        <?php if (!$success && $user): ?>
            <form method="POST" class="card p-4 bg-secondary border-0 rounded-4">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

                <label class="form-label fw-bold">New Password</label>
                <input type="password" name="password" class="form-control mb-3" required>

                <label class="form-label fw-bold">Confirm Password</label>
                <input type="password" name="confirm_password" class="form-control mb-3" required>

                <button class="btn btn-warning fw-bold rounded-pill">
                    Update Password
                </button>
            </form>
        <?php endif; ?>
    </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>