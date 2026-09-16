<?php
declare(strict_types=1);

/*
 * Ends the current work activity because an applied product must
 * dry/cure/set.
 *
 * Important:
 * - this is NOT a personal pause;
 * - this is NOT job completion;
 * - the timer stops;
 * - the job remains open for a later attendance;
 * - elapsed drying/curing time is not charged as labour.
 */

require_once __DIR__ . '/_admin_auth.php';
require_once __DIR__ . '/../../includes/work_tracker.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('POST required.');
}

$jobId = (int)($_POST['job_id'] ?? 0);

if ($jobId <= 0) {
    http_response_code(400);
    exit('Invalid job.');
}

$job = wt_job($pdo, $jobId);

$material = trim((string)($_POST['cure_material'] ?? ''));
$note = trim((string)($_POST['cure_note'] ?? ''));
$expectedReturn = trim(
    (string)($_POST['cure_expected_return'] ?? '')
);
$startNextTask = (string)($_POST['start_next_task'] ?? '0') === '1';
$nextTaskId = (int)($_POST['cure_next_task_id'] ?? 0);
$nextLocation = (string)($_POST['cure_next_start_location'] ?? 'onsite');
$nextCategory = (string)($_POST['cure_next_category'] ?? 'onsite');
$nextNotes = trim((string)($_POST['cure_next_notes'] ?? ''));

$allowedLocations = [
    'onsite',
    'bunnings',
    'supplier',
    'travel_job',
    'workshop_home',
    'offsite_planning',
    'other',
];

$allowedCategories = [
    'onsite',
    'measurement',
    'planning',
    'procurement',
    'travel',
    'loading_setup',
    'demolition',
    'repair',
    'unforeseen',
    'other',
];

if (!in_array($nextLocation, $allowedLocations, true)) {
    $nextLocation = 'other';
}

if (!in_array($nextCategory, $allowedCategories, true)) {
    $nextCategory = 'other';
}

if ($material === '') {
    http_response_code(400);
    exit('Please select what was applied.');
}

if ($note === '') {
    http_response_code(400);
    exit('Please describe what is drying / curing and what happens next.');
}

if ($startNextTask && ($nextTaskId <= 0 || $nextNotes === '')) {
    http_response_code(400);
    exit('Choose the next task and describe the next activity.');
}

/*
 * Prefer Mike's currently open session (worker_id NULL in this Work
 * Tracker). If another structure is encountered, fall back to the most
 * recent open session on the job.
 */
