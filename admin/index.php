<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!empty($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'admin') {
    header('Location: /admin/dashboard.php');
    exit;
}

header('Location: /admin/login.php');
exit;