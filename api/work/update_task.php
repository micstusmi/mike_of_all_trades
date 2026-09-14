<?php
declare(strict_types=1);

require_once __DIR__ . '/_admin_auth.php';
require_once __DIR__ . '/../../includes/work_tracker.php';

$jobId = (int)($_POST['job_id'] ?? 0);
$taskId = (int)($_POST['task_id'] ?? 0);

wt_job($pdo, $jobId);

$q = $pdo->prepare("
    SELECT *
    FROM work_tasks
    WHERE id=? AND job_id=?
    LIMIT 1
");
$q->execute([$taskId, $jobId]);

$existingTask = $q->fetch(PDO::FETCH_ASSOC);

if (!$existingTask) {
    die('Task not found.');
}

$title = trim((string)($_POST['title'] ?? ''));

if ($title === '') {
    die('Task title is required.');
}

$statuses = [
    'not_started',
    'in_progress',
    'blocked',
    'completed',
    'cancelled',
];

$status = (string)(
    $_POST['status']
    ?? 'not_started'
);

if (!in_array($status, $statuses, true)) {
    $status = 'not_started';
}

$origins = [
    'original',
    'customer_requested',
    'mike_added',
    'ai_suggested',
    'unforeseen',
];

$origin = (string)(
    $_POST['task_origin']
    ?? 'mike_added'
);

if (!in_array($origin, $origins, true)) {
    $origin = 'mike_added';
}

$num = static function (string $key): ?float {
    $value = trim(
        (string)($_POST[$key] ?? '')
    );

    return $value === ''
        ? null
        : max(0, (float)$value);
};

$completedSql =
    $status === 'completed'
        ? 'COALESCE(completed_at,NOW())'
        : 'NULL';

$sql = "
    UPDATE work_tasks
    SET
        title=?,
        description=?,
        customer_summary=?,
        detailed_procedure=?,
        time_drivers=?,
        waiting_curing_notes=?,
        suggested_materials=?,
        status=?,
        task_origin=?,
        ai_estimate_low=?,
        ai_estimate_high=?,
        ai_reasoning=?,
        mike_estimate_low=?,
        mike_estimate_high=?,
        mike_reasoning=?,
        actual_adjusted_hours=?,
        actual_reasoning=?,
        customer_visible=?,
        completed_at={$completedSql}
    WHERE id=? AND job_id=?
";

$q = $pdo->prepare($sql);

$q->execute([
    $title,
    trim((string)($_POST['description'] ?? '')) ?: null,
    trim((string)($_POST['customer_summary'] ?? '')) ?: null,
    trim((string)($_POST['detailed_procedure'] ?? '')) ?: null,
    trim((string)($_POST['time_drivers'] ?? '')) ?: null,
    trim((string)($_POST['waiting_curing_notes'] ?? '')) ?: null,
    trim((string)($_POST['suggested_materials'] ?? '')) ?: null,
    $status,
    $origin,
    $num('ai_estimate_low'),
    $num('ai_estimate_high'),
    trim((string)($_POST['ai_reasoning'] ?? '')) ?: null,
    $num('mike_estimate_low'),
    $num('mike_estimate_high'),
    trim((string)($_POST['mike_reasoning'] ?? '')) ?: null,
    $num('actual_adjusted_hours'),
    trim((string)($_POST['actual_reasoning'] ?? '')) ?: null,
    isset($_POST['customer_visible']) ? 1 : 0,
    $taskId,
    $jobId,
]);

/* Optional progress/stage photos saved with this exact task update. */
$photoCount = 0;
$files = $_FILES['progress_photos'] ?? null;
if ($files && isset($files['name'])) {
    $names = is_array($files['name']) ? $files['name'] : [$files['name']];
    $tmps = is_array($files['tmp_name']) ? $files['tmp_name'] : [$files['tmp_name']];
    $errors = is_array($files['error']) ? $files['error'] : [$files['error']];
    $sizes = is_array($files['size']) ? $files['size'] : [$files['size']];
    if (count($names) > 8) die('Please upload no more than 8 photos at once.');
    $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    $base = wt_env('WORKTRACKER_PRIVATE_UPLOAD_DIR', dirname(__DIR__, 2).'/storage/private/job_intake');
    $dir = dirname(rtrim($base, '/')).'/task_photos/job_'.$jobId.'/task_'.$taskId;
    if (!is_dir($dir) && !mkdir($dir, 0770, true) && !is_dir($dir)) die('Private task photo folder could not be created.');
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $photoType = $status === 'completed' ? 'after' : 'progress';
    $ins = $pdo->prepare("INSERT INTO work_task_photos(job_id,task_id,photo_type,uploader_type,original_name,stored_name,relative_path,mime_type,file_size,sha256,note,keep_permanent,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,NOW())");
    $note = mb_substr(trim((string)($_POST['progress_photo_note'] ?? '')), 0, 500);
    foreach ($names as $i => $original) {
        if (($errors[$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
        if (($errors[$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) die('One of the selected photos could not be uploaded.');
        $size = (int)($sizes[$i] ?? 0);
        if ($size <= 0 || $size > 12*1024*1024) die('Each photo must be no larger than 12 MB.');
        $mime = $finfo->file($tmps[$i]);
        if (!isset($allowed[$mime])) die('Task photos must be JPG, PNG or WEBP.');
        $stored = $photoType.'_'.date('Ymd_His').'_'.bin2hex(random_bytes(5)).'.'.$allowed[$mime];
        $dest = $dir.'/'.$stored;
        if (!move_uploaded_file($tmps[$i], $dest)) die('A selected photo could not be saved.');
        $relative = 'job_'.$jobId.'/task_'.$taskId.'/'.$stored;
        $ins->execute([$jobId,$taskId,$photoType,'mike',(string)$original,$stored,$relative,$mime,$size,hash_file('sha256',$dest),$note,0]);
        $photoCount++;
    }
}

/*
 * The task has now been safely saved.
 *
 * SMS is deliberately handled afterwards so a message can never
 * describe a task update that failed to save.
 */
$sendSms =
    (string)($_POST['save_action'] ?? '')
    === 'save_sms';

$statusLabels = [
    'not_started' => 'not started',
    'in_progress' => 'in progress',
    'blocked' => 'blocked',
    'completed' => 'completed',
    'cancelled' => 'cancelled',
];

$smsMessage = trim(
    (string)($_POST['customer_sms_message'] ?? '')
);

if ($smsMessage === '') {
    $smsMessage =
        'Mike of All Trades update: "' .
        $title .
        '" is now ' .
        ($statusLabels[$status] ?? $status) .
        '. Your job record has been updated.';
}

$sms = wt_optional_customer_sms(
    $pdo,
    $jobId,
    $sendSms,
    'task_update',
    $smsMessage
);

wt_store_sms_flash(
    $sms,
    'Task updated'.($photoCount ? ' with '.$photoCount.' photo'.($photoCount === 1 ? '' : 's') : '')
);

header(
    'Location: ../../admin/work/manage_job.php?id=' .
    $jobId .
    '&task_saved=1#task-' .
    $taskId
);

exit;
