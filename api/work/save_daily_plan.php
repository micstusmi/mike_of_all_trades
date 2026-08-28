<?php
require_once __DIR__ . '/_admin_auth.php';
require_once __DIR__ . '/../../includes/work_tracker.php';

$jobId = (int)($_POST['job_id'] ?? 0);
$job = wt_job($pdo, $jobId);

$date = $_POST['plan_date'] ?? date('Y-m-d');
$start = trim($_POST['planned_start_time'] ?? '');
$finish = trim($_POST['planned_finish_time'] ?? '');
$low = trim($_POST['anticipated_job_hours_low'] ?? '');
$high = trim($_POST['anticipated_job_hours_high'] ?? '');
$count = max(1, (int)($_POST['expected_worker_count'] ?? 1));
$workersText = trim($_POST['expected_workers_text'] ?? '');
$helperRoles = trim($_POST['helper_roles'] ?? '');
$interruptions = trim($_POST['planned_interruptions'] ?? '');
$note = trim($_POST['overall_plan_note'] ?? '');

$stmt = $pdo->prepare("
    INSERT INTO work_daily_plans
    (job_id, plan_date, planned_start_time, planned_finish_time,
     anticipated_job_hours_low, anticipated_job_hours_high,
     expected_worker_count, expected_workers_text, helper_roles,
     planned_interruptions, overall_plan_note, updated_at)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())
    ON DUPLICATE KEY UPDATE
      planned_start_time=VALUES(planned_start_time),
      planned_finish_time=VALUES(planned_finish_time),
      anticipated_job_hours_low=VALUES(anticipated_job_hours_low),
      anticipated_job_hours_high=VALUES(anticipated_job_hours_high),
      expected_worker_count=VALUES(expected_worker_count),
      expected_workers_text=VALUES(expected_workers_text),
      helper_roles=VALUES(helper_roles),
      planned_interruptions=VALUES(planned_interruptions),
      overall_plan_note=VALUES(overall_plan_note),
      updated_at=NOW()
");
$stmt->execute([
    $jobId, $date,
    $start !== '' ? $start : null,
    $finish !== '' ? $finish : null,
    $low !== '' ? $low : null,
    $high !== '' ? $high : null,
    $count,
    $workersText !== '' ? $workersText : null,
    $helperRoles !== '' ? $helperRoles : null,
    $interruptions !== '' ? $interruptions : null,
    $note !== '' ? $note : null
]);

header("Location: ../../admin/work/job.php?id=".$jobId."&plan_saved=1");
exit;
