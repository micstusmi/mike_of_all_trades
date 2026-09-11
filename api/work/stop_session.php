<?php
declare(strict_types=1);
require_once __DIR__ . '/_admin_auth.php';
require_once __DIR__ . '/../../includes/work_tracker.php';

$id=(int)($_POST['job_id']??0); $sid=(int)($_POST['session_id']??0);
$job=wt_job($pdo,$id);
$action=(string)($_POST['session_action']??'finish');
if(!in_array($action,['finish','change'],true)) die('Invalid session action.');
$note=mb_substr(trim((string)($_POST['stop_note']??'')),0,1000);

$pdo->beginTransaction();
try {
    $q=$pdo->prepare("SELECT * FROM work_sessions WHERE id=? AND job_id=? AND ended_at IS NULL FOR UPDATE");
    $q->execute([$sid,$id]); $old=$q->fetch(PDO::FETCH_ASSOC);
    if(!$old) throw new RuntimeException('Running session not found.');
    $pdo->prepare("UPDATE work_session_breaks SET ended_at=UTC_TIMESTAMP() WHERE session_id=? AND ended_at IS NULL")->execute([$sid]);
    $pdo->prepare("UPDATE work_sessions SET ended_at=UTC_TIMESTAMP(),stop_reason=?,stop_note=? WHERE id=?")
        ->execute([$action==='change'?'changed_activity':'completed_activity',$note?:null,$sid]);

    if($action==='change') {
        $location=(string)($_POST['start_location']??'onsite');
        $category=(string)($_POST['category']??'other');
        $allowedLocations=['onsite','bunnings','supplier','travel_job','workshop_home','offsite_planning','other'];
        $allowedCategories=['onsite','measurement','planning','procurement','travel','loading_setup','demolition','repair','unforeseen','other'];
        if(!in_array($location,$allowedLocations,true)) $location='other';
        if(!in_array($category,$allowedCategories,true)) $category='other';
        $detail=mb_substr(trim((string)($_POST['location_detail']??'')),0,255);
        $doing=mb_substr(trim((string)($_POST['notes']??'')),0,1000);
        if($doing==='') throw new RuntimeException('Describe the new activity.');
        $taskId=(int)($_POST['task_id']??($old['task_id']??0));
        $pdo->prepare("INSERT INTO work_sessions(job_id,session_source,worker_id,task_id,started_at,category,start_location,location_detail,billable,notes) VALUES(?,'live',?,?,UTC_TIMESTAMP(),?,?,?,?,?)")
            ->execute([$id,$old['worker_id'],$taskId?:null,$category,$location,$detail?:null,1,$doing]);
        $status='active';
    } else $status='paused';
    $pdo->prepare("UPDATE work_jobs SET status=? WHERE id=?")->execute([$status,$id]);
    $pdo->commit();
} catch(Throwable $e) {
    if($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(409); die($e->getMessage());
}
if(!empty($job['customer_phone'])){
    $mode=$job['customer_update_mode']??'full_transparency';
    if($mode==='full_transparency' || ($mode==='important_only' && $action==='change')){
        $label=$action==='change'?'changed activity: '.$doing:'finished the current activity';
        $msg="Mike of All Trades — Job update\n".($action==='change'?'🔄 ':'■ ').date('g:i a')." — Mike ".$label.".\nLive job record: ".wt_public_url($job);
        wt_send_sms($pdo,$id,$job['customer_phone'],$msg,$action==='change'?'session_changed':'session_finished');
    }
}
header("Location: ../../admin/work/job.php?id=$id&".($action==='change'?'changed=1':'finished=1')."#live-timer"); exit;
