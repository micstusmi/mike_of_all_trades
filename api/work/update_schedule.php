<?php
require_once __DIR__ . '/../../includes/auth_admin.php';
require_once __DIR__ . '/../../includes/work_tracker.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('POST required.');
}

$jobId = (int)($_POST['job_id'] ?? 0);

if ($jobId <= 0) {
    http_response_code(400);
    die('Invalid job.');
}

$job = wt_job($pdo, $jobId);

function mot_schedule_datetime(?string $value): ?string {
    $value = trim((string)$value);

    if ($value === '') {
        return null;
    }

    $ts = strtotime($value);

    if ($ts === false) {
        throw new InvalidArgumentException('Invalid date/time.');
    }

    return date('Y-m-d H:i:s', $ts);
}

try {
    $plannedStart  = mot_schedule_datetime($_POST['planned_start_at'] ?? null);
    $plannedFinish = mot_schedule_datetime($_POST['planned_finish_at'] ?? null);

    if ($plannedStart && $plannedFinish && strtotime($plannedFinish) < strtotime($plannedStart)) {
        throw new InvalidArgumentException('Expected finish cannot be before the planned start.');
    }

    $parkingNotes = trim((string)($_POST['parking_notes'] ?? ''));
    $accessNotes  = trim((string)($_POST['access_notes'] ?? ''));

    $oldStart = !empty($job['planned_start_at'])
        ? date('Y-m-d H:i:s', strtotime($job['planned_start_at']))
        : null;

    $startChanged = ($oldStart !== $plannedStart);

    if ($startChanged) {
        $sql = "
            UPDATE work_jobs
            SET planned_start_at=?,
                planned_finish_at=?,
                parking_notes=?,
                access_notes=?,
                customer_confirmation_status='not_requested',
                confirmation_requested_at=NULL,
                confirmation_received_at=NULL
            WHERE id=?
        ";
    } else {
        $sql = "
            UPDATE work_jobs
            SET planned_start_at=?,
                planned_finish_at=?,
                parking_notes=?,
                access_notes=?
            WHERE id=?
        ";
    }

    $q = $pdo->prepare($sql);
    $q->execute([
        $plannedStart,
        $plannedFinish,
        $parkingNotes !== '' ? $parkingNotes : null,
        $accessNotes !== '' ? $accessNotes : null,
        $jobId
    ]);

    header('Location: ../../admin/work/job.php?id=' . $jobId . '&schedule_saved=1#schedule-access');
    exit;

} catch (Throwable $e) {
    http_response_code(400);
    echo 'Could not save schedule: ' . wt_html($e->getMessage());
}
