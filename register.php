<?php
session_start();

require_once 'includes/db.php';

$error = '';
$success = '';

$discountRequest = isset($_GET['discount_request']) && $_GET['discount_request'] == 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $firstName    = trim($_POST['first_name'] ?? '');
    $lastName     = trim($_POST['last_name'] ?? '');
    $businessName = trim($_POST['business_name'] ?? '');
    $phone        = trim($_POST['phone'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $address      = trim($_POST['address'] ?? '');
    $password     = $_POST['password'] ?? '';

    $fullName = trim($firstName . ' ' . $lastName);
    $discountRequested = !empty($_POST['discount_request']) ? 1 : 0;

    if (!$firstName || !$lastName || !$phone || !$email || !$password) {
        $error = 'Please complete all required fields.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } else {

        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("
                INSERT INTO users 
                (
                    name,
                    first_name,
                    last_name,
                    business_name,
                    phone,
                    email,
                    address,
                    password_hash,
                    role,
                    discount_requested
                )
                VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, 'customer', ?)
            ");

            $stmt->execute([
                $fullName,
                $firstName,
                $lastName,
                $businessName,
                $phone,
                $email,
                $address,
                $hash,
                $discountRequested
            ]);

            if ($discountRequested) {
                @mail(
                    'mike@mikeofalltrades.com.au',
                    'New Discount Request',
                    "A customer has requested a discount account:\n\nName: {$fullName}\nBusiness: {$businessName}\nPhone: {$phone}\nEmail: {$email}\nAddress: {$address}"
                );
            }

            $success = 'Account created. You can now log in.';

        } catch (Exception $e) {
            $error = 'That email may already be registered.';
        }
    }
}

include 'includes/header.php';
?>

<main class="py-5 bg-dark text-white">
    <div class="container" style="max-width:650px;">

        <h2 class="mb-4">Register</h2>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="card p-4 bg-secondary border-0 rounded-4">

            <?php if ($discountRequest): ?>
                <input type="hidden" name="discount_request" value="1">

                <div class="alert alert-warning">
                    Discount setup can take up to 24 hours. Once reviewed, your approved customer discount will be applied to your account.
                </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">First Name *</label>
                    <input type="text" name="first_name" class="form-control" required>
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Last Name *</label>
                    <input type="text" name="last_name" class="form-control" required>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold">Business Name</label>
                <input type="text" name="business_name" class="form-control" placeholder="Optional">
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold">Phone Number *</label>
                <input type="tel" name="phone" class="form-control" required>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold">Email Address *</label>
                <input type="email" name="email" class="form-control" required>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold">Primary Address</label>
                <textarea name="address" class="form-control" rows="3" placeholder="Optional"></textarea>
            </div>

            <div class="mb-4">
                <label class="form-label fw-bold">Password *</label>

                <div class="input-group">
                    <input
                        type="password"
                        name="password"
                        id="passwordField"
                        class="form-control"
                        required
                    >

                    <button
                        class="btn btn-dark"
                        type="button"
                        onclick="togglePassword()"
                    >
                        <i id="passwordEye" class="bi bi-eye"></i>
                    </button>
                </div>

                <small class="text-light">
                    Minimum 8 characters.
                </small>
            </div>

            <button class="btn btn-warning fw-bold rounded-pill">
                Create Account
            </button>

        </form>

        <p class="mt-3">
            Already registered?
            <a href="login.php">Login here</a>
        </p>

    </div>
</main>

<script>
function togglePassword(){
    const field = document.getElementById('passwordField');
    const icon = document.getElementById('passwordEye');

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

<?php include 'includes/footer.php'; ?>