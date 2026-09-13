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
    'Task updated'
);

header(
    'Location: ../../admin/work/manage_job.php?id=' .
    $jobId .
    '&task_saved=1#task-' .
    $taskId
);

exit;
