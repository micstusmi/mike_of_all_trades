<?php
declare(strict_types=1);

require_once __DIR__ . '/_admin_auth.php';
require_once __DIR__ . '/../../includes/work_tracker.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('POST required');
}

$jobId = (int)($_POST['job_id'] ?? 0);
$materialId = (int)($_POST['material_id'] ?? 0);

if ($jobId <= 0 || $materialId <= 0) {
    exit('Invalid request.');
}

wt_job($pdo, $jobId);

$q = $pdo->prepare("
    SELECT id
    FROM work_materials
    WHERE id=? AND job_id=?
");
$q->execute([$materialId, $jobId]);

if (!$q->fetchColumn()) {
    exit('Material record not found.');
}

if (
    empty($_FILES['receipt']) ||
    !isset($_FILES['receipt']['error']) ||
    $_FILES['receipt']['error'] !== UPLOAD_ERR_OK
) {
    exit('Receipt upload failed.');
}

$file = $_FILES['receipt'];

if ((int)$file['size'] <= 0 || (int)$file['size'] > 12 * 1024 * 1024) {
    exit('Receipt must be smaller than 12 MB.');
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file['tmp_name']);

$extensions = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
    'application/pdf' => 'pdf',
];

if (!isset($extensions[$mime])) {
    exit('Receipt must be JPG, PNG, WEBP or PDF.');
}

$root = dirname(__DIR__, 2);
$relativeDir = 'storage/private/work_receipts/job_' . $jobId;
$absoluteDir = $root . '/' . $relativeDir;

if (!is_dir($absoluteDir)) {
    if (!mkdir($absoluteDir, 0775, true) && !is_dir($absoluteDir)) {
        exit('Could not create receipt storage directory.');
    }
}

$filename =
    'material_' .
    $materialId .
    '_' .
    bin2hex(random_bytes(12)) .
    '.' .
    $extensions[$mime];

$absolutePath = $absoluteDir . '/' . $filename;
$relativePath = $relativeDir . '/' . $filename;

if (!move_uploaded_file($file['tmp_name'], $absolutePath)) {
    exit('Could not save receipt.');
}

$q = $pdo->prepare("
    SELECT receipt_path
    FROM work_materials
    WHERE id=? AND job_id=?
");
$q->execute([$materialId, $jobId]);
$oldPath = (string)($q->fetchColumn() ?: '');

$u = $pdo->prepare("
    UPDATE work_materials
    SET receipt_path=?, updated_at=NOW()
    WHERE id=? AND job_id=?
");
$u->execute([
    $relativePath,
    $materialId,
    $jobId,
]);

/*
 * Replace the old receipt only after the database update succeeded.
 * Path safety prevents deletion outside the dedicated receipt tree.
 */
if (
    $oldPath !== '' &&
    str_starts_with($oldPath, 'storage/private/work_receipts/job_')
) {
    $oldAbsolute = $root . '/' . $oldPath;

    if (
        is_file($oldAbsolute) &&
        realpath(dirname($oldAbsolute)) !== false
    ) {
        @unlink($oldAbsolute);
    }
}

header(
    'Location: ../../admin/work/materials.php?id=' .
    $jobId .
    '&receipt_saved=1#material-' .
    $materialId
);
exit;
