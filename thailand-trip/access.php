<?php

require_once __DIR__ . '/../includes/trip_auth.php';

if (tripMemberIsLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = $_SESSION['trip_login_error'] ?? '';
unset($_SESSION['trip_login_error']);

function e(?string $value): string
{
    return htmlspecialchars(
        $value ?? '',
        ENT_QUOTES,
        'UTF-8'
    );
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <meta name="robots" content="noindex, nofollow">

    <title>Thailand Trip Member Login</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <style>
        :root {
            --navy: #102d44;
            --blue: #19577d;
            --gold: #f4b942;
        }

        * {
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            margin: 0;
            display: grid;
            place-items: center;
            padding: 24px;
            color: #1f2d36;
            background:
                linear-gradient(
                    rgba(5, 25, 38, 0.82),
                    rgba(5, 25, 38, 0.9)
                ),
                url(
                    "assets/images/week1-northern-thailand.jpg"
                )
                center / cover fixed;
            font-family:
                system-ui,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                sans-serif;
        }

        .login-card {
            width: min(100%, 460px);
            padding: 34px;
            border-radius: 24px;
            background: rgba(255, 255, 255, 0.97);
            box-shadow: 0 28px 80px rgba(0, 0, 0, 0.38);
        }

        .login-icon {
            margin-bottom: 12px;
            font-size: 3rem;
            text-align: center;
        }

        h1 {
            margin-bottom: 8px;
            font-size: 2rem;
            font-weight: 850;
            text-align: center;
        }

        .login-intro {
            margin-bottom: 26px;
            color: #687782;
            text-align: center;
        }

        label {
            margin-bottom: 7px;
            font-weight: 750;
        }

        .form-control {
            min-height: 52px;
            border-radius: 11px;
        }

        .login-button {
            min-height: 52px;
            border: 0;
            border-radius: 11px;
            color: #1d2930;
            background: var(--gold);
            font-weight: 850;
        }

        .login-button:hover {
            color: #1d2930;
            background: #e8a923;
        }

        .public-link {
            display: block;
            margin-top: 20px;
            color: var(--blue);
            font-weight: 700;
            text-align: center;
            text-decoration: none;
        }
    </style>
</head>

<body>

<div class="login-card">

    <div class="login-icon">🏍️</div>

    <h1>Group member login</h1>

    <p class="login-intro">
        Enter your mobile number and personal PIN to open the private
        itinerary, bookings and expense planner.
    </p>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <form action="login.php" method="post">

        <div class="mb-3">
            <label for="phone">
                Mobile number
            </label>

            <input
                type="tel"
                class="form-control"
                id="phone"
                name="phone"
                autocomplete="tel"
                placeholder="0400 000 000"
                required
            >
        </div>

        <div class="mb-4">
            <label for="pin">
                Personal PIN
            </label>

            <input
                type="password"
                inputmode="numeric"
                pattern="[0-9]{4,8}"
                maxlength="8"
                class="form-control"
                id="pin"
                name="pin"
                autocomplete="current-password"
                placeholder="Enter your PIN"
                required
            >
        </div>

        <button
            class="login-button w-100"
            type="submit"
        >
            Open private trip planner
        </button>

    </form>

    <a class="public-link" href="index.php">
        ← Return to public trip overview
    </a>

</div>

</body>
</html>
