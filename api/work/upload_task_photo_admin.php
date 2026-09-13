<?php
declare(strict_types=1);

require_once __DIR__ . '/_admin_auth.php';
require_once __DIR__ . '/../../includes/work_tracker.php';

function fail_photo(string $message, int $status = 400): never
{
    http_response_code($status);
    die(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail_photo('POST required.', 405);
}

$jobId = (int)($_POST['job_id'] ?? 0);
$taskId = (int)($_POST['task_id'] ?? 0);
$type = (string)($_POST['photo_type'] ?? '');
$note = trim((string)($_POST['note'] ?? ''));

if (
    $jobId <= 0 ||
    $taskId <= 0 ||
    !in_array($type, ['before', 'after'], true)
) {
    fail_photo('Invalid request.');
}

wt_job($pdo, $jobId);

$q = $pdo->prepare("
    SELECT id
    FROM work_tasks
    WHERE id=? AND job_id=?
    LIMIT 1
");
$q->execute([$taskId, $jobId]);

if (!$q->fetchColumn()) {
    fail_photo('Task not found.', 404);
}

/*
 * V8.8 accepts:
 *
 *   photos[]   — new multiple-file admin controls
 *   photo      — backwards compatibility with any older control
 */
$files = $_FILES['photos'] ?? $_FILES['photo'] ?? null;

if (!$files || !isset($files['name'])) {
    fail_photo('Choose at least one image.');
}

$names = is_array($files['name'])
    ? $files['name']
    : [$files['name']];

$tmpNames = is_array($files['tmp_name'])
    ? $files['tmp_name']
    : [$files['tmp_name']];

$errors = is_array($files['error'])
    ? $files['error']
    : [$files['error']];

$sizes = is_array($files['size'])
    ? $files['size']
    : [$files['size']];

if (count($names) > 8) {
    fail_photo('Please upload no more than 8 photos at once.');
}

$allowed = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
];

$base = wt_env(
    'WORKTRACKER_PRIVATE_UPLOAD_DIR',
    dirname(__DIR__, 2) . '/storage/private/job_intake'
);

$photoBase = dirname(rtrim($base, '/')) . '/task_photos';
$dir = $photoBase . '/job_' . $jobId . '/task_' . $taskId;

if (
    !is_dir($dir) &&
    !mkdir($dir, 0770, true) &&
    !is_dir($dir)
) {
    fail_photo('Could not create photo directory.', 500);
}

$denyFile = $photoBase . '/.htaccess';

if (!is_file($denyFile)) {
    @file_put_contents(
        $denyFile,
        "<IfModule mod_authz_core.c>\n" .
        "Require all denied\n" .
        "</IfModule>\n" .
        "<IfModule !mod_authz_core.c>\n" .
        "Deny from all\n" .
        "</IfModule>\n"
    );
}

$finfo = new finfo(FILEINFO_MIME_TYPE);

$insert = $pdo->prepare("
    INSERT INTO work_task_photos
    (
        job_id,
        task_id,
        photo_type,
        uploader_type,
        original_name,
        stored_name,
        relative_path,
        mime_type,
        file_size,
        sha256,
        note
    )
    VALUES
    (
        ?,?,?,?,?,?,?,?,?,?,?
    )
");

$uploaded = 0;

foreach ($names as $i => $originalName) {

    if (($errors[$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        continue;
    }

    $size = (int)($sizes[$i] ?? 0);

    if ($size <= 0 || $size > 12 * 1024 * 1024) {
        continue;
    }

    $tmp = (string)($tmpNames[$i] ?? '');

    if ($tmp === '' || !is_uploaded_file($tmp)) {
        continue;
    }

    $mime = $finfo->file($tmp);

    if (!isset($allowed[$mime])) {
        continue;
    }

    $safeOriginal = preg_replace(
        '/[^A-Za-z0-9._-]+/',
        '_',
        basename((string)$originalName)
    ) ?: 'photo.' . $allowed[$mime];

    $stored =
        $type .
        '_' .
        date('Ymd_His') .
        '_' .
        bin2hex(random_bytes(5)) .
        '.' .
        $allowed[$mime];

    $dest = $dir . '/' . $stored;

    if (!move_uploaded_file($tmp, $dest)) {
        continue;
    }

    $relative =
        'job_' .
        $jobId .
        '/task_' .
        $taskId .
        '/' .
        $stored;

    $insert->execute([
        $jobId,
        $taskId,
        $type,
        'mike',
        $safeOriginal,
        $stored,
        $relative,
        $mime,
        $size,
        hash_file('sha256', $dest) ?: null,
        $note !== '' ? $note : null,
    ]);

    $uploaded++;
}

if ($uploaded < 1) {
    fail_photo(
        'No valid photo was uploaded. ' .
        'Use JPG, PNG or WEBP up to 12 MB each.'
    );
}

header(
    'Location: ../../admin/work/task_photos.php?id=' .
    $jobId .
    '&saved=1&uploaded=' .
    $uploaded .
    '#task-' .
    $taskId
);
exit;
