<?php
session_start();

require_once __DIR__ . '/includes/db.php';

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');

if ($token === '') {
    die('Invalid invite link.');
}

$stmt = $pdo->prepare("
    SELECT *
    FROM special_customer_invites
    WHERE token = ?
    LIMIT 1
");
$stmt->execute([$token]);
$invite = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$invite) {
    die('Invite not found.');
}

if (!empty($invite['used_at'])) {
    die('This invite has already been used.');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (strlen($password) < 8) {
        $error = 'Please enter a password with at least 8 characters.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } else {

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $existingStmt = $pdo->prepare("
            SELECT id
            FROM users
            WHERE email = ?
            LIMIT 1
        ");
        $existingStmt->execute([$invite['email']]);
        $existingUser = $existingStmt->fetch(PDO::FETCH_ASSOC);

        if ($existingUser) {

            $updateStmt = $pdo->prepare("
                UPDATE users
                SET
                    name = ?,
                    phone = ?,
                    address = ?,
                    password_hash = ?,
                    pricing_mode = ?,
                    hourly_rate = ?,
                    minimum_hours = ?,
                    service_zone = ?
                WHERE id = ?
            ");

            $updateStmt->execute([
                $invite['contact_name'],
                $invite['phone'],
                $invite['billing_address'],
                $passwordHash,
                $invite['pricing_mode'],
                $invite['hourly_rate'],
                $invite['minimum_hours'],
                $invite['service_zone'],
                $existingUser['id']
            ]);

            $userId = $existingUser['id'];

        } else {

            $insertStmt = $pdo->prepare("
                INSERT INTO users
                (
                    name,
                    email,
                    phone,
                    address,
                    password_hash,
                    role,
                    pricing_mode,
                    hourly_rate,
                    minimum_hours,
                    service_zone
                )
                VALUES (?, ?, ?, ?, ?, 'customer', ?, ?, ?, ?)
            ");

            $insertStmt->execute([
                $invite['contact_name'],
                $invite['email'],
                $invite['phone'],
                $invite['billing_address'],
                $passwordHash,
                $invite['pricing_mode'],
                $invite['hourly_rate'],
                $invite['minimum_hours'],
                $invite['service_zone']
            ]);

            $userId = $pdo->lastInsertId();
        }

        $usedStmt = $pdo->prepare("
            UPDATE special_customer_invites
            SET used_at = NOW()
            WHERE id = ?
        ");
        $usedStmt->execute([$invite['id']]);

        $_SESSION['user_id'] = $userId;
        $_SESSION['user_name'] = $invite['contact_name'];
        $_SESSION['user_role'] = 'customer';

        header('Location: customer/dashboard.php');
        exit;
    }
}

include __DIR__ . '/includes/header.php';
?>

<main class="py-5 bg-dark text-white">
    <div class="container" style="max-width:650px;">

        <h2 class="text-info mb-4">Complete Your Customer Account</h2>

        <div class="card bg-secondary p-4 rounded-4 border-0">

            <p class="mb-3">
                Hi <strong><?= htmlspecialchars($invite['contact_name']) ?></strong>,
                Mike has set up special customer booking access for you.
            </p>

            <div class="alert alert-dark">
                <strong>Your agreed booking setup:</strong><br>
                Hourly rate: $<?= number_format((float)$invite['hourly_rate'], 2) ?><br>
                Minimum booking: <?= htmlspecialchars($invite['minimum_hours']) ?> hours<br>
                Service zone: <?= htmlspecialchars($invite['service_zone']) ?>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST">

                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

                <label class="form-label fw-bold">Contact / Company Name</label>
                <input class="form-control mb-3" value="<?= htmlspecialchars($invite['contact_name']) ?>" readonly>

                <label class="form-label fw-bold">First Name</label>
                <input class="form-control mb-3" value="<?= htmlspecialchars($invite['first_name'] ?? '') ?>" readonly>

                <label class="form-label fw-bold">Last Name</label>
                <input class="form-control mb-3" value="<?= htmlspecialchars($invite['last_name'] ?? '') ?>" readonly>

                <label class="form-label fw-bold">Email</label>
                <input class="form-control mb-3" value="<?= htmlspecialchars($invite['email']) ?>" readonly>

                <label class="form-label fw-bold">Phone</label>
                <input class="form-control mb-3" value="<?= htmlspecialchars($invite['phone'] ?? '') ?>" readonly>

                <label class="form-label fw-bold">Billing Address</label>
                <textarea class="form-control mb-3" readonly><?= htmlspecialchars($invite['billing_address'] ?? '') ?></textarea>

                <label class="form-label fw-bold">Create Password</label>
                <input type="password" name="password" class="form-control mb-3" required>

                <label class="form-label fw-bold">Confirm Password</label>
                <input type="password" name="confirm_password" class="form-control mb-4" required>

                <button class="btn btn-warning fw-bold rounded-pill w-100">
                    Create My Account
                </button>

            </form>

        </div>

    </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>