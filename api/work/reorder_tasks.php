<?php
declare(strict_types=1);

require_once __DIR__ . '/_admin_auth.php';
require_once __DIR__ . '/../../includes/work_tracker.php';

header('Content-Type: application/json; charset=utf-8');

function reorder_fail(string $message, int $status = 400): never
{
    http_response_code($status);

    echo json_encode([
        'ok' => false,
        'error' => $message,
    ]);

    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    reorder_fail('POST required.', 405);
}

$data = json_decode(
    file_get_contents('php://input') ?: '{}',
    true
);

if (!is_array($data)) {
    reorder_fail('Invalid JSON.');
}

$jobId = (int)($data['job_id'] ?? 0);
$requestedIds = $data['active_task_ids'] ?? [];

if ($jobId <= 0 || !is_array($requestedIds)) {
    reorder_fail('Invalid request.');
}

wt_job($pdo, $jobId);

$requestedIds = array_values(array_unique(array_filter(
    array_map('intval', $requestedIds),
    static fn(int $id): bool => $id > 0
)));

try {
    $pdo->beginTransaction();

    /*
     * Lock the entire non-cancelled task sequence for this job.
     *
     * Completed tasks retain their underlying planning positions.
     * Only active tasks are redistributed through the existing
     * active slots.
     */
    $q = $pdo->prepare("
        SELECT
            id,
            status,
            task_order
        FROM work_tasks
        WHERE job_id=?
          AND status <> 'cancelled'
        ORDER BY task_order,id
        FOR UPDATE
    ");

    $q->execute([$jobId]);
    $allTasks = $q->fetchAll(PDO::FETCH_ASSOC);

    /*
     * First normalise the complete planning sequence to stable
     * 10-point slots while preserving its current overall order.
     */
    $normalised = [];
    $position = 10;

    foreach ($allTasks as $task) {
        $normalised[] = [
            'id' => (int)$task['id'],
            'status' => (string)$task['status'],
            'slot' => $position,
        ];

        $position += 10;
    }

    $databaseActiveIds = [];
    $activeSlots = [];

    foreach ($normalised as $task) {
        if ($task['status'] !== 'completed') {
            $databaseActiveIds[] = $task['id'];
            $activeSlots[] = $task['slot'];
        }
    }

    $a = $requestedIds;
    $b = $databaseActiveIds;

    sort($a);
    sort($b);

    if ($a !== $b) {
        $pdo->rollBack();

        reorder_fail(
            'The task list changed while it was being reordered. ' .
            'Reload the page and try again.',
            409
        );
    }

    $update = $pdo->prepare("
        UPDATE work_tasks
        SET
            task_order=?,
            updated_at=updated_at
        WHERE id=? AND job_id=?
    ");

    /*
     * Preserve completed-task slots.
     */
    foreach ($normalised as $task) {
        if ($task['status'] === 'completed') {
            $update->execute([
                $task['slot'],
                $task['id'],
                $jobId,
            ]);
        }
    }

    /*
     * Put the user-selected active ordering into the available
     * active slots.
     */
    foreach ($requestedIds as $index => $taskId) {
        $update->execute([
            $activeSlots[$index],
            $taskId,
            $jobId,
        ]);
    }

    $pdo->commit();

    echo json_encode([
        'ok' => true,
        'active_task_ids' => $requestedIds,
    ]);

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Work Tracker task reorder failed: ' .
        $e->getMessage()
    );

    reorder_fail('Could not save the task order.', 500);
}
