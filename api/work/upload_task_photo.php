<?php
require_once __DIR__ . '/../../includes/work_tracker.php';

function fail_photo(string $m,int $s=400):never{http_response_code($s);echo '<!doctype html><meta charset="utf-8"><style>body{font-family:system-ui;max-width:760px;margin:40px auto;padding:20px}</style><h1>Photo upload failed</h1><p>'.htmlspecialchars($m,ENT_QUOTES,'UTF-8').'</p>';exit;}
if($_SERVER['REQUEST_METHOD']!=='POST') fail_photo('POST required.',405);
$token=(string)($_POST['token']??'');$taskId=(int)($_POST['task_id']??0);$type=(string)($_POST['photo_type']??'');$note=trim((string)($_POST['note']??''));
if(!$token||$taskId<=0||!in_array($type,['before','progress','after'],true)) fail_photo('Invalid request.');
try{$job=wt_job_by_token($pdo,$token);}catch(Throwable $e){fail_photo('Job not found.',404);}
$q=$pdo->prepare("SELECT * FROM work_tasks WHERE id=? AND job_id=? AND customer_visible=1 LIMIT 1");$q->execute([$taskId,$job['id']]);$task=$q->fetch(PDO::FETCH_ASSOC);if(!$task) fail_photo('Task not found.',404);
$f=$_FILES['photo']??null;if(!$f||($f['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK) fail_photo('Choose an image to upload.');
if((int)$f['size']<=0||(int)$f['size']>12*1024*1024) fail_photo('Photo must be 12 MB or smaller.');
$mime=wt_normalise_uploaded_photo_mime((string)(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']),(string)$f['name']);$allowed=wt_allowed_task_photo_types();if(!isset($allowed[$mime])) fail_photo('Use JPEG, PNG, WEBP, HEIC or HEIF.');
$base=wt_env('WORKTRACKER_PRIVATE_UPLOAD_DIR',dirname(__DIR__,2).'/storage/private/job_intake');$photoBase=dirname(rtrim($base,'/')).'/task_photos';$dir=$photoBase.'/job_'.$job['id'].'/task_'.$taskId;
if(!is_dir($dir)&&!mkdir($dir,0770,true)&&!is_dir($dir)) fail_photo('Could not create the private photo directory.',500);
$name=preg_replace('/[^A-Za-z0-9._-]+/','_',basename((string)$f['name']))?:'photo';
$stored=$type.'_'.date('Ymd_His').'_'.bin2hex(random_bytes(4)).'.'.wt_task_photo_stored_extension($mime);
$dest=$dir.'/'.$stored;
try{$storedInfo=wt_store_uploaded_task_photo_file((string)$f['tmp_name'],$dest,$mime,(int)$f['size']);}catch(Throwable $e){fail_photo($e->getMessage(),500);}
$rel='job_'.$job['id'].'/task_'.$taskId.'/'.$stored;
$pdo->prepare("INSERT INTO work_task_photos(job_id,task_id,photo_type,uploader_type,original_name,stored_name,relative_path,mime_type,file_size,sha256,note) VALUES(?,?,?,?,?,?,?,?,?,?,?)")
 ->execute([$job['id'],$taskId,$type,'customer',$name,$stored,$rel,$storedInfo['mime_type'],$storedInfo['file_size'],$storedInfo['sha256'],$note?:null]);
wt_after_task_photo_saved($pdo,(int)$pdo->lastInsertId(),$dest,(string)$storedInfo['mime_type'],$type,$rel,$storedInfo);
header('Location: ../../work/job.php?t='.urlencode($token).'&photo_saved=1#customer-task-'.$taskId);exit;
