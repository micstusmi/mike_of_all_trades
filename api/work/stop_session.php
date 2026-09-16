<?php
declare(strict_types=1);

require_once __DIR__ . '/_admin_auth.php';
require_once __DIR__ . '/../../includes/work_tracker.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('POST required.');
}

$jobId = (int)($_POST['job_id'] ?? 0);
$sessionId = (int)($_POST['session_id'] ?? 0);

if ($jobId <= 0 || $sessionId <= 0) {
    http_response_code(400);
    exit('Invalid session.');
}

$job = wt_job($pdo, $jobId);

$allowedActions = [
    'change',
    'finish',
    'finish_task_start_next',
];

$action = (string)($_POST['session_action'] ?? 'finish');
if (!in_array($action, $allowedActions, true)) {
    $action = 'finish';
}

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

$locationLabels = [
    'onsite' => 'On site',
    'bunnings' => 'Bunnings',
    'supplier' => 'Another supplier / store',
    'travel_job' => 'Travelling for this job',
    'workshop_home' => 'Workshop / home preparation',
    'offsite_planning' => 'Off-site planning / admin for this job',
    'other' => 'Other',
];

$categoryLabels = [
    'onsite' => 'On-site work',
    'measurement' => 'Measurement / investigation',
    'planning' => 'Planning',
    'procurement' => 'Sourcing / procurement',
    'travel' => 'Job-specific travel',
    'loading_setup' => 'Loading / setup / pack-up',
    'demolition' => 'Demolition / removal',
    'repair' => 'Repair / preparation',
    'unforeseen' => 'Unforeseen / remedial',
    'other' => 'Other',
];

function wt_redirect_manage(int $jobId, string $query, string $anchor = 'live-timer'): never {
    header(
        'Location: ../../admin/work/manage_job.php?id=' .
        $jobId .
        $query .
        '#' .
        $anchor
    );
    exit;
}

function wt_same_worker_open_clause(?int $workerId): string {
    return $workerId === null
        ? 'worker_id IS NULL'
        : 'worker_id = ?';
}

