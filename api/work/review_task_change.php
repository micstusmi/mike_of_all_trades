<?php
require_once __DIR__ . '/_admin_auth.php';
require_once __DIR__ . '/../../includes/work_tracker.php';
$jobId=(int)($_POST['job_id']??0);$requestId=(int)($_POST['request_id']??0);$job=wt_job($pdo,$jobId);
$q=$pdo->prepare("SELECT cr.*,t.title AS task_title FROM work_task_change_requests cr JOIN work_tasks t ON t.id=cr.task_id WHERE cr.id=? AND cr.job_id=? LIMIT 1");$q->execute([$requestId,$jobId]);$cr=$q->fetch(PDO::FETCH_ASSOC);if(!$cr)die('Change request not found.');
$statuses=['accepted','amended','declined','question_sent'];$status=$_POST['status']??'question_sent';if(!in_array($status,$statuses,true))$status='question_sent';
$affects=['unknown','no','yes'];$aff=$_POST['affects_estimate']??'unknown';if(!in_array($aff,$affects,true))$aff='unknown';
$num=function($k){$v=trim((string)($_POST[$k]??''));return $v===''?null:max(0,(float)$v);};
$response=trim((string)($_POST['mike_response']??''));
$q=$pdo->prepare("UPDATE work_task_change_requests SET status=?,mike_response=?,affects_estimate=?,estimate_delta_low=?,estimate_delta_high=?,reviewed_at=NOW() WHERE id=? AND job_id=?");$q->execute([$status,$response?:null,$aff,$num('estimate_delta_low'),$num('estimate_delta_high'),$requestId,$jobId]);
$label=['accepted'=>'accepted','amended'=>'accepted with amendments','declined'=>'declined','question_sent'=>'needs clarification'][$status];
$extra='';if($aff==='yes'){$lo=$num('estimate_delta_low');$hi=$num('estimate_delta_high');$extra=' This may affect time/cost.';if($lo!==null||$hi!==null){$extra.=' Approx. additional time: '.($lo!==null?number_format($lo,1):'?').'–'.($hi!==null?number_format($hi,1):'?').' h.';}}
if(!empty($job['customer_phone'])){$msg='Mike of All Trades: Your change request for "'.$cr['task_title'].'" has been '.$label.'.'.$extra;if($response!==''){$r=preg_replace('/\\s+/',' ',$response);if(mb_strlen($r)>180)$r=mb_substr($r,0,177).'...';$msg.=' Mike: '.$r;}$msg.=' View: '.wt_public_url($job);wt_send_sms($pdo,$jobId,$job['customer_phone'],$msg,'task_change_review');}
header('Location: ../../admin/work/manage_job.php?id='.$jobId.'&change_reviewed=1#task-'.$cr['task_id']);exit;
