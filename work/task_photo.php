<?php
require_once __DIR__ . '/../includes/work_tracker.php';
$id=(int)($_GET['id']??0);$token=(string)($_GET['t']??'');if($id<=0||$token===''){http_response_code(404);exit;}
try{$job=wt_job_by_token($pdo,$token);}catch(Throwable $e){http_response_code(404);exit;}
$q=$pdo->prepare("SELECT p.* FROM work_task_photos p JOIN work_tasks t ON t.id=p.task_id WHERE p.id=? AND p.job_id=? AND t.customer_visible=1 LIMIT 1");$q->execute([$id,$job['id']]);$p=$q->fetch(PDO::FETCH_ASSOC);if(!$p){http_response_code(404);exit;}
$variant=(string)($_GET['variant']??'original');
$relative=(string)$p['relative_path'];$mime=(string)$p['mime_type'];
if($variant==='thumbnail'&&!empty($p['thumbnail_relative_path'])){$relative=(string)$p['thumbnail_relative_path'];$mime=(string)($p['thumbnail_mime_type']?:'image/jpeg');}
if($variant==='social'&&!empty($p['social_relative_path'])){$relative=(string)$p['social_relative_path'];$mime=(string)($p['social_mime_type']?:'image/jpeg');}
if(in_array($variant,['instagram','tiktok','facebook'],true)&&!empty($p[$variant.'_relative_path'])){$relative=(string)$p[$variant.'_relative_path'];$mime=(string)($p[$variant.'_mime_type']?:'image/jpeg');}
$path=wt_task_photo_path($relative);if(!is_file($path)){http_response_code(404);exit;}
if(isset($_GET['download'])){$base=pathinfo((string)($p['original_name']??'job-photo'),PATHINFO_FILENAME);$ext=$mime==='image/png'?'png':($mime==='image/webp'?'webp':'jpg');$name=preg_replace('/[^A-Za-z0-9._-]+/','-',$base.'-'.$variant.'.'.$ext)?:('job-photo-'.$id.'.'.$ext);header('Content-Disposition: attachment; filename="'.$name.'"; filename*=UTF-8\'\''.rawurlencode($name));}
header('Content-Type: '.$mime);header('Cache-Control: private, max-age=300');header('X-Content-Type-Options: nosniff');readfile($path);
