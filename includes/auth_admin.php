<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$base = ($_SERVER['HTTP_HOST'] ?? '') === 'localhost'
    ? '/mike_of_all_trades'
    : '';

$role = $_SESSION['user_role'] ?? $_SESSION['role'] ?? '';

if (empty($_SESSION['user_id']) || $role !== 'admin') {
    header('Location: ' . $base . '/login.php');
    exit;
}
