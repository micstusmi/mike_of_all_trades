<?php
declare(strict_types=1);

require_once __DIR__ . '/_admin_auth.php';
require_once __DIR__ . '/../../includes/work_tracker.php';

function qa_back(int $jobId, string $flag): never
{
    if (!empty($_SESSION['wt_quick_sms_note']) && isset($_SESSION['wt_quick_action_flash'])) {
        $_SESSION['wt_quick_action_flash']['message'] .= ' ' . (string)$_SESSION['wt_quick_sms_note'];
        unset($_SESSION['wt_quick_sms_note']);
    }
    header('Location: ../../admin/work/manage_job.php?id=' . $jobId . '&' . $flag . '=1#quick-actions');
    exit;
}

function qa_fail(int $jobId, string $message): never
{
    $_SESSION['wt_quick_action_flash'] = [
        'ok' => false,
        'message' => $message,
    ];
    qa_back($jobId, 'quick_action_failed');
}

function qa_sms(PDO $pdo, array $job, string $message, string $purpose, string $label, string $importance = 'routine'): void
{
    $mode = (string)($job['customer_update_mode'] ?? 'full_transparency');
    $send = $mode === 'full_transparency'
        || ($mode === 'important_only' && $importance === 'important');
    if (!$send) {
        $modeLabel = match ($mode) {
            'important_only' => 'Important-only SMS mode suppressed this routine update.',
            'daily_only' => 'Daily-summary mode suppressed this individual update.',
            'none' => 'Automatic customer SMS is turned off for this job.',
            default => 'No automatic customer SMS was required.',
        };
        $_SESSION['wt_quick_sms_note'] = $modeLabel;
        return;
    }

    $phone = trim((string)($job['customer_phone'] ?? ''));
    if ($phone === '') {
        $_SESSION['wt_quick_sms_note'] = 'No SMS was sent because the customer has no phone number recorded.';
        $_SESSION['work_sms_flash'] = [
            'ok' => false,
            'purpose' => $purpose,
            'time' => date('Y-m-d H:i:s'),
            'status' => 'Customer has no phone number recorded.',
            'message' => $message,
        ];
        return;
    }

    try {
        $result = wt_send_sms($pdo, (int)$job['id'], $phone, $message, $purpose);
        $_SESSION['wt_quick_sms_note'] = !empty($result['ok'])
            ? 'Customer SMS sent and recorded.'
            : 'The activity was saved, but the customer SMS failed.';
        $_SESSION['work_sms_flash'] = [
            'ok' => !empty($result['ok']),
            'purpose' => $purpose,
            'time' => date('Y-m-d H:i:s'),
            'status' => (string)($result['gateway_status'] ?? $result['message'] ?? ''),
            'message' => $message,
            'action' => $label,
        ];
    } catch (Throwable $e) {
        $_SESSION['wt_quick_sms_note'] = 'The activity was saved, but the customer SMS failed.';
        $_SESSION['work_sms_flash'] = [
            'ok' => false,
            'purpose' => $purpose,
            'time' => date('Y-m-d H:i:s'),
            'status' => $e->getMessage(),
            'message' => $message,
            'action' => $label,
        ];
    }
}

function qa_task_label(PDO $pdo, int $jobId, ?int $taskId): string
{
    if (!$taskId) {
        return 'the current job activity';
    }
    $stmt = $pdo->prepare("SELECT title FROM work_tasks WHERE id=? AND job_id=? LIMIT 1");
    $stmt->execute([$taskId, $jobId]);
    return trim((string)$stmt->fetchColumn()) ?: 'the current job activity';
}

function qa_running_action(array $running): string
{
    if (($running['travel_type'] ?? '') === 'to_customer') return 'travel_site';
    if (($running['category'] ?? '') === 'procurement') return 'supplier_out';
    $detail = (string)($running['location_detail'] ?? '') . ' ' . (string)($running['notes'] ?? '');
    if (stripos($detail, 'Returning from supplier') !== false) return 'supplier_return';
    if (stripos($detail, 'Leaving customer site') !== false) return 'leave_site';
    if (($running['category'] ?? '') === 'onsite') return 'work';
    return '';
}