$q = $pdo->prepare("
    SELECT *
    FROM work_sessions
    WHERE job_id = ?
      AND ended_at IS NULL
    ORDER BY
        CASE WHEN worker_id IS NULL THEN 0 ELSE 1 END,
        id DESC
    LIMIT 1
");
$q->execute([$jobId]);

$session = $q->fetch(PDO::FETCH_ASSOC);

if (!$session) {
    header(
        'Location: ../../admin/work/manage_job.php?id=' .
        $jobId .
        '&cure_no_session=1#live-timer'
    );
    exit;
}

$sessionId = (int)$session['id'];
$taskId = (int)($session['task_id'] ?? 0);
$workerId = $session['worker_id'] === null
    ? null
    : (int)$session['worker_id'];

/*
 * If the session happened to have an open personal-break record,
 * close it cleanly before closing the activity itself.
 */
try {
    $closeBreak = $pdo->prepare("
        UPDATE work_session_breaks
        SET ended_at = NOW()
        WHERE session_id = ?
          AND ended_at IS NULL
    ");
    $closeBreak->execute([$sessionId]);
} catch (Throwable $e) {
    /*
     * Older installations without this table should still be able to
     * stop the session.
     */
}

$stopNote =
    $material .
    ': ' .
    $note;

$stmt = $pdo->prepare("
    UPDATE work_sessions
    SET
        ended_at = NOW(),
        stop_reason = 'waiting_cure',
        stop_note = ?,
        expected_return = ?
    WHERE id = ?
      AND job_id = ?
      AND ended_at IS NULL
");

$stmt->execute([
    $stopNote,
    $expectedReturn !== '' ? $expectedReturn : null,
    $sessionId,
    $jobId,
]);

if ($taskId > 0) {
    $waitingText = $stopNote . ($expectedReturn !== '' ? "\nExpected return / next stage: ".$expectedReturn : '');
    $pdo->prepare("
        UPDATE work_tasks
        SET status='blocked',
            waiting_curing_notes=CASE
                WHEN waiting_curing_notes IS NULL OR waiting_curing_notes='' THEN ?
                ELSE CONCAT(waiting_curing_notes, '\n', ?)
            END
        WHERE id=? AND job_id=? AND status NOT IN ('completed','cancelled')
    ")->execute([$waitingText,$waitingText,$taskId,$jobId]);
}

if ($startNextTask) {
    $taskStmt = $pdo->prepare("
        SELECT title
        FROM work_tasks
        WHERE id=?
          AND job_id=?
          AND status NOT IN ('completed','cancelled')
        LIMIT 1
    ");
    $taskStmt->execute([$nextTaskId, $jobId]);
    $nextTaskTitle = (string)$taskStmt->fetchColumn();

    if ($nextTaskTitle === '' || ($taskId > 0 && $nextTaskId === $taskId)) {
        http_response_code(400);
        exit('Choose a valid next task.');
    }

    if ($workerId === null) {
        $dup = $pdo->prepare("
            SELECT id
            FROM work_sessions
            WHERE job_id=?
              AND worker_id IS NULL
              AND ended_at IS NULL
            LIMIT 1
        ");
        $dup->execute([$jobId]);
    } else {
        $dup = $pdo->prepare("
            SELECT id
            FROM work_sessions
            WHERE job_id=?
              AND worker_id=?
              AND ended_at IS NULL
            LIMIT 1
        ");
        $dup->execute([$jobId, $workerId]);
    }

    if ($dup->fetchColumn()) {
        http_response_code(409);
        exit('That worker still has a running session.');
    }

    $insert = $pdo->prepare("
        INSERT INTO work_sessions
        (job_id,session_source,worker_id,task_id,started_at,category,start_location,location_detail,billable,notes)
        VALUES(?,'live',?,?,UTC_TIMESTAMP(),?,?,?,?,?)
    ");

    $insert->execute([
        $jobId,
        $workerId,
        $nextTaskId,
        $nextCategory,
        $nextLocation,
        null,
        1,
        $nextNotes,
    ]);

    $pdo->prepare("
        UPDATE work_tasks
        SET status=IF(status IN ('not_started','blocked'),'in_progress',status)
        WHERE id=? AND job_id=?
    ")->execute([$nextTaskId, $jobId]);
}

/* Save optional stage photos against the activity's current task. */
$files = $_FILES['cure_photos'] ?? null;
if ($files && $taskId > 0 && isset($files['name'])) {
    $names = is_array($files['name']) ? $files['name'] : [$files['name']];
    $tmps = is_array($files['tmp_name']) ? $files['tmp_name'] : [$files['tmp_name']];
    $errors = is_array($files['error']) ? $files['error'] : [$files['error']];
    $sizes = is_array($files['size']) ? $files['size'] : [$files['size']];
    if (count($names) > 8) die('Please upload no more than 8 photos at once.');
    $allowed = wt_allowed_task_photo_types();
    $base = wt_env('WORKTRACKER_PRIVATE_UPLOAD_DIR', dirname(__DIR__, 2).'/storage/private/job_intake');
    $dir = dirname(rtrim($base, '/')).'/task_photos/job_'.$jobId.'/task_'.$taskId;
    if (!is_dir($dir) && !mkdir($dir, 0770, true) && !is_dir($dir)) die('Private task photo folder could not be created.');
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $ins = $pdo->prepare("INSERT INTO work_task_photos(job_id,task_id,photo_type,uploader_type,original_name,stored_name,relative_path,mime_type,file_size,sha256,note,keep_permanent,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,NOW())");
    foreach ($names as $i => $original) {
        if (($errors[$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
        if (($errors[$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) die('One selected photo could not be uploaded.');
        $size = (int)($sizes[$i] ?? 0);
        if ($size <= 0 || $size > 12*1024*1024) die('Each photo must be no larger than 12 MB.');
        $mime = wt_normalise_uploaded_photo_mime((string)$finfo->file($tmps[$i]), (string)$original);
        if (!isset($allowed[$mime])) die('Photos must be JPG, PNG, WEBP, HEIC or HEIF.');
        $storedExt = wt_task_photo_stored_extension($mime);
        $stored = 'progress_'.date('Ymd_His').'_'.bin2hex(random_bytes(5)).'.'.$storedExt;
        $dest = $dir.'/'.$stored;
        try {
            $storedInfo = wt_store_uploaded_task_photo_file((string)$tmps[$i], $dest, $mime, $size);
        } catch (Throwable $e) {
            die($e->getMessage());
        }
        $relative = 'job_'.$jobId.'/task_'.$taskId.'/'.$stored;
        $ins->execute([$jobId,$taskId,'progress','mike',(string)$original,$stored,$relative,$storedInfo['mime_type'],$storedInfo['file_size'],$storedInfo['sha256'],$stopNote,0]);
        wt_after_task_photo_saved($pdo,(int)$pdo->lastInsertId(),$dest,(string)$storedInfo['mime_type'],'progress',$relative,$storedInfo);
    }
}

/*
 * Leave the job OPEN.
 *
 * If another worker still has an active session, the job remains active.
 * Otherwise it becomes paused rather than completed.
 */
$running = $pdo->prepare("
    SELECT COUNT(*)
    FROM work_sessions
    WHERE job_id = ?
      AND ended_at IS NULL
");
$running->execute([$jobId]);

$jobStatus =
    ((int)$running->fetchColumn() > 0)
        ? 'active'
        : 'paused';

$pdo->prepare("
    UPDATE work_jobs
    SET status = ?
    WHERE id = ?
")->execute([
    $jobStatus,
    $jobId,
]);

/*
 * Customer communication:
 * drying/curing is a genuine job-status explanation, not an unrelated
 * personal interruption.
 */
$sendSms = (string)($_POST['notify_customer'] ?? '0') === '1';

if (
    $sendSms &&
    !empty($job['customer_phone'])
) {
    $msg =
        "Mike of All Trades — Job update\n" .
        "Work has stopped for now while an applied material dries/cures.\n" .
        "Applied: " . $material . ".\n" .
        "Details: " . $note . ".\n" .
        "Drying/curing time is not being recorded as labour.";

    if ($expectedReturn !== '') {
        $msg .=
            "\nExpected return / next attendance: " .
            $expectedReturn .
            ".";
    }

    $msg .=
        "\nThe job remains open for the next stage." .
        "\nLive job record: " .
        wt_public_url($job);

    try {
        wt_send_sms(
            $pdo,
            $jobId,
            $job['customer_phone'],
            $msg,
            'waiting_cure'
        );
    } catch (Throwable $e) {
        /*
         * Never lose the timer/session update merely because SMS failed.
         */
        error_log(
            'Waiting/cure SMS failed for work job ' .
            $jobId .
            ': ' .
            $e->getMessage()
        );
    }
}

header(
    'Location: ../../admin/work/manage_job.php?id=' .
    $jobId .
    '&waiting_cure=1' .
    ($startNextTask ? '&started=1' : '') .
    '#live-timer'
);

exit;
