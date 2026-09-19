<?php
declare(strict_types=1);
require_once __DIR__.'/_admin_auth.php';
require_once __DIR__.'/../../includes/work_receipts.php';
if($_SERVER['REQUEST_METHOD']!=='POST')wr_fail('POST required.',405);
$jobId=(int)($_POST['job_id']??0);$taskId=(int)($_POST['task_id']??0);$sessionId=(int)($_POST['session_id']??0);wt_job($pdo,$jobId);
if($taskId>0){$q=$pdo->prepare('SELECT id FROM work_tasks WHERE id=? AND job_id=?');$q->execute([$taskId,$jobId]);if(!$q->fetchColumn())wr_fail('Choose a valid task.');}else $taskId=null;
if($sessionId>0){$q=$pdo->prepare('SELECT id FROM work_sessions WHERE id=? AND job_id=?');$q->execute([$sessionId,$jobId]);if(!$q->fetchColumn())wr_fail('Choose a valid activity.');}else $sessionId=null;
try{$result=wr_accept_uploads($pdo,$jobId,$taskId,$sessionId,$_FILES['receipts']??[]);$started=wr_start_worker($jobId);header('Location: ../../admin/work/materials.php?id='.$jobId.'&receipts_queued='.$result['queued'].'&receipt_duplicates='.$result['duplicates'].'&receipt_worker='.($started?'1':'0').'#receipts');exit;}catch(Throwable $e){wr_fail('Receipts could not be queued: '.$e->getMessage(),500);}
