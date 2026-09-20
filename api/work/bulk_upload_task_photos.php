<?php
declare(strict_types=1);

require_once __DIR__ . '/_admin_auth.php';
require_once __DIR__ . '/../../includes/work_tracker.php';

function bulk_fail(string $message, int $status = 400): never
{
    http_response_code($status);
    exit($message);
}

function bulk_ini_bytes(string $value): int
{
    $value = trim($value);
    if ($value === '') {
        return 0;
    }

    $unit = strtolower(substr($value, -1));
    $number = (float)$value;

    return match ($unit) {
        'g' => (int)round($number * 1024 * 1024 * 1024),
        'm' => (int)round($number * 1024 * 1024),
        'k' => (int)round($number * 1024),
        default => (int)round($number),
    };
}

function bulk_format_bytes(int $bytes): string
{
    if ($bytes >= 1024 * 1024) {
        return round($bytes / 1024 / 1024, 1) . ' MB';
    }

    if ($bytes >= 1024) {
        return round($bytes / 1024, 1) . ' KB';
    }

    return $bytes . ' bytes';
}

function bulk_photo_taken_at(string $path, string $mime, ?int $clientMs): ?DateTimeImmutable
{
    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
        $exif = @exif_read_data($path);
        $raw = $exif['DateTimeOriginal'] ?? $exif['DateTimeDigitized'] ?? $exif['DateTime'] ?? null;
        if (is_string($raw) && preg_match('/^\d{4}:\d{2}:\d{2} \d{2}:\d{2}:\d{2}$/', $raw)) {
            return DateTimeImmutable::createFromFormat(
                'Y:m:d H:i:s',
                $raw,
                new DateTimeZone('Australia/Melbourne')
            ) ?: null;
        }
    }

    if ($clientMs && $clientMs > 0) {
        return (new DateTimeImmutable('@' . (int)floor($clientMs / 1000)))
            ->setTimezone(new DateTimeZone('Australia/Melbourne'));
    }

    return null;
}

function bulk_pick_assignment(?DateTimeImmutable $takenAt, array $sessions, int $fallbackTaskId, string $fallbackType): array
{
    if (!$takenAt) {
        return [$fallbackTaskId, $fallbackType, 'manual_no_timestamp'];
    }

    $photoTs = $takenAt->setTimezone(new DateTimeZone('UTC'))->getTimestamp();
    $byTask = [];

    foreach ($sessions as $session) {
        $taskId = (int)($session['task_id'] ?? 0);
        if ($taskId <= 0) {
            continue;
        }

        $start = (int)$session['start_ts'];
        $end = (int)($session['end_ts'] ?: time());
        $byTask[$taskId]['first'] = min($byTask[$taskId]['first'] ?? $start, $start);
        $byTask[$taskId]['last'] = max($byTask[$taskId]['last'] ?? $end, $end);

        if ($photoTs >= $start && $photoTs <= $end) {
            return [$taskId, 'progress', 'auto_timestamp_during_task_timer'];
        }
    }

    foreach ($byTask as $taskId => $bounds) {
        if ($photoTs >= ((int)$bounds['first'] - 90 * 60) && $photoTs < (int)$bounds['first']) {
            return [(int)$taskId, 'before', 'auto_timestamp_before_task_timer'];
        }
    }

    foreach ($byTask as $taskId => $bounds) {
        if ($photoTs > (int)$bounds['last'] && $photoTs <= ((int)$bounds['last'] + 3 * 60 * 60)) {
            return [(int)$taskId, 'after', 'auto_timestamp_after_task_timer'];
        }
    }

    return [$fallbackTaskId, $fallbackType, 'manual_fallback_timestamp_unmatched'];
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    bulk_fail('POST required.', 405);
}

$postMax = bulk_ini_bytes((string)ini_get('post_max_size'));
$contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($postMax > 0 && $contentLength > $postMax) {
    bulk_fail(
        'That photo batch is too large for this server upload limit. ' .
        'Selected batch: about ' . bulk_format_bytes($contentLength) . '. ' .
        'Server limit: ' . bulk_format_bytes($postMax) . '. ' .
        'Please upload fewer iPhone photos at a time, or increase PHP post_max_size/upload_max_filesize.',
        413
    );
}

$jobId = (int)($_POST['job_id'] ?? 0);
$assignmentMode = (string)($_POST['assignment_mode'] ?? 'auto');
$fallbackTaskId = (int)($_POST['fallback_task_id'] ?? 0);
$fallbackType = (string)($_POST['fallback_photo_type'] ?? 'progress');
$note = trim((string)($_POST['bulk_note'] ?? ''));

if ($jobId <= 0 || !in_array($fallbackType, ['before', 'progress', 'after'], true)) {
    bulk_fail('Invalid request.');
}

wt_job($pdo, $jobId);

$tasks = wt_job_tasks($pdo, $jobId, false);
$taskIds = array_map(static fn($task) => (int)$task['id'], $tasks);
if ($assignmentMode === 'general' || $fallbackTaskId <= 0) {
    $fallbackTaskId = wt_get_or_create_general_photo_task($pdo, $jobId);
    $tasks = wt_job_tasks($pdo, $jobId, false);
    $taskIds = array_map(static fn($task) => (int)$task['id'], $tasks);
}

if (!in_array($fallbackTaskId, $taskIds, true)) {
    bulk_fail('Choose a fallback task first.');
}

