<?php
declare(strict_types=1);

require_once __DIR__ . '/_admin_auth.php';
require_once __DIR__ . '/../../includes/work_tracker.php';

header('Content-Type: application/json; charset=utf-8');

function posted_fail(string $message, int $status = 400): never
{
    http_response_code($status);
    echo json_encode(['ok' => false, 'error' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    posted_fail('POST required.', 405);
}

$photoId = (int)($_POST['photo_id'] ?? 0);
$platform = (string)($_POST['platform'] ?? '');
$posted = (int)($_POST['posted'] ?? 0) === 1;

if ($photoId <= 0 || !in_array($platform, ['instagram', 'tiktok', 'facebook'], true)) {
    posted_fail('Invalid request.');
}

$column = $platform . '_posted_at';

try {
    $stmt = $pdo->prepare("
        UPDATE work_task_photos
        SET {$column} = " . ($posted ? 'COALESCE(' . $column . ', NOW())' : 'NULL') . "
        WHERE id=?
        LIMIT 1
    ");
    $stmt->execute([$photoId]);
} catch (Throwable $e) {
    posted_fail('The posted checkbox needs the v8.12N social-platform migration first.', 500);
}

echo json_encode([
    'ok' => true,
    'posted' => $posted,
]);