function wt_save_finish_photos(PDO $pdo, int $jobId, int $taskId, string $photoType, string $note): int {
    if ($taskId <= 0) {
        return 0;
    }

    $files = $_FILES['finish_photos'] ?? null;
    if (!$files || !isset($files['name'])) {
        return 0;
    }

    $names = is_array($files['name']) ? $files['name'] : [$files['name']];
    $tmps = is_array($files['tmp_name']) ? $files['tmp_name'] : [$files['tmp_name']];
    $errors = is_array($files['error']) ? $files['error'] : [$files['error']];
    $sizes = is_array($files['size']) ? $files['size'] : [$files['size']];

    if (count($names) > 8) {
        exit('Please upload no more than 8 photos at once.');
    }

    $allowed = wt_allowed_task_photo_types();

    $base = wt_env(
        'WORKTRACKER_PRIVATE_UPLOAD_DIR',
        dirname(__DIR__, 2) . '/storage/private/job_intake'
    );

    $dir = dirname(rtrim((string)$base, '/')) . '/task_photos/job_' . $jobId . '/task_' . $taskId;

    if (!is_dir($dir) && !mkdir($dir, 0770, true) && !is_dir($dir)) {
        exit('Private task photo folder could not be created.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $ins = $pdo->prepare("
        INSERT INTO work_task_photos
        (job_id,task_id,photo_type,uploader_type,original_name,stored_name,relative_path,mime_type,file_size,sha256,note,keep_permanent,created_at)
        VALUES(?,?,?,?,?,?,?,?,?,?,?,?,NOW())
    ");

    $count = 0;
    $note = mb_substr($note, 0, 500);

    foreach ($names as $i => $original) {
        if (($errors[$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        if (($errors[$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            exit('One selected photo could not be uploaded.');
        }

        $size = (int)($sizes[$i] ?? 0);
        if ($size <= 0 || $size > 12 * 1024 * 1024) {
            exit('Each photo must be no larger than 12 MB.');
        }

        $tmp = (string)($tmps[$i] ?? '');
        $mime = wt_normalise_uploaded_photo_mime((string)$finfo->file($tmp), (string)$original);
        if (!isset($allowed[$mime])) {
            exit('Photos must be JPG, PNG, WEBP, HEIC or HEIF.');
        }

        $storedExt = wt_task_photo_stored_extension($mime);
        $stored = $photoType . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(5)) . '.' . $storedExt;
        $dest = $dir . '/' . $stored;

        try {
            $storedInfo = wt_store_uploaded_task_photo_file($tmp, $dest, $mime, $size);
        } catch (Throwable $e) {
            exit($e->getMessage());
        }

        $relative = 'job_' . $jobId . '/task_' . $taskId . '/' . $stored;

        $ins->execute([
            $jobId,
            $taskId,
            $photoType,
            'mike',
            (string)$original,
            $stored,
            $relative,
            $storedInfo['mime_type'],
            $storedInfo['file_size'],
            $storedInfo['sha256'],
            $note,
            0,
        ]);

        wt_after_task_photo_saved($pdo, (int)$pdo->lastInsertId(), $dest, (string)$storedInfo['mime_type'], $photoType, $relative, $storedInfo);

        $count++;
    }

    return $count;
}

$stopNote = trim((string)($_POST['stop_note'] ?? ''));
$nextTaskId = (int)($_POST['next_task_id'] ?? 0);
$nextNotes = trim((string)($_POST['next_notes'] ?? ''));
$nextLocation = (string)($_POST['next_start_location'] ?? 'onsite');
$nextCategory = (string)($_POST['next_category'] ?? 'onsite');

if (!in_array($nextLocation, $allowedLocations, true)) {
    $nextLocation = 'other';
}

if (!in_array($nextCategory, $allowedCategories, true)) {
    $nextCategory = 'other';
}

if ($action === 'change') {
    $nextLocation = (string)($_POST['start_location'] ?? 'onsite');
    $nextCategory = (string)($_POST['category'] ?? 'onsite');
    $nextNotes = trim((string)($_POST['notes'] ?? ''));

    if (!in_array($nextLocation, $allowedLocations, true)) {
        $nextLocation = 'other';
    }

    if (!in_array($nextCategory, $allowedCategories, true)) {
        $nextCategory = 'other';
    }

    if ($nextNotes === '') {
        exit('Please describe the new activity.');
    }
}

if ($action === 'finish_task_start_next' && ($nextTaskId <= 0 || $nextNotes === '')) {
    exit('Choose the next task and describe the next activity.');
}

$newSessionId = null;
$currentTaskId = 0;
$currentTaskTitle = '';
$nextTaskTitle = '';
$workerName = 'Mike';
$photoType = 'progress';

$pdo->beginTransaction();

try {
    $q = $pdo->prepare("
        SELECT s.*, w.worker_name
        FROM work_sessions s
        LEFT JOIN work_workers w ON w.id = s.worker_id
        WHERE s.id = ?
          AND s.job_id = ?
          AND s.ended_at IS NULL
        LIMIT 1
        FOR UPDATE
    ");
    $q->execute([$sessionId, $jobId]);
    $session = $q->fetch(PDO::FETCH_ASSOC);

    if (!$session) {
        $pdo->rollBack();
        wt_redirect_manage($jobId, '&stopped=1');
    }

    $workerId = $session['worker_id'] === null
        ? null
        : (int)$session['worker_id'];
    $workerName = (string)($session['worker_name'] ?: 'Mike');
    $currentTaskId = (int)($session['task_id'] ?? 0);

    if ($currentTaskId > 0) {
        $taskStmt = $pdo->prepare("SELECT title FROM work_tasks WHERE id=? AND job_id=? LIMIT 1");
        $taskStmt->execute([$currentTaskId, $jobId]);
        $currentTaskTitle = (string)$taskStmt->fetchColumn();
    }

    if ($action === 'finish_task_start_next') {
        $taskStmt = $pdo->prepare("
            SELECT title
            FROM work_tasks
            WHERE id=?
              AND job_id=?
              AND status NOT IN ('completed','cancelled')
            LIMIT 1
            FOR UPDATE
        ");
        $taskStmt->execute([$nextTaskId, $jobId]);
        $nextTaskTitle = (string)$taskStmt->fetchColumn();

        if ($nextTaskTitle === '' || $nextTaskId === $currentTaskId) {
            throw new RuntimeException('Choose a valid next task.');
        }

        if ($currentTaskId > 0) {
            $photoType = 'after';
        }
    }

    $closeBreak = $pdo->prepare("
        UPDATE work_session_breaks
        SET ended_at = UTC_TIMESTAMP()
        WHERE session_id = ?
          AND ended_at IS NULL
    ");
    $closeBreak->execute([$sessionId]);

    $stopReason = $action === 'change'
        ? 'changed_activity'
        : ($action === 'finish_task_start_next' ? 'task_finished_next_started' : 'finished');

    $stop = $pdo->prepare("
        UPDATE work_sessions
        SET ended_at = UTC_TIMESTAMP(),
            stop_reason = ?,
            stop_note = ?
        WHERE id = ?
          AND job_id = ?
          AND ended_at IS NULL
    ");
    $stop->execute([
        $stopReason,
        $stopNote !== '' ? $stopNote : null,
        $sessionId,
        $jobId,
    ]);

    if ($action === 'finish_task_start_next' && $currentTaskId > 0) {
        $pdo->prepare("
            UPDATE work_tasks
            SET status='completed',
                completed_at=COALESCE(completed_at,NOW())
            WHERE id=? AND job_id=?
        ")->execute([$currentTaskId, $jobId]);
    }

    if ($action === 'change' || $action === 'finish_task_start_next') {
        $openClause = wt_same_worker_open_clause($workerId);
        $dupSql = "
            SELECT id
            FROM work_sessions
            WHERE job_id = ?
              AND {$openClause}
              AND ended_at IS NULL
            LIMIT 1
            FOR UPDATE
        ";

        $dup = $pdo->prepare($dupSql);
        $dupParams = [$jobId];
        if ($workerId !== null) {
            $dupParams[] = $workerId;
        }
        $dup->execute($dupParams);

        if ($dup->fetchColumn()) {
            throw new RuntimeException('That worker still has a running session.');
        }

        $insertTaskId = $action === 'finish_task_start_next'
            ? $nextTaskId
            : ($currentTaskId ?: null);

        $insert = $pdo->prepare("
            INSERT INTO work_sessions
            (job_id,session_source,worker_id,task_id,started_at,category,start_location,location_detail,billable,notes)
            VALUES(?,'live',?,?,UTC_TIMESTAMP(),?,?,?,?,?)
        ");

        $insert->execute([
            $jobId,
            $workerId,
            $insertTaskId,
            $nextCategory,
            $nextLocation,
            null,
            1,
            $nextNotes,
        ]);

        $newSessionId = (int)$pdo->lastInsertId();

        if ($insertTaskId) {
            $pdo->prepare("
                UPDATE work_tasks
                SET status=IF(status IN ('not_started','blocked'),'in_progress',status)
                WHERE id=? AND job_id=?
            ")->execute([$insertTaskId, $jobId]);
        }
    }

    $running = $pdo->prepare("
        SELECT COUNT(*)
        FROM work_sessions
        WHERE job_id = ?
          AND ended_at IS NULL
    ");
    $running->execute([$jobId]);

    $jobStatus = (int)$running->fetchColumn() > 0
        ? 'active'
        : 'paused';

    $pdo->prepare("UPDATE work_jobs SET status=? WHERE id=?")->execute([$jobStatus, $jobId]);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(400);
    exit($e->getMessage());
}

$photoNote = trim((string)($_POST['finish_photo_note'] ?? ''));
if ($photoNote === '') {
    $photoNote = $stopNote;
}

$photoCount = wt_save_finish_photos(
    $pdo,
    $jobId,
    $currentTaskId,
    $photoType,
    $photoNote
);

$sendSms = (string)($_POST['save_action'] ?? '') === 'save_sms';
$smsMessage = trim((string)($_POST['customer_sms_message'] ?? ''));

if ($smsMessage === '') {
    if ($action === 'finish_task_start_next') {
        if ($currentTaskTitle !== '') {
            $smsMessage =
                'Mike of All Trades update: "' .
                $currentTaskTitle .
                '" is finished and "' .
                $nextTaskTitle .
                '" has started. Your job record has been updated.';
        } else {
            $smsMessage =
                'Mike of All Trades update: current job activity has finished and "' .
                $nextTaskTitle .
                '" has started. Your job record has been updated.';
        }
    } elseif ($action === 'change') {
        $where = $locationLabels[$nextLocation] ?? 'Job activity';
        $smsMessage =
            'Mike of All Trades update: ' .
            $workerName .
            ' changed activity to ' .
            ($categoryLabels[$nextCategory] ?? $nextCategory) .
            ' at ' .
            $where .
            '. Your job record has been updated.';
    } else {
        $smsMessage =
            'Mike of All Trades update: current job activity has finished. Your job record has been updated.';
    }
}

$sms = wt_optional_customer_sms(
    $pdo,
    $jobId,
    $sendSms,
    $action === 'finish_task_start_next' ? 'task_finished_next_started' : 'session_stopped',
    $smsMessage
);

wt_store_sms_flash(
    $sms,
    'Activity finish'.($photoCount ? ' with '.$photoCount.' photo'.($photoCount === 1 ? '' : 's') : '')
);

$query = $newSessionId !== null
    ? '&started=1'
    : '&stopped=1';

wt_redirect_manage($jobId, $query);
