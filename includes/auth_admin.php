<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$base = ($_SERVER['HTTP_HOST'] ?? '') === 'localhost'
    ? '/mike_of_all_trades'
    : '';

if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ' . $base . '/login.php');
    exit;
}
