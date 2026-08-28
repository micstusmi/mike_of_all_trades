<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    // This file is used from /admin/work/, which is two levels below the project root.
    header('Location: ../../login.php');
    exit;
}
