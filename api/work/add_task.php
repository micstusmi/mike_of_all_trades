<?php
require_once __DIR__ . '/_admin_auth.php';
require_once __DIR__ . '/../../includes/work_tracker.php';
$id=(int)($_POST['job_id']??0); wt_job($pdo,$id);
$title=trim($_POST['title']??''); if($title==='')die('Task title is required.');
$desc=trim($_POST['description']??'');
$customerSummary=trim($_POST['customer_summary']??'');
$detailedProcedure=trim($_POST['detailed_procedure']??'');
$timeDrivers=trim($_POST['time_drivers']??'');
$waitingCuringNotes=trim($_POST['waiting_curing_notes']??'');
$suggestedMaterials=trim($_POST['suggested_materials']??'');
$origins=['original','customer_requested','mike_added','ai_suggested','unforeseen'];
$origin=$_POST['task_origin']??'mike_added'; if(!in_array($origin,$origins,true))$origin='mike_added';
$num=function($k){$v=trim((string)($_POST[$k]??''));return $v===''?null:max(0,(float)$v);};
$q=$pdo->prepare("SELECT COALESCE(MAX(task_order),0)+10 FROM work_tasks WHERE job_id=?");$q->execute([$id]);$order=(int)$q->fetchColumn();
$q=$pdo->prepare("INSERT INTO work_tasks(job_id,task_order,title,description,customer_summary,detailed_procedure,time_drivers,waiting_curing_notes,suggested_materials,task_origin,ai_estimate_low,ai_estimate_high,ai_reasoning,mike_estimate_low,mike_estimate_high,mike_reasoning,customer_visible) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
$q->execute([$id,$order,$title,$desc?:null,$customerSummary?:null,$detailedProcedure?:null,$timeDrivers?:null,$waitingCuringNotes?:null,$suggestedMaterials?:null,$origin,$num('ai_estimate_low'),$num('ai_estimate_high'),trim($_POST['ai_reasoning']??'')?:null,$num('mike_estimate_low'),$num('mike_estimate_high'),trim($_POST['mike_reasoning']??'')?:null,isset($_POST['customer_visible'])?1:0]);
header("Location: ../../admin/work/manage_job.php?id=$id&task_added=1#tasks");exit;
