<?php
declare(strict_types=1);

require_once __DIR__ . '/admin_session.php';

$base = ($_SERVER['HTTP_HOST'] ?? '') === 'localhost'
    ? '/mike_of_all_trades'
    : '';

$role = $_SESSION['user_role'] ?? $_SESSION['role'] ?? '';

if (empty($_SESSION['user_id']) || $role !== 'admin') {
    $returnTo = $_SERVER['REQUEST_URI'] ?? '';

    header(
        'Location: ' . $base . '/login.php?return_to=' .
        rawurlencode($returnTo)
    );
    exit;
}
