<?php
require_once __DIR__ . '/_admin_auth.php';
require_once __DIR__ . '/../../includes/work_tracker.php';
$id=(int)($_POST['job_id']??0); $taskId=(int)($_POST['task_id']??0); wt_job($pdo,$id);
$q=$pdo->prepare("SELECT id FROM work_tasks WHERE id=? AND job_id=?");$q->execute([$taskId,$id]);if(!$q->fetchColumn())die('Task not found.');
$title=trim($_POST['title']??''); if($title==='')die('Task title is required.');
$statuses=['not_started','in_progress','blocked','completed','cancelled'];$status=$_POST['status']??'not_started';if(!in_array($status,$statuses,true))$status='not_started';
$origins=['original','customer_requested','mike_added','ai_suggested','unforeseen'];$origin=$_POST['task_origin']??'mike_added';if(!in_array($origin,$origins,true))$origin='mike_added';
$num=function($k){$v=trim((string)($_POST[$k]??''));return $v===''?null:max(0,(float)$v);};
$completed=$status==='completed'?'COALESCE(completed_at,NOW())':'NULL';
$sql="UPDATE work_tasks SET title=?,description=?,customer_summary=?,detailed_procedure=?,time_drivers=?,waiting_curing_notes=?,suggested_materials=?,status=?,task_origin=?,ai_estimate_low=?,ai_estimate_high=?,ai_reasoning=?,mike_estimate_low=?,mike_estimate_high=?,mike_reasoning=?,actual_adjusted_hours=?,actual_reasoning=?,customer_visible=?,completed_at=$completed WHERE id=? AND job_id=?";
$q=$pdo->prepare($sql);$q->execute([$title,trim($_POST['description']??'')?:null,trim($_POST['customer_summary']??'')?:null,trim($_POST['detailed_procedure']??'')?:null,trim($_POST['time_drivers']??'')?:null,trim($_POST['waiting_curing_notes']??'')?:null,trim($_POST['suggested_materials']??'')?:null,$status,$origin,$num('ai_estimate_low'),$num('ai_estimate_high'),trim($_POST['ai_reasoning']??'')?:null,$num('mike_estimate_low'),$num('mike_estimate_high'),trim($_POST['mike_reasoning']??'')?:null,$num('actual_adjusted_hours'),trim($_POST['actual_reasoning']??'')?:null,isset($_POST['customer_visible'])?1:0,$taskId,$id]);
header("Location: ../../admin/work/manage_job.php?id=$id&task_saved=1#task-$taskId");exit;
