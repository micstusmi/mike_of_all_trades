<?php
session_start();
require_once __DIR__.'/../../includes/work_tracker.php';
header('Content-Type: application/json');
$role=$_SESSION['user_role']??$_SESSION['role']??null;
if($role!=='admin'){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'Admin login required.']);exit;}
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);echo json_encode(['ok'=>false,'error'=>'POST required.']);exit;}

$jobId=(int)($_POST['job_id']??0);$taskId=(int)($_POST['task_id']??0);
$type=(string)($_POST['photo_type']??'');$note=trim((string)($_POST['note']??''));
if($jobId<=0||$taskId<=0||!in_array($type,['before','after'],true)){http_response_code(400);echo json_encode(['ok'=>false,'error'=>'Invalid task/photo type.']);exit;}

$chk=$pdo->prepare("SELECT id FROM work_tasks WHERE id=? AND job_id=? LIMIT 1");$chk->execute([$taskId,$jobId]);
if(!$chk->fetchColumn()){http_response_code(404);echo json_encode(['ok'=>false,'error'=>'Task not found.']);exit;}

$files=$_FILES['photos']??null;
if(!$files||!isset($files['name'])){http_response_code(400);echo json_encode(['ok'=>false,'error'=>'Choose at least one photo.']);exit;}
$names=is_array($files['name'])?$files['name']:[$files['name']];
$tmp=is_array($files['tmp_name'])?$files['tmp_name']:[$files['tmp_name']];
$errs=is_array($files['error'])?$files['error']:[$files['error']];
$sizes=is_array($files['size'])?$files['size']:[$files['size']];
if(count($names)>8){http_response_code(400);echo json_encode(['ok'=>false,'error'=>'Please upload no more than 8 photos at once.']);exit;}

$allowed=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
$base=wt_env('WORKTRACKER_PRIVATE_UPLOAD_DIR',dirname(__DIR__,2).'/storage/private/job_intake');
$photoBase=dirname(rtrim($base,'/')).'/task_photos';
$dir=$photoBase.'/job_'.$jobId.'/task_'.$taskId;
if(!is_dir($dir)&&!mkdir($dir,0770,true)&&!is_dir($dir)){http_response_code(500);echo json_encode(['ok'=>false,'error'=>'Private task photo folder could not be created.']);exit;}
$ht=$photoBase.'/.htaccess';if(!is_file($ht))@file_put_contents($ht,"<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");

$finfo=new finfo(FILEINFO_MIME_TYPE);
$ins=$pdo->prepare("INSERT INTO work_task_photos(job_id,task_id,photo_type,uploader_type,original_name,stored_name,relative_path,mime_type,file_size,sha256,note,keep_permanent,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,NOW())");
$count=0;
foreach($names as $i=>$original){
 if(($errs[$i]??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)continue;
 $size=(int)($sizes[$i]??0);if($size<=0||$size>12*1024*1024)continue;
 $mime=$finfo->file($tmp[$i]);if(!isset($allowed[$mime]))continue;
 $stored=$type.'_'.date('Ymd_His').'_'.bin2hex(random_bytes(5)).'.'.$allowed[$mime];
 $dest=$dir.'/'.$stored;
 if(!move_uploaded_file($tmp[$i],$dest))continue;
 $sha=hash_file('sha256',$dest);
 $relative='job_'.$jobId.'/task_'.$taskId.'/'.$stored;
 $ins->execute([$jobId,$taskId,$type,'mike',(string)$original,$stored,$relative,$mime,$size,$sha,$note,0]);
 $count++;
}
if($count<1){http_response_code(400);echo json_encode(['ok'=>false,'error'=>'No valid photo was uploaded. Use JPG, PNG or WEBP up to 12 MB each.']);exit;}
echo json_encode(['ok'=>true,'uploaded'=>$count]);