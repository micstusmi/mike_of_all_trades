<?php
declare(strict_types=1);
require_once __DIR__ . '/_admin_auth.php';
require_once __DIR__ . '/../../includes/work_task_updates.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') wtu_fail('POST required.', 405);
$jobId = (int)($_POST['job_id'] ?? 0);
if ($jobId <= 0) wtu_fail('Invalid job ID.');
wt_job($pdo, $jobId);
try {
    $requestId = wtu_create_proposal($pdo, $jobId, 'mike', 'Mike / admin', (string)($_POST['updated_task_list'] ?? ''), $_FILES['task_list_files'] ?? []);
    header('Location: ../../admin/work/task_update_review.php?id='.$jobId.'&request_id='.$requestId);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    wtu_fail('The AI comparison could not be prepared: '.$e->getMessage(), 500);
}
