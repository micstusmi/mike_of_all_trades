<?php
declare(strict_types=1);
require_once __DIR__ . '/_admin_auth.php';
require_once __DIR__ . '/../../includes/work_tracker.php';

$id=(int)($_POST['job_id']??0); $sid=(int)($_POST['session_id']??0); $job=wt_job($pdo,$id);
$pdo->beginTransaction();
try {
    $q=$pdo->prepare("SELECT id FROM work_sessions WHERE id=? AND job_id=? AND ended_at IS NULL FOR UPDATE");
    $q->execute([$sid,$id]);
    if(!$q->fetchColumn()) throw new RuntimeException('Running session not found.');
    $pdo->prepare("UPDATE work_session_breaks SET ended_at=UTC_TIMESTAMP() WHERE session_id=? AND ended_at IS NULL")
        ->execute([$sid]);
    $pdo->prepare("UPDATE work_jobs SET status='active' WHERE id=?")->execute([$id]);
    $pdo->commit();
} catch(Throwable $e) {
    if($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(409); die($e->getMessage());
}
if(($job['customer_update_mode']??'full_transparency')==='full_transparency' && !empty($job['customer_phone'])){
    $msg="Mike of All Trades — Job update\n▶ ".date('g:i a')." — work has continued after the non-chargeable break.\nLive job record: ".wt_public_url($job);
    wt_send_sms($pdo,$id,$job['customer_phone'],$msg,'session_continued');
}
header("Location: ../../admin/work/job.php?id=$id&continued=1#live-timer"); exit;
