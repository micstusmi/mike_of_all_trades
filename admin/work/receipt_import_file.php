<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../includes/work_tracker.php';

$jobId = (int)($_GET['job_id'] ?? 0);
$importId = (int)($_GET['import_id'] ?? 0);

wt_job($pdo, $jobId);

$query = $pdo->prepare(
    'SELECT *
     FROM work_receipt_imports
     WHERE id=?
       AND job_id=?'
);
$query->execute([$importId, $jobId]);
$import = $query->fetch(PDO::FETCH_ASSOC);

if (!$import) {
    http_response_code(404);
    exit('Spreadsheet import not found.');
}

$path = dirname(__DIR__, 2)
    . '/'
    . ltrim((string)$import['relative_path'], '/');

if (!is_file($path)) {
    http_response_code(404);
    exit('Spreadsheet file is missing.');
}

$name = preg_replace(
    '/[^A-Za-z0-9._-]+/',
    '_',
    basename((string)$import['original_name'])
) ?: 'receipt-spreadsheet';

header('Content-Type: ' . $import['mime_type']);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: attachment; filename="' . $name . '"');
header('Cache-Control: private, no-store');
readfile($path);
