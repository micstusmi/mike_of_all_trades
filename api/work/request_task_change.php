<?php
require_once __DIR__ . '/../../includes/work_tracker.php';
$token=trim((string)($_POST['token']??'')); $taskId=(int)($_POST['task_id']??0);
if($token===''||$taskId<=0){http_response_code(400);die('Invalid request.');}
try{$job=wt_job_by_token($pdo,$token);}catch(Throwable $e){http_response_code(404);die('Job not found.');}
$q=$pdo->prepare("SELECT * FROM work_tasks WHERE id=? AND job_id=? AND customer_visible=1 LIMIT 1");$q->execute([$taskId,$job['id']]);$task=$q->fetch(PDO::FETCH_ASSOC);
if(!$task){http_response_code(404);die('Task not found.');}
if(in_array($task['status'],['completed','cancelled'],true)){http_response_code(409);die('This task is already completed or cancelled. Contact Mike directly if further work is required.');}
$types=['instructions','measurement','colour_finish','product_material','positioning','quantity','scheduling','other'];$type=$_POST['request_type']??'instructions';if(!in_array($type,$types,true))$type='instructions';
$msg=trim((string)($_POST['customer_message']??''));if($msg===''){http_response_code(400);die('Please describe the requested change.');}if(mb_strlen($msg)>5000)$msg=mb_substr($msg,0,5000);
// Simple flood protection: no more than 10 awaiting requests on the same task.
$q=$pdo->prepare("SELECT COUNT(*) FROM work_task_change_requests WHERE task_id=? AND status='awaiting_review'");$q->execute([$taskId]);if((int)$q->fetchColumn()>=10){http_response_code(429);die('There are already several change requests awaiting review for this task. Please wait for Mike to review them.');}
$snapshot=json_encode(['title'=>$task['title'],'description'=>$task['description'],'customer_summary'=>$task['customer_summary']??null,'detailed_procedure'=>$task['detailed_procedure']??null,'time_drivers'=>$task['time_drivers']??null,'waiting_curing_notes'=>$task['waiting_curing_notes']??null,'suggested_materials'=>$task['suggested_materials']??null,'status'=>$task['status'],'mike_estimate_low'=>$task['mike_estimate_low'],'mike_estimate_high'=>$task['mike_estimate_high']],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
$q=$pdo->prepare("INSERT INTO work_task_change_requests(job_id,task_id,request_type,customer_message,original_task_snapshot_json) VALUES(?,?,?,?,?)");$q->execute([$job['id'],$taskId,$type,$msg,$snapshot]);
$short=preg_replace('/\\s+/',' ',$msg);if(mb_strlen($short)>180)$short=mb_substr($short,0,177).'...';
if(!empty($job['customer_phone'])) wt_send_sms($pdo,(int)$job['id'],$job['customer_phone'],'Mike of All Trades: Your change request for "'.$task['title'].'" has been recorded and is awaiting Mike\'s review. The task has not been silently changed. View: '.wt_public_url($job),'task_change_customer_confirmation');
$mikePhone=wt_env('WORK_TRACKER_MIKE_MOBILE');
if($mikePhone) wt_send_sms($pdo,(int)$job['id'],$mikePhone,'Mike of All Trades admin: Customer requested a change to "'.$task['title'].'": '.$short.' Review the job in Work Tracker.','task_change_admin_alert');
header('Location: ../../work/job.php?t='.urlencode($token).'&change_requested=1#customer-task-'.$taskId);exit;
