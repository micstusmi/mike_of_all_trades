<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

require_once __DIR__ . '/includes/db.php';

$error = '';

$claimAiChat = $_POST['claim_ai_chat'] ?? $_GET['claim_ai_chat'] ?? '';
$return = $_GET['return'] ?? $_POST['return'] ?? '';

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
        }

        if ($claimAiChat !== '') {
            header('Location: claim_ai_chat.php?token=' . urlencode($claimAiChat));
            exit;
        }

        if ($return === 'quotes_bookings.php') {
            header('Location: quotes_bookings.php?restore=1');
            exit;
        }

        if ($user['role'] === 'admin') {
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

            <?php if ($return !== ''): ?>
                <input
                    type="hidden"
                    name="return"
                    value="<?= htmlspecialchars($return) ?>"
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