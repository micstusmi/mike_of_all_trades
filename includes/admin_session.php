<?php
declare(strict_types=1);

const ADMIN_SESSION_IDLE_SECONDS = 86400; // 24 hours since last admin request

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', (string)ADMIN_SESSION_IDLE_SECONDS);
    session_set_cookie_params([
        'lifetime' => ADMIN_SESSION_IDLE_SECONDS,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Sliding expiry: each authenticated admin request renews the 24-hour window.
$role = $_SESSION['user_role'] ?? $_SESSION['role'] ?? '';
if (!empty($_SESSION['user_id']) && $role === 'admin') {
    setcookie(session_name(), session_id(), [
        'expires' => time() + ADMIN_SESSION_IDLE_SECONDS,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

