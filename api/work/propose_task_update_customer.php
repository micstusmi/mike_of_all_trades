<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/work_task_updates.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') wtu_fail('POST required.', 405);
$token = trim((string)($_POST['token'] ?? ''));
if ($token === '') wtu_fail('Missing job link.');
try { $job = wt_job_by_token($pdo, $token); } catch (Throwable $e) { wtu_fail('Job not found.', 404); }
try {
    $requestId = wtu_create_proposal($pdo, (int)$job['id'], 'customer', (string)($job['customer_name'] ?? 'Customer'), (string)($_POST['updated_task_list'] ?? ''), $_FILES['task_list_files'] ?? []);
    $mikePhone = wt_env('WORK_TRACKER_MIKE_MOBILE');
    if ($mikePhone) wt_send_sms($pdo, (int)$job['id'], $mikePhone, 'Mike of All Trades: '.$job['customer_name'].' submitted an AI-compared task-list update for Job #'.$job['id'].'. Review it before applying any changes.', 'customer_ai_task_update');
    header('Location: ../../work/job.php?t='.urlencode($token).'&task_update_submitted=1#customer-request');
    exit;
} catch (Throwable $e) {
    wtu_fail('The updated list could not be compared: '.$e->getMessage(), 500);
}
