<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/work_tracker.php';

$token = trim((string)($_GET['t'] ?? ''));
$receiptId = (int)($_GET['receipt_id'] ?? 0);
try {
    $job = wt_job_by_token($pdo, $token);
} catch (Throwable $error) {
    http_response_code(404);
    exit('Receipt not found.');
}

$query = $pdo->prepare('SELECT * FROM work_receipts WHERE id=? AND job_id=?');
$query->execute([$receiptId, (int)$job['id']]);
$receipt = $query->fetch(PDO::FETCH_ASSOC);
if (!$receipt) {
    http_response_code(404);
    exit('Receipt not found.');
}

$path = dirname(__DIR__) . '/' . ltrim((string)$receipt['relative_path'], '/');
if (!is_file($path)) {
    http_response_code(404);
    exit('Receipt file is unavailable.');
}

$name = preg_replace('/[^A-Za-z0-9._-]+/', '_', basename((string)$receipt['original_name'])) ?: 'receipt';
header('Content-Type: ' . (string)$receipt['mime_type']);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="' . $name . '"');
header('Cache-Control: private, no-store, max-age=0');
header('X-Robots-Tag: noindex, nofollow, noarchive');
readfile($path);

