<?php
declare(strict_types=1);

if (PHP_SAPI!=='cli') { http_response_code(403); exit("CLI only.\n"); }
require_once __DIR__.'/../includes/work_tracker.php';
require_once __DIR__.'/../includes/work_photo_queue.php';

$limit=max(1,min(100,(int)($argv[1]??50)));
wt_photo_queue_ensure_schema($pdo);
$lock=fopen(sys_get_temp_dir().'/mot_photo_upload_worker.lock','c');
if (!$lock || !flock($lock,LOCK_EX|LOCK_NB)) { echo "Another photo worker is already running.\n"; exit(0); }
$pdo->exec("UPDATE work_photo_upload_queue SET status='queued',started_at=NULL,error_message='Recovered after an interrupted worker.' WHERE status='processing' AND started_at<DATE_SUB(NOW(),INTERVAL 20 MINUTE)");

for ($run=0;$run<$limit;$run++) {
    $pdo->beginTransaction();
    $q=$pdo->query("SELECT * FROM work_photo_upload_queue WHERE status='queued' ORDER BY id LIMIT 1 FOR UPDATE");
    $item=$q->fetch(PDO::FETCH_ASSOC);
    if (!$item) { $pdo->commit(); if ($run===0) echo "No queued task photos.\n"; break; }
    $queueId=(int)$item['id'];
    $pdo->prepare("UPDATE work_photo_upload_queue SET status='processing',attempts=attempts+1,started_at=NOW(),error_message=NULL WHERE id=?")->execute([$queueId]);
    $pdo->commit();
    try {
        $jobId=(int)$item['job_id']; $fallbackTaskId=(int)$item['fallback_task_id'];
        wt_job($pdo,$jobId);
        $tasks=wt_job_tasks($pdo,$jobId,false);
        $taskIds=array_map(static fn($task)=>(int)$task['id'],$tasks);
        if (!in_array($fallbackTaskId,$taskIds,true)) throw new RuntimeException('The selected fallback task no longer exists.');
        $staging=wt_photo_queue_staging_dir().'/'.basename((string)$item['staging_relative_path']);

        $photoId=(int)($item['photo_id']??0);
        if ($photoId>0) {
            $photoStmt=$pdo->prepare("SELECT * FROM work_task_photos WHERE id=? AND job_id=?");
            $photoStmt->execute([$photoId,$jobId]);
            $photo=$photoStmt->fetch(PDO::FETCH_ASSOC);
            if (!$photo) throw new RuntimeException('The saved photo record could not be found.');
            $dest=wt_task_photo_path((string)$photo['relative_path']);
            if (!is_file($dest)) throw new RuntimeException('The saved source photo could not be found.');
            $storedInfo=[
                'mime_type'=>(string)$photo['mime_type'],
                'file_size'=>(int)$photo['file_size'],
                'sha256'=>(string)($photo['sha256']??''),
                'original_file_size'=>(int)($photo['original_file_size']??$item['original_size']),
                'server_expires_at'=>(string)($photo['server_expires_at']??wt_task_photo_expiry_date(wt_task_photo_retention_months('source'))),
            ];
            wt_after_task_photo_saved($pdo,$photoId,$dest,(string)$photo['mime_type'],(string)$photo['photo_type'],(string)$photo['relative_path'],$storedInfo);
        } else {
            if (!is_file($staging)) throw new RuntimeException('The queued original photo is missing.');
            $mime=(string)$item['detected_mime'];
            $takenAt=wt_photo_queue_taken_at($staging,$mime,$item['client_mtime_ms']!==null?(int)$item['client_mtime_ms']:null);
            $sessions=[];
            if ((string)$item['assignment_mode']==='auto') {
                $ss=$pdo->prepare("SELECT task_id,UNIX_TIMESTAMP(started_at) start_ts,UNIX_TIMESTAMP(COALESCE(ended_at,UTC_TIMESTAMP())) end_ts FROM work_sessions WHERE job_id=? AND task_id IS NOT NULL ORDER BY started_at,id");
                $ss->execute([$jobId]); $sessions=$ss->fetchAll(PDO::FETCH_ASSOC);
            }
            [$taskId,$photoType,$method]=(string)$item['assignment_mode']==='auto'
                ? wt_photo_queue_pick_assignment($takenAt,$sessions,$fallbackTaskId,(string)$item['fallback_photo_type'])
                : [$fallbackTaskId,(string)$item['fallback_photo_type'],
                    (string)$item['assignment_mode']==='general'?'general_all_tasks_bulk_async':'manual_bulk_async'];
            if (!in_array((int)$taskId,$taskIds,true)) { $taskId=$fallbackTaskId; $photoType=(string)$item['fallback_photo_type']; $method='manual_fallback_invalid_auto_task'; }
            $dir=wt_task_photo_base_dir().'/job_'.$jobId.'/task_'.(int)$taskId;
            if (!is_dir($dir) && !mkdir($dir,0770,true) && !is_dir($dir)) throw new RuntimeException('Could not create the final photo directory.');
            $safeOriginal=preg_replace('/[^A-Za-z0-9._-]+/','_',basename((string)$item['original_name']))?:'photo';
            $stored=$photoType.'_'.date('Ymd_His').'_'.bin2hex(random_bytes(5)).'.'.wt_task_photo_stored_extension($mime);
            $dest=$dir.'/'.$stored;
            $storedInfo=wt_store_uploaded_task_photo_file($staging,$dest,$mime,(int)$item['original_size']);
            $relative='job_'.$jobId.'/task_'.(int)$taskId.'/'.$stored;
            $takenSql=$takenAt?$takenAt->format('Y-m-d H:i:s'):null;
            $note=trim((string)($item['bulk_note']??'').($takenAt?"\nPhoto timestamp: ".$takenAt->format('D j M Y, g:i a'):''));
            $insert=$pdo->prepare("INSERT INTO work_task_photos
                (job_id,task_id,photo_type,uploader_type,original_name,stored_name,relative_path,mime_type,file_size,sha256,note,keep_permanent,photo_taken_at,assignment_method,created_at)
                VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())");
            $insert->execute([$jobId,(int)$taskId,$photoType,'mike',$safeOriginal,$stored,$relative,$storedInfo['mime_type'],$storedInfo['file_size'],$storedInfo['sha256'],$note?:null,0,$takenSql,$method]);
            $photoId=(int)$pdo->lastInsertId();
            $pdo->prepare("UPDATE work_photo_upload_queue SET photo_id=?,assignment_method=? WHERE id=?")->execute([$photoId,$method,$queueId]);
            wt_after_task_photo_saved($pdo,$photoId,$dest,(string)$storedInfo['mime_type'],$photoType,$relative,$storedInfo);
        }
        @unlink($staging);
        $pdo->prepare("UPDATE work_photo_upload_queue SET status='complete',completed_at=NOW(),error_message=NULL WHERE id=?")->execute([$queueId]);
        echo "Processed queued photo #{$queueId} as task photo #{$photoId}.\n";
    } catch (Throwable $e) {
        $attempts=(int)$pdo->query("SELECT attempts FROM work_photo_upload_queue WHERE id=".$queueId)->fetchColumn();
        $status=$attempts<2?'queued':'failed';
        $pdo->prepare("UPDATE work_photo_upload_queue SET status=?,error_message=?,completed_at=".($status==='failed'?'NOW()':'NULL')." WHERE id=?")
            ->execute([$status,mb_substr($e->getMessage(),0,4000),$queueId]);
        fwrite(STDERR,"Queued photo #{$queueId} attempt {$attempts} failed: {$e->getMessage()}\n");
    }
}
flock($lock,LOCK_UN); fclose($lock);
