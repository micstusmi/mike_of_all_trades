<?php
declare(strict_types=1);
require_once __DIR__ . '/_admin_auth.php';
require_once __DIR__ . '/../../includes/work_task_updates.php';
if($_SERVER['REQUEST_METHOD']!=='POST')wtu_fail('POST required.',405);
$jobId=(int)($_POST['job_id']??0);$requestId=(int)($_POST['request_id']??0);wt_job($pdo,$jobId);
$q=$pdo->prepare("SELECT * FROM work_task_update_requests WHERE id=? AND job_id=? AND status='ready' LIMIT 1");$q->execute([$requestId,$jobId]);$request=$q->fetch(PDO::FETCH_ASSOC);if(!$request)wtu_fail('This task comparison is unavailable or has already been applied.',409);
$proposal=json_decode((string)$request['proposal_json'],true);if(!is_array($proposal)||!is_array($proposal['items']??null))wtu_fail('The stored task comparison is invalid.',500);
$selected=array_values(array_unique(array_map('intval',(array)($_POST['selected']??[]))));if(!$selected)wtu_fail('Select at least one proposed addition or update.');
$pdo->beginTransaction();
try{
    $orderQ=$pdo->prepare('SELECT COALESCE(MAX(task_order),0) FROM work_tasks WHERE job_id=?');$orderQ->execute([$jobId]);$order=(int)$orderQ->fetchColumn();
    $batch='merge-'.$requestId.'-'.date('YmdHis');$added=0;$updated=0;$approvedText=[];
    $insert=$pdo->prepare("INSERT INTO work_tasks(job_id,task_order,title,description,customer_summary,detailed_procedure,time_drivers,waiting_curing_notes,suggested_materials,status,task_origin,ai_batch_key,ai_estimate_low,ai_estimate_high,ai_reasoning,customer_visible) VALUES(?,?,?,?,?,?,?,?,?,'not_started','ai_suggested',?,?,?,?,1)");
    $update=$pdo->prepare('UPDATE work_tasks SET title=?,description=?,customer_summary=?,detailed_procedure=?,time_drivers=?,waiting_curing_notes=?,suggested_materials=?,ai_estimate_low=?,ai_estimate_high=?,ai_reasoning=?,updated_at=NOW() WHERE id=? AND job_id=?');
    $num=static fn($v):?float=>is_numeric($v)?max(0,(float)$v):null;
    foreach($selected as $index){
        $item=$proposal['items'][$index]??null;if(!is_array($item))continue;$action=(string)($item['action']??'');$task=is_array($item['task']??null)?$item['task']:[];$title=mb_substr(trim((string)($task['title']??'')),0,255);if($title==='')continue;
        $values=[$title,trim((string)($task['description']??''))?:null,trim((string)($task['customer_summary']??''))?:null,trim((string)($task['detailed_procedure']??''))?:null,trim((string)($task['time_drivers']??''))?:null,trim((string)($task['waiting_curing_notes']??''))?:null,trim((string)($task['suggested_materials']??''))?:null,$num($task['ai_estimate_low']??null),$num($task['ai_estimate_high']??null),trim((string)($task['ai_reasoning']??''))?:null];
        if($action==='add'){$order+=10;$insert->execute([$jobId,$order,$values[0],$values[1],$values[2],$values[3],$values[4],$values[5],$values[6],$batch,$values[7],$values[8],$values[9]]);$added++;$approvedText[]=$title.($values[1]?': '.$values[1]:'');}
        elseif($action==='update'){$taskId=(int)($item['matched_task_id']??0);$check=$pdo->prepare('SELECT * FROM work_tasks WHERE id=? AND job_id=? LIMIT 1');$check->execute([$taskId,$jobId]);$existing=$check->fetch(PDO::FETCH_ASSOC);if(!$existing)continue;$columns=['title','description','customer_summary','detailed_procedure','time_drivers','waiting_curing_notes','suggested_materials','ai_estimate_low','ai_estimate_high','ai_reasoning'];foreach($columns as $position=>$column){if($values[$position]===null||$values[$position]==='')$values[$position]=$existing[$column]??null;}$update->execute(array_merge($values,[$taskId,$jobId]));$updated++;$approvedText[]=(string)$values[0].($values[1]?': '.$values[1]:'');}
    }
    if($added+$updated<1)throw new RuntimeException('None of the selected suggestions could be applied.');
    $mergeText=implode("\n",$approvedText);wtu_merge_request_text($pdo,$jobId,$mergeText);
    $pdo->prepare("UPDATE work_task_update_requests SET status='applied',applied_at=NOW(),applied_by='Mike / admin' WHERE id=? AND job_id=? AND status='ready'")->execute([$requestId,$jobId]);
    $pdo->commit();
    header('Location: ../../admin/work/manage_job.php?id='.$jobId.'&task_merge_added='.$added.'&task_merge_updated='.$updated.'#tasks');exit;
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();wtu_fail('The selected task changes could not be applied: '.$e->getMessage(),500);}
