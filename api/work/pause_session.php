<?php
declare(strict_types=1);
require_once __DIR__ . '/_admin_auth.php';
require_once __DIR__ . '/../../includes/work_tracker.php';

$id=(int)($_POST['job_id']??0); $sid=(int)($_POST['session_id']??0); $job=wt_job($pdo,$id);
$reason=(string)($_POST['pause_reason']??'personal_break');
$allowed=['toilet','meal','personal_break','personal_call','rest','other'];
if(!in_array($reason,$allowed,true)) $reason='other';
$note=mb_substr(trim((string)($_POST['pause_note']??'')),0,500);

$pdo->beginTransaction();
try {
    $q=$pdo->prepare("SELECT id FROM work_sessions WHERE id=? AND job_id=? AND ended_at IS NULL FOR UPDATE");
    $q->execute([$sid,$id]);
    if(!$q->fetchColumn()) throw new RuntimeException('Running session not found.');
    $q=$pdo->prepare("SELECT id FROM work_session_breaks WHERE session_id=? AND ended_at IS NULL LIMIT 1");
    $q->execute([$sid]);
    if(!$q->fetchColumn()) {
        $pdo->prepare("INSERT INTO work_session_breaks(session_id,started_at,reason,note) VALUES(?,UTC_TIMESTAMP(),?,?)")
            ->execute([$sid,$reason,$note?:null]);
    }
    $pdo->prepare("UPDATE work_jobs SET status='paused' WHERE id=?")->execute([$id]);
    $pdo->commit();
} catch(Throwable $e) {
    if($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(409); die($e->getMessage());
}
if(($job['customer_update_mode']??'full_transparency')==='full_transparency' && !empty($job['customer_phone'])){
    $msg="Mike of All Trades — Job update\n⏸ ".date('g:i a')." — activity paused for a short non-chargeable break.\nThis break is excluded from your job time.\nLive job record: ".wt_public_url($job);
    wt_send_sms($pdo,$id,$job['customer_phone'],$msg,'session_paused');
}
header("Location: ../../admin/work/job.php?id=$id&paused=1#live-timer"); exit;
