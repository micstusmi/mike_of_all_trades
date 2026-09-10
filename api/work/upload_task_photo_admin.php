<?php
require_once __DIR__ . '/_admin_auth.php';
require_once __DIR__ . '/../../includes/work_tracker.php';
function fail_photo(string $m,int $s=400):never{http_response_code($s);die(htmlspecialchars($m,ENT_QUOTES,'UTF-8'));}
if($_SERVER['REQUEST_METHOD']!=='POST') fail_photo('POST required.',405);
$jobId=(int)($_POST['job_id']??0);$taskId=(int)($_POST['task_id']??0);$type=(string)($_POST['photo_type']??'');$note=trim((string)($_POST['note']??''));
if($jobId<=0||$taskId<=0||!in_array($type,['before','after'],true)) fail_photo('Invalid request.');
$job=wt_job($pdo,$jobId);$q=$pdo->prepare("SELECT * FROM work_tasks WHERE id=? AND job_id=? LIMIT 1");$q->execute([$taskId,$jobId]);if(!$q->fetch())fail_photo('Task not found.',404);
$f=$_FILES['photo']??null;if(!$f||($f['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)fail_photo('Choose an image.');if((int)$f['size']>12*1024*1024)fail_photo('Photo must be 12 MB or smaller.');
$mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);if(!in_array($mime,['image/jpeg','image/png','image/webp'],true))fail_photo('Use JPEG, PNG or WEBP.');
$base=wt_env('WORKTRACKER_PRIVATE_UPLOAD_DIR',dirname(__DIR__,2).'/storage/private/job_intake');$photoBase=dirname(rtrim($base,'/')).'/task_photos';$dir=$photoBase.'/job_'.$jobId.'/task_'.$taskId;if(!is_dir($dir)&&!mkdir($dir,0770,true)&&!is_dir($dir))fail_photo('Could not create photo directory.',500);
$name=preg_replace('/[^A-Za-z0-9._-]+/','_',basename((string)$f['name']))?:'photo';$stored=$type.'_'.date('Ymd_His').'_'.bin2hex(random_bytes(4)).'_'.$name;$dest=$dir.'/'.$stored;if(!move_uploaded_file($f['tmp_name'],$dest))fail_photo('Could not preserve photo.',500);
$pdo->prepare("INSERT INTO work_task_photos(job_id,task_id,photo_type,uploader_type,original_name,stored_name,relative_path,mime_type,file_size,sha256,note) VALUES(?,?,?,?,?,?,?,?,?,?,?)")
 ->execute([$jobId,$taskId,$type,'mike',$name,$stored,'job_'.$jobId.'/task_'.$taskId.'/'.$stored,$mime,(int)$f['size'],hash_file('sha256',$dest),$note?:null]);
header('Location: ../../admin/work/task_photos.php?id='.$jobId.'&saved=1#task-'.$taskId);exit;
