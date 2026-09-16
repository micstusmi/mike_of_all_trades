<?php
session_start();
require_once __DIR__.'/../../includes/work_tracker.php';
$role=$_SESSION['user_role']??$_SESSION['role']??null;
if($role!=='admin'){http_response_code(403);exit;}
$id=(int)($_GET['id']??0);if($id<=0){http_response_code(400);exit;}
$q=$pdo->prepare("SELECT * FROM work_task_photos WHERE id=? LIMIT 1");$q->execute([$id]);$p=$q->fetch(PDO::FETCH_ASSOC);
if(!$p){http_response_code(404);exit;}
$variant=(string)($_GET['variant']??'original');
$relative=(string)$p['relative_path'];
$mime=(string)($p['mime_type']?:'application/octet-stream');
if($variant==='thumbnail'&&!empty($p['thumbnail_relative_path'])){
    $relative=(string)$p['thumbnail_relative_path'];
    $mime=(string)($p['thumbnail_mime_type']?:'image/jpeg');
}
if($variant==='social'&&!empty($p['social_relative_path'])){
    $relative=(string)$p['social_relative_path'];
    $mime=(string)($p['social_mime_type']?:'image/jpeg');
}
if(in_array($variant,['instagram','tiktok','facebook'],true)&&!empty($p[$variant.'_relative_path'])){
    $relative=(string)$p[$variant.'_relative_path'];
    $mime=(string)($p[$variant.'_mime_type']?:'image/jpeg');
}
$file=wt_task_photo_path($relative);
if(!is_file($file)){http_response_code(404);exit;}
if(isset($_GET['download'])){
    $base=pathinfo((string)($p['original_name']??'job-photo'),PATHINFO_FILENAME);
    $ext=$mime==='image/png'?'png':($mime==='image/webp'?'webp':'jpg');
    $name=preg_replace('/[^A-Za-z0-9._-]+/','-',$base.'-'.$variant.'.'.$ext)?:('job-photo-'.$id.'.'.$ext);
    header('Content-Disposition: attachment; filename="'.$name.'"; filename*=UTF-8\'\''.rawurlencode($name));
}
header('Content-Type: '.$mime);
header('Content-Length: '.filesize($file));
header('Cache-Control: private, max-age=300');
header('X-Content-Type-Options: nosniff');
readfile($file);