$jobId = (int)($_POST['job_id'] ?? 0);
$action = (string)($_POST['quick_action'] ?? '');
$taskId = (int)($_POST['quick_task_id'] ?? 0);
$billableTravel = isset($_POST['quick_charge_travel']) ? 1 : 0;

if ($jobId <= 0) {
    http_response_code(400);
    exit('Invalid job.');
}

$job = wt_job($pdo, $jobId);

$taskTitle = '';
if ($taskId > 0) {
    $taskStmt = $pdo->prepare("SELECT title FROM work_tasks WHERE id=? AND job_id=? AND status<>'cancelled' LIMIT 1");
    $taskStmt->execute([$taskId, $jobId]);
    $taskTitle = (string)$taskStmt->fetchColumn();
    if ($taskTitle === '') {
        qa_fail($jobId, 'Choose a valid task.');
    }
}

$startMap = [
    'travel_site' => [
        'category' => 'travel',
        'start_location' => 'travel_job',
        'location_detail' => (string)($job['job_address'] ?? ''),
        'travel_type' => 'to_customer',
        'billable' => $billableTravel,
        'notes' => 'Travelling to customer premises.',
    ],
    'arrive_site' => [
        'category' => 'onsite',
        'start_location' => 'onsite',
        'location_detail' => (string)($job['job_address'] ?? ''),
        'travel_type' => null,
        'billable' => 1,
        'notes' => $taskTitle !== '' ? 'Arrived and starting: ' . $taskTitle : 'Arrived and starting on-site work.',
    ],
    'work' => [
        'category' => 'onsite',
        'start_location' => 'onsite',
        'location_detail' => (string)($job['job_address'] ?? ''),
        'travel_type' => null,
        'billable' => 1,
        'notes' => $taskTitle !== '' ? 'Working on: ' . $taskTitle : 'On-site work.',
    ],
    'leave_site' => [
        'category' => 'travel',
        'start_location' => 'travel_job',
        'location_detail' => 'Leaving customer site',
        'travel_type' => null,
        'billable' => $billableTravel,
        'notes' => 'Leaving customer site for this job.',
    ],
    'supplier_out' => [
        'category' => 'procurement',
        'start_location' => 'bunnings',
        'location_detail' => 'Travelling to Bunnings / supplier',
        'travel_type' => null,
        'billable' => $billableTravel,
        'notes' => 'Travelling to Bunnings / supplier for job materials.',
    ],
    'supplier_return' => [
        'category' => 'travel',
        'start_location' => 'travel_job',
        'location_detail' => 'Returning from supplier',
        'travel_type' => null,
        'billable' => $billableTravel,
        'notes' => 'Returning from supplier for this job.',
    ],
];

$pauseMap = [
    'coffee_break' => ['reason' => 'rest', 'note' => 'Coffee / short break'],
    'meal_break' => ['reason' => 'meal', 'note' => 'Meal break'],
];

if (!isset($startMap[$action]) && !isset($pauseMap[$action]) && $action !== 'finish_activity') {
    qa_fail($jobId, 'Unknown quick action.');
}

$pdo->beginTransaction();

