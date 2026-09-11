<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/admin_session.php';

$role = $_SESSION['user_role'] ?? $_SESSION['role'] ?? '';
if (empty($_SESSION['user_id']) || $role !== 'admin') {
    $returnTo = $_SERVER['REQUEST_URI'] ?? '';
    header('Location: ../../login.php?return_to=' . rawurlencode($returnTo));
    exit;
}
