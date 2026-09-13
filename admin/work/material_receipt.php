<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../includes/work_tracker.php';

$jobId = (int)($_GET['job_id'] ?? 0);
$materialId = (int)($_GET['material_id'] ?? 0);

if ($jobId <= 0 || $materialId <= 0) {
    http_response_code(400);
    exit('Invalid request.');
}

wt_job($pdo, $jobId);

$q = $pdo->prepare("
    SELECT receipt_path
    FROM work_materials
    WHERE id=? AND job_id=?
");
$q->execute([$materialId, $jobId]);

$relativePath = (string)($q->fetchColumn() ?: '');

if (
    $relativePath === '' ||
    !str_starts_with(
        $relativePath,
        'storage/private/work_receipts/job_' . $jobId . '/'
    )
) {
    http_response_code(404);
    exit('Receipt not found.');
}

$root = dirname(__DIR__, 2);
$path = $root . '/' . $relativePath;

if (!is_file($path)) {
    http_response_code(404);
    exit('Receipt file not found.');
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($path) ?: 'application/octet-stream';

$allowed = [
    'image/jpeg',
    'image/png',
    'image/webp',
    'application/pdf',
];

if (!in_array($mime, $allowed, true)) {
    http_response_code(415);
    exit('Unsupported receipt type.');
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="receipt-' . $materialId . '"');
header('X-Content-Type-Options: nosniff');

readfile($path);
exit;
