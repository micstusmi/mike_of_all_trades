<?php
session_start();

require_once 'includes/db.php';

$error = '';

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
            header('Location: admin/dashboard.php');
            exit;
        }

        $return = $_GET['return'] ?? '';

        if ($return === 'quotes_bookings.php') {
            header('Location: quotes_bookings.php?restore=1');
            exit;
        }

        header('Location: customer/dashboard.php');
        exit;
    }

    $error = 'Invalid email or password.';
}

include 'includes/header.php';
?>

<main class="py-5 bg-dark text-white">
    <div class="container" style="max-width:500px;">

        <h2 class="mb-4">Login</h2>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" class="card p-4 bg-secondary border-0 rounded-4">

            <label class="form-label fw-bold">Email</label>
            <input type="email" name="email" class="form-control mb-3" required>

            <label class="form-label fw-bold">Password</label>
            <input type="password" name="password" class="form-control mb-3" required>

            <button class="btn btn-warning fw-bold rounded-pill">
                Login
            </button>

            <div class="text-center mt-3">
                <a href="forgot_password.php">Forgot password?</a>
            </div>

        </form>

        <p class="mt-3">
            No account yet?
            <a href="register.php">Register here</a>
        </p>

    </div>
</main>

<?php include 'includes/footer.php'; ?>