$sessions = [];
if ($assignmentMode === 'auto') {
    $sessionStmt = $pdo->prepare("
        SELECT task_id,
               UNIX_TIMESTAMP(started_at) AS start_ts,
               UNIX_TIMESTAMP(COALESCE(ended_at, UTC_TIMESTAMP())) AS end_ts
        FROM work_sessions
        WHERE job_id=?
          AND task_id IS NOT NULL
        ORDER BY started_at,id
    ");
    $sessionStmt->execute([$jobId]);
    $sessions = $sessionStmt->fetchAll(PDO::FETCH_ASSOC);
}

$files = $_FILES['photos'] ?? null;
if (!$files || !isset($files['name'])) {
    bulk_fail('Choose at least one photo.');
}

$names = is_array($files['name']) ? $files['name'] : [$files['name']];
$tmps = is_array($files['tmp_name']) ? $files['tmp_name'] : [$files['tmp_name']];
$errors = is_array($files['error']) ? $files['error'] : [$files['error']];
$sizes = is_array($files['size']) ? $files['size'] : [$files['size']];
$clientTimes = array_values((array)($_POST['client_photo_mtime'] ?? []));

if (count($names) > 40) {
    bulk_fail('Please upload no more than 40 photos at once.');
}

$allowed = wt_allowed_task_photo_types();
$photoBase = wt_task_photo_base_dir();
if (!is_dir($photoBase) && !mkdir($photoBase, 0770, true) && !is_dir($photoBase)) {
    bulk_fail('Private task photo folder could not be created.', 500);
}

$denyFile = $photoBase . '/.htaccess';
if (!is_file($denyFile)) {
    @file_put_contents($denyFile, "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$insert = $pdo->prepare("
    INSERT INTO work_task_photos
    (job_id,task_id,photo_type,uploader_type,original_name,stored_name,relative_path,mime_type,file_size,sha256,note,keep_permanent,photo_taken_at,assignment_method,created_at)
    VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())
");

$uploaded = 0;
$autoCount = 0;
$fallbackCount = 0;

foreach ($names as $i => $originalName) {
    if (($errors[$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        continue;
    }

    $size = (int)($sizes[$i] ?? 0);
    if ($size <= 0 || $size > 20 * 1024 * 1024) {
        continue;
    }

    $tmp = (string)($tmps[$i] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        continue;
    }

    $mime = wt_normalise_uploaded_photo_mime((string)$finfo->file($tmp), (string)$originalName);
    if (!isset($allowed[$mime])) {
        continue;
    }

    $takenAt = bulk_photo_taken_at($tmp, $mime, isset($clientTimes[$i]) ? (int)$clientTimes[$i] : null);
    [$taskId, $photoType, $method] = $assignmentMode === 'auto'
        ? bulk_pick_assignment($takenAt, $sessions, $fallbackTaskId, $fallbackType)
        : [$fallbackTaskId, $fallbackType, $assignmentMode === 'general' ? 'general_all_tasks_bulk' : 'manual_bulk'];

    if (!in_array($taskId, $taskIds, true)) {
        $taskId = $fallbackTaskId;
        $photoType = $fallbackType;
        $method = 'manual_fallback_invalid_auto_task';
    }

    $dir = $photoBase . '/job_' . $jobId . '/task_' . $taskId;
    if (!is_dir($dir) && !mkdir($dir, 0770, true) && !is_dir($dir)) {
        continue;
    }

    $safeOriginal = preg_replace('/[^A-Za-z0-9._-]+/', '_', basename((string)$originalName)) ?: 'photo.' . $allowed[$mime];
    $stored = $photoType . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(5)) . '.' . wt_task_photo_stored_extension($mime);
    $dest = $dir . '/' . $stored;

    try {
        $storedInfo = wt_store_uploaded_task_photo_file($tmp, $dest, $mime, $size);
    } catch (Throwable $e) {
        $lastError = $e->getMessage();
        continue;
    }

    $relative = 'job_' . $jobId . '/task_' . $taskId . '/' . $stored;
    $takenSql = $takenAt ? $takenAt->format('Y-m-d H:i:s') : null;
    $photoNote = trim($note . ($takenAt ? "\nPhoto timestamp: " . $takenAt->format('D j M Y, g:i a') : ''));

    $insert->execute([
        $jobId,
        $taskId,
        $photoType,
        'mike',
        $safeOriginal,
        $stored,
        $relative,
        $storedInfo['mime_type'],
        $storedInfo['file_size'],
        $storedInfo['sha256'],
        $photoNote !== '' ? $photoNote : null,
        0,
        $takenSql,
        $method,
    ]);

    wt_after_task_photo_saved($pdo, (int)$pdo->lastInsertId(), $dest, (string)$storedInfo['mime_type'], $photoType, $relative, $storedInfo);
    $uploaded++;
    str_starts_with($method, 'auto_') ? $autoCount++ : $fallbackCount++;
}

header(
    'Location: ../../admin/work/task_photos.php?id=' .
    $jobId .
    '&bulk_uploaded=' .
    $uploaded .
    ($uploaded < 1 && isset($lastError) ? '&photo_error=' . urlencode($lastError) : '') .
    '&bulk_auto=' .
    $autoCount .
    '&bulk_fallback=' .
    $fallbackCount
);
exit;