try {
    $runningStmt = $pdo->prepare("
        SELECT *
        FROM work_sessions
        WHERE job_id=?
          AND worker_id IS NULL
          AND ended_at IS NULL
        ORDER BY id DESC
        LIMIT 1
        FOR UPDATE
    ");
    $runningStmt->execute([$jobId]);
    $running = $runningStmt->fetch(PDO::FETCH_ASSOC);

    if (isset($pauseMap[$action])) {
        if (!$running) {
            throw new RuntimeException('Start an activity before taking a break.');
        }

        $breakStmt = $pdo->prepare("SELECT * FROM work_session_breaks WHERE session_id=? AND ended_at IS NULL LIMIT 1 FOR UPDATE");
        $breakStmt->execute([(int)$running['id']]);
        $openBreak = $breakStmt->fetch(PDO::FETCH_ASSOC);
        $sameBreak = $openBreak && (string)$openBreak['reason'] === $pauseMap[$action]['reason'];
        $resumeTask = qa_task_label($pdo, $jobId, isset($running['task_id']) ? (int)$running['task_id'] : null);

        if ($openBreak) {
            $pdo->prepare("UPDATE work_session_breaks SET ended_at=UTC_TIMESTAMP() WHERE id=? AND ended_at IS NULL")
                ->execute([(int)$openBreak['id']]);
        }

        if ($sameBreak) {
            $pdo->prepare("UPDATE work_jobs SET status='active' WHERE id=?")->execute([$jobId]);
            $pdo->commit();
            $label = $action === 'meal_break' ? 'Meal break ended' : 'Coffee break ended';
            $message = 'Mike of All Trades update: Mike has finished his ' .
                ($action === 'meal_break' ? 'meal break' : 'coffee / short break') .
                ' and resumed work on ' . $resumeTask . '. The break was not counted as working time.';
            qa_sms($pdo, $job, $message, $action . '_ended', $label);
            $_SESSION['wt_quick_action_flash'] = ['ok' => true, 'message' => $label . '. Work resumed.'];
            qa_back($jobId, 'quick_action_saved');
        }

        $pdo->prepare("INSERT INTO work_session_breaks(session_id,started_at,reason,note) VALUES(?,UTC_TIMESTAMP(),?,?)")
            ->execute([(int)$running['id'], $pauseMap[$action]['reason'], $pauseMap[$action]['note']]);
        $pdo->prepare("UPDATE work_jobs SET status='paused' WHERE id=?")->execute([$jobId]);
        $pdo->commit();
        $breakName = $action === 'meal_break' ? 'meal break' : 'coffee / short break';
        $message = 'Mike of All Trades update: Mike has paused work for a ' . $breakName .
            '. This break is not being counted as working time.';
        qa_sms($pdo, $job, $message, $action . '_started', ucfirst($breakName) . ' started');
        $_SESSION['wt_quick_action_flash'] = ['ok' => true, 'message' => $pauseMap[$action]['note'] . ' started. Tap the same button again to end it and resume work.'];
        qa_back($jobId, 'quick_action_saved');
    }

    $runningAction = $running ? qa_running_action($running) : '';
    if ($running && $action !== 'arrive_site' && $runningAction === $action) {
        $pdo->prepare("UPDATE work_session_breaks SET ended_at=UTC_TIMESTAMP() WHERE session_id=? AND ended_at IS NULL")
            ->execute([(int)$running['id']]);
        $pdo->prepare("UPDATE work_sessions SET ended_at=UTC_TIMESTAMP(),stop_reason='finished',stop_note='Stopped by tapping the active quick-action button.' WHERE id=? AND job_id=? AND ended_at IS NULL")
            ->execute([(int)$running['id'], $jobId]);
        $pdo->prepare("UPDATE work_jobs SET status='paused' WHERE id=?")->execute([$jobId]);
        $pdo->commit();
        $message = 'Mike of All Trades update: Mike has stopped ' . strtolower($startMap[$action]['notes']) .
            ' The recorded job time has been updated.';
        $stopImportance = in_array($action, ['travel_site','leave_site'], true) ? 'important' : 'routine';
        qa_sms($pdo, $job, $message, $action . '_ended', 'Activity stopped', $stopImportance);
        $_SESSION['wt_quick_action_flash'] = ['ok' => true, 'message' => 'The active timer was stopped.'];
        qa_back($jobId, 'quick_action_saved');
    }

    if ($running) {
        $pdo->prepare("UPDATE work_session_breaks SET ended_at=UTC_TIMESTAMP() WHERE session_id=? AND ended_at IS NULL")
            ->execute([(int)$running['id']]);

        $reason = $action === 'finish_activity' ? 'finished' : 'changed_activity';
        $note = $action === 'finish_activity'
            ? 'Finished from quick action dashboard.'
            : 'Changed from quick action dashboard.';

        $pdo->prepare("
            UPDATE work_sessions
            SET ended_at=UTC_TIMESTAMP(),
                stop_reason=?,
                stop_note=?
            WHERE id=? AND job_id=? AND ended_at IS NULL
        ")->execute([$reason, $note, (int)$running['id'], $jobId]);
    }

    if ($action === 'finish_activity') {
        if (!$running) {
            throw new RuntimeException('There is no current activity to finish.');
        }
        $pdo->prepare("UPDATE work_jobs SET status='paused' WHERE id=?")->execute([$jobId]);
        $pdo->commit();
        qa_sms(
            $pdo,
            $job,
            'Mike of All Trades update: Mike has finished the current activity. The recorded work and travel times have been updated.',
            'quick_activity_finished',
            'Activity finished',
            'important'
        );
        $_SESSION['wt_quick_action_flash'] = ['ok' => true, 'message' => 'Current activity finished.'];
        qa_back($jobId, 'quick_action_saved');
    }

    $next = $startMap[$action];
    $insert = $pdo->prepare("
        INSERT INTO work_sessions
        (job_id,session_source,worker_id,task_id,started_at,category,start_location,location_detail,travel_type,billable,notes)
        VALUES(?,'live',NULL,?,UTC_TIMESTAMP(),?,?,?,?,?,?)
    ");
    $insert->execute([
        $jobId,
        $taskId > 0 ? $taskId : null,
        $next['category'],
        $next['start_location'],
        $next['location_detail'] !== '' ? $next['location_detail'] : null,
        $next['travel_type'],
        (int)$next['billable'],
        $next['notes'],
    ]);

    if ($taskId > 0) {
        $pdo->prepare("UPDATE work_tasks SET status=IF(status IN ('not_started','blocked'),'in_progress',status) WHERE id=? AND job_id=?")
            ->execute([$taskId, $jobId]);
    }

    $pdo->prepare("UPDATE work_jobs SET status='active' WHERE id=?")->execute([$jobId]);
    $pdo->commit();

    $nextTaskLabel = $taskTitle !== '' ? $taskTitle : 'the current job activity';
    $smsByAction = [
        'travel_site' => 'Mike of All Trades update: Mike is now travelling to your property. Travel timing has started.',
        'arrive_site' => 'Mike of All Trades update: Mike has arrived and started work on ' . $nextTaskLabel . '.',
        'work' => 'Mike of All Trades update: Mike has started work on ' . $nextTaskLabel . '.',
        'leave_site' => 'Mike of All Trades update: Mike has left the job site. Today’s recorded work and travel times have been updated.',
        'supplier_out' => 'Mike of All Trades update: Mike is travelling to a supplier for materials required for your job. This change has been recorded in the job timeline.',
        'supplier_return' => 'Mike of All Trades update: Mike is returning from the supplier with materials for your job.',
    ];
    $importanceByAction = [
        'travel_site' => 'important',
        'arrive_site' => 'important',
        'work' => 'routine',
        'leave_site' => 'important',
        'supplier_out' => 'important',
        'supplier_return' => 'routine',
    ];
    qa_sms(
        $pdo,
        $job,
        $smsByAction[$action],
        'quick_' . $action,
        'Quick activity update',
        $importanceByAction[$action] ?? 'routine'
    );

    $_SESSION['wt_quick_action_flash'] = ['ok' => true, 'message' => 'Quick action saved without overlapping timers.'];
    qa_back($jobId, 'quick_action_saved');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    qa_fail($jobId, $e->getMessage());
}
