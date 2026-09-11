<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/admin_session.php';

$role = $_SESSION['user_role'] ?? $_SESSION['role'] ?? '';
if (empty($_SESSION['user_id']) || $role !== 'admin') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Your admin login expired. Return to the admin page and log in again.');
}
