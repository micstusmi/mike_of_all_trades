<?php
declare(strict_types=1);
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../includes/work_tracker.php';
$jobId=(int)($_GET['job_id']??0);$fileId=(int)($_GET['file_id']??0);wt_job($pdo,$jobId);
$q=$pdo->prepare('SELECT * FROM work_task_update_files WHERE id=? AND job_id=? LIMIT 1');$q->execute([$fileId,$jobId]);$file=$q->fetch(PDO::FETCH_ASSOC);
if(!$file){http_response_code(404);exit('File not found.');}
$path=dirname(__DIR__,2).'/'.ltrim((string)$file['relative_path'],'/');
if(!is_file($path)){http_response_code(404);exit('Stored file is missing.');}
$downloadName=preg_replace('/[^A-Za-z0-9._-]+/','_',basename((string)$file['original_name']))?:'task-update-file';
header('Content-Type: '.(string)$file['mime_type']);header('Content-Length: '.filesize($path));header('Content-Disposition: inline; filename="'.$downloadName.'"');header('Cache-Control: private, max-age=300');readfile($path);
