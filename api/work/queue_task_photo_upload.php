<?php
declare(strict_types=1);

require_once __DIR__ . '/_admin_auth.php';
require_once __DIR__ . '/../../includes/work_tracker.php';
require_once __DIR__ . '/../../includes/work_photo_queue.php';

header('Content-Type: application/json; charset=utf-8');
function qpu_fail(string $message,int $status=400): never {
    http_response_code($status);
    echo json_encode(['ok'=>false,'message'=>$message]);
    exit;
}
if ($_SERVER['REQUEST_METHOD']!=='POST') qpu_fail('POST required.',405);

$jobId=(int)($_POST['job_id']??0);
$fallbackTaskId=(int)($_POST['fallback_task_id']??0);
$assignmentMode=(string)($_POST['assignment_mode']??'auto');
$fallbackType=(string)($_POST['fallback_photo_type']??'progress');
$note=trim((string)($_POST['bulk_note']??''));
$batchToken=preg_replace('/[^a-zA-Z0-9_-]/','',(string)($_POST['batch_token']??''))??'';
$clientFileKey=preg_replace('/[^a-zA-Z0-9_.-]/','',(string)($_POST['client_file_key']??''))??'';
$clientMtime=(int)($_POST['client_photo_mtime']??0);
if ($jobId<=0 || $fallbackTaskId<=0 || strlen($batchToken)<8 || strlen($clientFileKey)<1) qpu_fail('Invalid upload request.');
if (!in_array($assignmentMode,['auto','manual'],true) || !in_array($fallbackType,['before','progress','after'],true)) qpu_fail('Invalid photo assignment choice.');
wt_job($pdo,$jobId);
$taskIds=array_map(static fn($task)=>(int)$task['id'],wt_job_tasks($pdo,$jobId,false));
if (!in_array($fallbackTaskId,$taskIds,true)) qpu_fail('Choose a valid fallback task.');

wt_photo_queue_ensure_schema($pdo);
$existing=$pdo->prepare("SELECT id,status FROM work_photo_upload_queue WHERE batch_token=? AND client_file_key=? LIMIT 1");
$existing->execute([$batchToken,$clientFileKey]);
if ($row=$existing->fetch(PDO::FETCH_ASSOC)) {
    $worker=wt_photo_queue_start_worker();
    echo json_encode(['ok'=>true,'queue_id'=>(int)$row['id'],'status'=>(string)$row['status'],'duplicate'=>true,'worker_started'=>(bool)$worker['started']]);
    exit;
}

$file=$_FILES['photo']??null;
if (!$file || !isset($file['tmp_name'],$file['name'],$file['error'],$file['size'])) qpu_fail('Choose a photo.');
if ((int)$file['error']!==UPLOAD_ERR_OK) qpu_fail('The original photo did not reach the server. Upload error '.(int)$file['error'].'.');
$size=(int)$file['size'];
if ($size<=0 || $size>20*1024*1024) qpu_fail('Each photo must be between 1 byte and 20 MB.',413);
$tmp=(string)$file['tmp_name'];
if (!is_uploaded_file($tmp)) qpu_fail('The uploaded photo could not be verified.');
$originalName=basename((string)$file['name']);
$finfo=new finfo(FILEINFO_MIME_TYPE);
$mime=wt_normalise_uploaded_photo_mime((string)$finfo->file($tmp),$originalName);
$allowed=wt_allowed_task_photo_types();
if (!isset($allowed[$mime])) qpu_fail('Unsupported photo type: '.$mime.'.');

$stagingDir=wt_photo_queue_staging_dir();
if (!is_dir($stagingDir) && !mkdir($stagingDir,0770,true) && !is_dir($stagingDir)) qpu_fail('The private upload queue folder could not be created.',500);
$stagingName='queued_'.date('Ymd_His').'_'.bin2hex(random_bytes(8)).'.'.($allowed[$mime]??'bin');
$stagingPath=$stagingDir.'/'.$stagingName;
if (!move_uploaded_file($tmp,$stagingPath)) qpu_fail('The original photo could not be saved into the processing queue.',500);

try {
    $insert=$pdo->prepare("INSERT INTO work_photo_upload_queue
        (batch_token,client_file_key,job_id,fallback_task_id,assignment_mode,fallback_photo_type,bulk_note,client_mtime_ms,original_name,staging_relative_path,detected_mime,original_size,status)
        VALUES(?,?,?,?,?,?,?,?,?,?,?,?,'queued')");
    $insert->execute([$batchToken,$clientFileKey,$jobId,$fallbackTaskId,$assignmentMode,$fallbackType,$note?:null,$clientMtime?:null,$originalName,$stagingName,$mime,$size]);
    $queueId=(int)$pdo->lastInsertId();
} catch (Throwable $e) {
    @unlink($stagingPath);
    qpu_fail('The photo queue record could not be saved.',500);
}
$worker=wt_photo_queue_start_worker();
echo json_encode(['ok'=>true,'queue_id'=>$queueId,'status'=>'queued','duplicate'=>false,'worker_started'=>(bool)$worker['started'],'message'=>'Original saved. Background processing queued.']);
