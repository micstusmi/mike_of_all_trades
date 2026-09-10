<?php
require_once __DIR__ . '/../../admin/work/_auth.php';
require_once __DIR__ . '/../../includes/work_tracker.php';

$jobId = (int)($_POST['job_id'] ?? 0);

if ($jobId <= 0) {
    http_response_code(400);
    exit('Invalid job ID.');
}

try {
    $job = wt_job($pdo, $jobId);

    $name = trim((string)($_POST['customer_name'] ?? ''));
    $email = trim((string)($_POST['customer_email'] ?? ''));
    $phoneRaw = trim((string)($_POST['customer_phone'] ?? ''));

    if ($name === '') {
        throw new RuntimeException('Customer name is required.');
    }

    $phone = $phoneRaw !== '' ? wt_normalise_phone($phoneRaw) : null;

    $q = $pdo->prepare("
        UPDATE work_jobs
        SET customer_name=?,
            customer_email=?,
            customer_phone=?
        WHERE id=?
    ");

    $q->execute([
        $name,
        $email !== '' ? $email : null,
        $phone,
        $jobId
    ]);

    header(
        'Location: ../../admin/work/job.php?id=' .
        $jobId .
        '&customer_saved=1#customer-details'
    );
    exit;

} catch (Throwable $e) {
    http_response_code(400);
    echo 'Could not update customer details: ' . wt_html($e->getMessage());
}
