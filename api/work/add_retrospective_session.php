<?php
require_once __DIR__ . '/_admin_auth.php';
require_once __DIR__ . '/../../includes/work_tracker.php';

$id=(int)($_POST['job_id']??0); $job=wt_job($pdo,$id);
$workerRaw=$_POST['worker_id']??'mike';
$workerId=($workerRaw===''||$workerRaw==='mike')?null:(int)$workerRaw;
if($workerId!==null){$q=$pdo->prepare("SELECT id FROM work_workers WHERE id=? AND job_id=? AND active=1");$q->execute([$workerId,$id]);if(!$q->fetchColumn())die('Invalid worker for this job.');}
$date=trim($_POST['work_date']??'');
$hours=(float)($_POST['recorded_hours']??0);
$notes=trim($_POST['notes']??'');
$startTime=trim($_POST['start_time']??''); $endTime=trim($_POST['end_time']??'');
if(!$date||$hours<=0||$hours>24)die('Date and valid total job hours are required.');
if($notes==='')die('Please describe what was done.');
$billable=isset($_POST['billable'])?1:0;
$basis='hours';
// Use noon as an internal anchor for hours-only entries; customer/admin display uses recorded_hours, not these synthetic times.
$start=new DateTime($date.' 12:00'); $end=(clone $start)->modify('+'.round($hours*3600).' seconds');
if($startTime!==''||$endTime!==''){
  if($startTime===''||$endTime==='')die('Enter both start and finish time, or leave both blank.');
  $start=DateTime::createFromFormat('Y-m-d H:i',$date.' '.$startTime); $end=DateTime::createFromFormat('Y-m-d H:i',$date.' '.$endTime);
  if(!$start||!$end||$end<=$start)die('Finish time must be later than start time.');
  if($end>new DateTime('+5 minutes'))die('Retrospective entries must describe work that has already happened.');
  $basis='exact_times';
  $hours=round(($end->getTimestamp()-$start->getTimestamp())/3600,4);
}
$stmt=$pdo->prepare("INSERT INTO work_sessions (job_id,worker_id,started_at,ended_at,session_source,retrospective_entered_at,retrospective_entry_basis,retrospective_hours,category,start_location,billable,notes,stop_reason,stop_note) VALUES(?,?,?,?, 'retrospective',NOW(),?,?,'other','other',?,?,'completed_activity','Retrospective entry — work was completed before it was entered into the tracker.')");
$stmt->execute([$id,$workerId,$start->format('Y-m-d H:i:s'),$end->format('Y-m-d H:i:s'),$basis,$hours,$billable,$notes]);
header("Location: ../../admin/work/job.php?id=$id&retrospective_added=1"); exit;
