<?php
declare(strict_types=1);

function wt_photo_queue_ensure_schema(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS work_photo_upload_queue (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        batch_token VARCHAR(40) NOT NULL,
        client_file_key VARCHAR(80) NOT NULL,
        job_id BIGINT UNSIGNED NOT NULL,
        fallback_task_id BIGINT UNSIGNED NOT NULL,
        assignment_mode VARCHAR(20) NOT NULL DEFAULT 'auto',
        fallback_photo_type VARCHAR(20) NOT NULL DEFAULT 'progress',
        bulk_note TEXT NULL,
        client_mtime_ms BIGINT NULL,
        original_name VARCHAR(255) NOT NULL,
        staging_relative_path VARCHAR(500) NOT NULL,
        detected_mime VARCHAR(100) NOT NULL,
        original_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
        status VARCHAR(20) NOT NULL DEFAULT 'queued',
        attempts INT UNSIGNED NOT NULL DEFAULT 0,
        photo_id BIGINT UNSIGNED NULL,
        assignment_method VARCHAR(80) NULL,
        error_message TEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        started_at DATETIME NULL,
        completed_at DATETIME NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_work_photo_queue_file (batch_token,client_file_key),
        KEY idx_work_photo_queue_status (status,id),
        KEY idx_work_photo_queue_job (job_id,id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function wt_photo_queue_staging_dir(): string
{
    return wt_task_photo_base_dir() . '/upload_queue';
}

function wt_photo_queue_start_worker(): array
{
    if (!function_exists('exec')) return ['started'=>false,'message'=>'PHP exec() is disabled.'];
    $candidates = [
        PHP_BINDIR . '/php',
        '/Applications/XAMPP/xamppfiles/bin/php',
        '/usr/bin/php',
        '/usr/local/bin/php',
        '/opt/homebrew/bin/php',
    ];
    $php = null;
    foreach ($candidates as $candidate) {
        if (is_executable($candidate)) { $php = $candidate; break; }
    }
    $worker = dirname(__DIR__) . '/tools/process_photo_upload_queue.php';
    if (!$php) return ['started'=>false,'message'=>'Command-line PHP was not found.'];
    if (!is_file($worker)) return ['started'=>false,'message'=>'Photo processing worker is missing.'];
    $log = sys_get_temp_dir() . '/mot_photo_upload_worker.log';
    $command = 'nohup ' . escapeshellarg($php) . ' ' . escapeshellarg($worker)
        . ' 50 </dev/null >> ' . escapeshellarg($log) . ' 2>&1 & echo $!';
    $output=[]; $code=1; @exec($command,$output,$code);
    $pid=(int)trim((string)($output[0]??''));
    return $code===0 && $pid>0
        ? ['started'=>true,'message'=>'Background photo processing started.','pid'=>$pid]
        : ['started'=>false,'message'=>'Original saved, but the background worker could not be started automatically.'];
}

function wt_photo_queue_taken_at(string $path, string $mime, ?int $clientMs): ?DateTimeImmutable
{
    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
        $exif = @exif_read_data($path);
        $raw = $exif['DateTimeOriginal'] ?? $exif['DateTimeDigitized'] ?? $exif['DateTime'] ?? null;
        if (is_string($raw) && preg_match('/^\d{4}:\d{2}:\d{2} \d{2}:\d{2}:\d{2}$/', $raw)) {
            return DateTimeImmutable::createFromFormat('Y:m:d H:i:s', $raw, new DateTimeZone('Australia/Melbourne')) ?: null;
        }
    }
    if ($clientMs && $clientMs > 0) {
        return (new DateTimeImmutable('@' . (int)floor($clientMs / 1000)))
            ->setTimezone(new DateTimeZone('Australia/Melbourne'));
    }
    return null;
}

function wt_photo_queue_pick_assignment(?DateTimeImmutable $takenAt, array $sessions, int $fallbackTaskId, string $fallbackType): array
{
    if (!$takenAt) return [$fallbackTaskId,$fallbackType,'manual_no_timestamp'];
    $photoTs = $takenAt->setTimezone(new DateTimeZone('UTC'))->getTimestamp();
    $byTask = [];
    foreach ($sessions as $session) {
        $taskId=(int)($session['task_id']??0); if ($taskId<=0) continue;
        $start=(int)$session['start_ts']; $end=(int)($session['end_ts']?:time());
        $byTask[$taskId]['first']=min($byTask[$taskId]['first']??$start,$start);
        $byTask[$taskId]['last']=max($byTask[$taskId]['last']??$end,$end);
        if ($photoTs >= $start && $photoTs <= $end) return [$taskId,'progress','auto_timestamp_during_task_timer'];
    }
    foreach ($byTask as $taskId=>$bounds) {
        if ($photoTs >= (int)$bounds['first']-5400 && $photoTs < (int)$bounds['first']) return [(int)$taskId,'before','auto_timestamp_before_task_timer'];
    }
    foreach ($byTask as $taskId=>$bounds) {
        if ($photoTs > (int)$bounds['last'] && $photoTs <= (int)$bounds['last']+10800) return [(int)$taskId,'after','auto_timestamp_after_task_timer'];
    }
    return [$fallbackTaskId,$fallbackType,'manual_fallback_timestamp_unmatched'];
}

function wt_photo_queue_summary(PDO $pdo, int $jobId, ?string $batchToken = null): array
{
    wt_photo_queue_ensure_schema($pdo);
    $sql="SELECT status,COUNT(*) total FROM work_photo_upload_queue WHERE job_id=?";
    $params=[$jobId];
    if ($batchToken !== null && $batchToken !== '') { $sql.=" AND batch_token=?"; $params[]=$batchToken; }
    $sql.=" GROUP BY status";
    $stmt=$pdo->prepare($sql); $stmt->execute($params);
    $summary=['queued'=>0,'processing'=>0,'complete'=>0,'failed'=>0,'total'=>0];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $status=(string)$row['status']; $count=(int)$row['total'];
        if (array_key_exists($status,$summary)) $summary[$status]=$count;
        $summary['total']+=$count;
    }
    return $summary;
}
