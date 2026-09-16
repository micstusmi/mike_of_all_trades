<?php
declare(strict_types=1);

require_once __DIR__ . '/_admin_auth.php';
require_once __DIR__ . '/../../includes/work_tracker.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('POST required.');
}

$jobId = (int)($_POST['job_id'] ?? 0);
$rawList = trim((string)($_POST['customer_task_list'] ?? ''));

if ($jobId <= 0) {
    http_response_code(400);
    exit('Invalid job ID.');
}

if ($rawList === '') {
    http_response_code(400);
    exit('Paste the customer task list first.');
}

$job = wt_job($pdo, $jobId);
$items = wt_split_customer_request_items($rawList);

if (!$items) {
    http_response_code(400);
    exit('No separate tasks could be found in that list.');
}

$pdo->beginTransaction();
try {
    $old = (string)($job['customer_request_text'] ?? $job['original_scope'] ?? '');
    $newText = wt_replace_job_intake_items($pdo, $jobId, $items);

    $pdo->prepare("
        UPDATE work_jobs
        SET customer_request_text=?,
            original_scope=?,
            customer_request_updated_at=NOW(),
            ai_breakdown_status='pending',
            ai_breakdown_error=NULL
        WHERE id=?
    ")->execute([$newText, $newText, $jobId]);

    $pdo->prepare("
        INSERT INTO work_job_request_revisions
        (job_id,source,previous_text,new_text,note,requires_review,reviewed_at)
        VALUES(?,'mike',?,?,?,0,NOW())
    ")->execute([
        $jobId,
        $old,
        $newText,
        'Bulk pasted customer task list split into ' . count($items) . ' requested items',
    ]);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    exit('Could not import customer task list: ' . $e->getMessage());
}

header('Location: ../../admin/work/manage_job.php?id=' . $jobId . '&run_ai=1&bulk_list_imported=' . count($items) . '#tasks');
exit;
