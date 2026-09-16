<?php
declare(strict_types=1);

require_once __DIR__ . '/_admin_auth.php';
require_once __DIR__ . '/../../includes/work_tracker.php';

function qa_back(int $jobId, string $flag): never
{
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

        $breakStmt = $pdo->prepare("SELECT id FROM work_session_breaks WHERE session_id=? AND ended_at IS NULL LIMIT 1 FOR UPDATE");
        $breakStmt->execute([(int)$running['id']]);

        if (!$breakStmt->fetchColumn()) {
            $pdo->prepare("INSERT INTO work_session_breaks(session_id,started_at,reason,note) VALUES(?,UTC_TIMESTAMP(),?,?)")
                ->execute([(int)$running['id'], $pauseMap[$action]['reason'], $pauseMap[$action]['note']]);
        }

        $pdo->prepare("UPDATE work_jobs SET status='paused' WHERE id=?")->execute([$jobId]);
        $pdo->commit();
        $_SESSION['wt_quick_action_flash'] = ['ok' => true, 'message' => $pauseMap[$action]['note'] . ' started.'];
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
        $pdo->prepare("UPDATE work_jobs SET status='paused' WHERE id=?")->execute([$jobId]);
        $pdo->commit();
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

    $_SESSION['wt_quick_action_flash'] = ['ok' => true, 'message' => 'Quick action saved without overlapping timers.'];
    qa_back($jobId, 'quick_action_saved');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    qa_fail($jobId, $e->getMessage());
}
