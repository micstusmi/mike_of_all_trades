<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

require_once __DIR__ . '/includes/db.php';

$error = '';

$claimAiChat = $_POST['claim_ai_chat'] ?? $_GET['claim_ai_chat'] ?? '';

$returnTo =
    $_POST['return_to']
    ?? $_GET['return_to']
    ?? $_POST['return']
    ?? $_GET['return']
    ?? '';

function safeAdminReturnUrl(string $value): string
{
    $value = trim($value);

    if ($value === '') {
        return '';
    }

    /*
     * Reject control characters, header injection,
     * Windows-style separators and protocol-relative URLs.
     */
    if (
        preg_match('/[\x00-\x1F\x7F]/', $value) ||
        str_contains($value, "\\") ||
        str_starts_with($value, '//')
    ) {
        return '';
    }

    $parts = parse_url($value);

    if ($parts === false) {
        return '';
    }

    /*
     * Return destinations must be local URLs, never another host.
     */
    foreach ([
        'scheme',
        'host',
        'user',
        'pass',
        'port'
    ] as $forbiddenPart) {
        if (isset($parts[$forbiddenPart])) {
            return '';
        }
    }

    $path = (string)($parts['path'] ?? '');

    if (
        !str_starts_with($path, '/admin/') &&
        !str_starts_with(
            $path,
            '/mike_of_all_trades/admin/'
        )
    ) {
        return '';
    }

    /*
     * Reject encoded or literal path traversal.
     */
    $decodedPath = rawurldecode($path);

    if (
        str_contains($decodedPath, '/../') ||
        str_ends_with($decodedPath, '/..')
    ) {
        return '';
    }

    return $value;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password_hash'])) {

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_role'] = $user['role'];

        if ($user['role'] === 'admin') {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_last_activity_at'] = time();

            session_regenerate_id(true);

            setcookie(session_name(), session_id(), [
                'expires' => time() + 86400,
                'path' => '/',
                'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }

        if ($claimAiChat !== '') {
            header('Location: claim_ai_chat.php?token=' . urlencode($claimAiChat));
            exit;
        }

        if ($returnTo === 'quotes_bookings.php') {
            header('Location: quotes_bookings.php?restore=1');
            exit;
        }

        if ($user['role'] === 'admin') {
            $adminReturn = safeAdminReturnUrl($returnTo);

            if ($adminReturn !== '') {
                header('Location: ' . $adminReturn);
                exit;
            }

            header('Location: admin/dashboard.php');
            exit;
        }

        header('Location: customer/dashboard.php');
        exit;
    }

    $error = 'Invalid email or password.';
}

include __DIR__ . '/includes/header.php';
?>

<main class="py-5 bg-dark text-white">
    <div class="container" style="max-width:500px;">

        <h2 class="mb-4">Login</h2>

        <?php if ($claimAiChat !== ''): ?>
            <div class="alert alert-info">
                Your AI chat has been saved. Please log in to attach it to your account.
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="card p-4 bg-secondary border-0 rounded-4">

            <?php if ($claimAiChat !== ''): ?>
                <input
                    type="hidden"
                    name="claim_ai_chat"
                    value="<?= htmlspecialchars($claimAiChat) ?>"
                >
            <?php endif; ?>

            <?php if ($returnTo !== ''): ?>
                <input
                    type="hidden"
                    name="return_to"
                    value="<?= htmlspecialchars($returnTo) ?>"
                >
            <?php endif; ?>

            <div class="mb-3">
                <label class="form-label fw-bold">Email Address</label>
                <input
                    type="email"
                    name="email"
                    class="form-control"
                    placeholder="name@example.com"
                    required
                >
            </div>

            <div class="mb-4">
                <label class="form-label fw-bold">Password</label>

                <div class="input-group">
                    <input
                        type="password"
                        name="password"
                        id="loginPasswordField"
                        class="form-control"
                        placeholder="Password"
                        required
                    >

                    <button
                        class="btn btn-dark"
                        type="button"
                        onclick="toggleLoginPassword()"
                    >
                        <i id="loginPasswordEye" class="bi bi-eye"></i>
                    </button>
                </div>
            </div>

            <button class="btn btn-warning fw-bold rounded-pill w-100">
                Login
            </button>

            <div class="text-center mt-3">
                <a href="forgot_password.php" class="text-info">
                    Forgot password?
                </a>
            </div>

        </form>

        <p class="mt-3">
            No account yet?
            <a href="register.php<?= $claimAiChat !== '' ? '?claim_ai_chat=' . urlencode($claimAiChat) : '' ?>">
                Register here
            </a>
        </p>

    </div>
</main>

<script>
function toggleLoginPassword(){
    const field = document.getElementById('loginPasswordField');
    const icon = document.getElementById('loginPasswordEye');

    if(field.type === 'password'){
        field.type = 'text';
        icon.classList.remove('bi-eye');
        icon.classList.add('bi-eye-slash');
    } else {
        field.type = 'password';
        icon.classList.remove('bi-eye-slash');
        icon.classList.add('bi-eye');
    }
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>