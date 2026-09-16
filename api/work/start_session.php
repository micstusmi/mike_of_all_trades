<?php
require_once __DIR__ . '/_admin_auth.php';
require_once __DIR__ . '/../../includes/work_tracker.php';

$id = (int)($_POST['job_id'] ?? 0);
$job = wt_job($pdo, $id);

$workerRaw = $_POST['worker_id'] ?? 'mike';
$workerId = ($workerRaw === '' || $workerRaw === 'mike') ? null : (int)$workerRaw;

if ($workerId !== null) {
    $checkWorker = $pdo->prepare("SELECT id,worker_name FROM work_workers WHERE id=? AND job_id=? AND active=1");
    $checkWorker->execute([$workerId, $id]);
    $workerRow = $checkWorker->fetch(PDO::FETCH_ASSOC);
    if (!$workerRow) die('Invalid worker for this job.');
    $workerName = $workerRow['worker_name'];
} else {
    $workerName = 'Mike';
}

if ($workerId === null) {
    $dup = $pdo->prepare("SELECT id FROM work_sessions WHERE job_id=? AND worker_id IS NULL AND ended_at IS NULL LIMIT 1");
    $dup->execute([$id]);
} else {
    $dup = $pdo->prepare("SELECT id FROM work_sessions WHERE job_id=? AND worker_id=? AND ended_at IS NULL LIMIT 1");
    $dup->execute([$id, $workerId]);
}
if ($dup->fetchColumn()) {
    header("Location: ../../admin/work/job.php?id=$id&duplicate=1");
    exit;
}

$allowedLocations = ['onsite','bunnings','supplier','travel_job','workshop_home','offsite_planning','other'];
$location = $_POST['start_location'] ?? 'onsite';
if (!in_array($location, $allowedLocations, true)) $location = 'other';

$locationLabels = [
    'onsite'=>'On site',
    'bunnings'=>'Bunnings',
    'supplier'=>'Another supplier / store',
    'travel_job'=>'Travelling for this job',
    'workshop_home'=>'Workshop / home preparation',
    'offsite_planning'=>'Off-site planning / admin for this job',
    'other'=>'Other'
];

$allowedCategories = ['onsite','measurement','planning','procurement','travel','loading_setup','demolition','repair','unforeseen','other'];
$category = $_POST['category'] ?? 'onsite';
if (!in_array($category, $allowedCategories, true)) $category = 'other';

$categoryLabels = [
    'onsite'=>'On-site work',
    'measurement'=>'Measurement / investigation',
    'planning'=>'Planning',
    'procurement'=>'Sourcing / procurement',
    'travel'=>'Job-specific travel',
    'loading_setup'=>'Loading / setup / pack-up',
    'demolition'=>'Demolition / removal',
    'repair'=>'Repair / preparation',
    'unforeseen'=>'Unforeseen / remedial',
    'other'=>'Other'
];

$notes = trim($_POST['notes'] ?? '');
$taskId=(int)($_POST['task_id']??0);
$taskTitle='';
if($taskId>0){$tq=$pdo->prepare("SELECT title FROM work_tasks WHERE id=? AND job_id=? AND status<>'cancelled'");$tq->execute([$taskId,$id]);$taskTitle=(string)$tq->fetchColumn();if($taskTitle==='')die('Invalid task for this job.');}
$startPhotoCount=wt_upload_field_file_count('start_before_photos');
if($startPhotoCount>20)die('Please upload no more than 20 before photos when starting an activity.');
if($taskId<=0 && wt_upload_field_has_files('start_before_photos')){
    $taskId=wt_get_or_create_general_photo_task($pdo,$id);
    $taskTitle='General job photos';
}
$locationDetail = trim($_POST['location_detail'] ?? '');
$billable = ($category === 'travel' || $location === 'travel_job')
    ? (isset($_POST['charge_travel']) ? 1 : 0)
    : 1;

$stmt = $pdo->prepare("
    INSERT INTO work_sessions
    (job_id,session_source,worker_id,task_id,started_at,category,start_location,location_detail,billable,notes)
    VALUES(?,'live',?,?,UTC_TIMESTAMP(),?,?,?,?,?)
");
$stmt->execute([$id, $workerId, $taskId?:null, $category, $location, $locationDetail ?: null, $billable, $notes]);
if($taskId>0)$pdo->prepare("UPDATE work_tasks SET status=IF(status IN ('not_started','blocked'),'in_progress',status) WHERE id=? AND job_id=?")->execute([$taskId,$id]);

if($taskId>0){
    $photoNote=trim((string)($_POST['start_photo_note']??''));
    wt_save_task_photo_upload_field($pdo,$id,$taskId,'start_before_photos','before',$photoNote,20);
}

$pdo->prepare("UPDATE work_jobs SET status='active' WHERE id=?")->execute([$id]);

$mode = $job['customer_update_mode'] ?? 'full_transparency';
$send = ($mode === 'full_transparency');

if ($mode === 'important_only') {
    // Supplier/travel/unforeseen changes are meaningful enough to text automatically.
    $send = in_array($location, ['bunnings','supplier','travel_job'], true)
        || in_array($category, ['procurement','travel','unforeseen'], true);
}

if ($send && !empty($job['customer_phone'])) {
    $planStmt = $pdo->prepare("SELECT planned_finish_time,anticipated_job_hours_low,anticipated_job_hours_high
                              FROM work_daily_plans WHERE job_id=? AND plan_date=CURDATE() LIMIT 1");
    $planStmt->execute([$id]);
    $plan = $planStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $where = $locationLabels[$location] ?? 'Job activity';
    if ($locationDetail !== '') $where .= ' — '.$locationDetail;

    $msg = "Mike of All Trades — Job update\n";
    $msg .= "▶ ".date('g:i a')." — ".$workerName." started job activity.\n";
    $msg .= "Location: ".$where.".\n";
    $msg .= "Activity: ".($categoryLabels[$category] ?? $category).".";
    if ($taskTitle !== '') $msg .= "\nTask: ".$taskTitle.".";
    if ($notes !== '') $msg .= "\nNow doing: ".$notes.".";

    if (!empty($plan['planned_finish_time'])) {
        $msg .= "\nToday's anticipated finish: approx. ".date('g:i a', strtotime($plan['planned_finish_time'])).".";
    }

    $msg .= "\nLive job record: ".wt_public_url($job);

    wt_send_sms($pdo, $id, $job['customer_phone'], $msg, 'session_started');
}

header("Location: ../../admin/work/job.php?id=$id&started=1");
exit;
