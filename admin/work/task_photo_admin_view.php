<?php
session_start();
require_once __DIR__.'/../../includes/work_tracker.php';
$role=$_SESSION['user_role']??$_SESSION['role']??null;
if($role!=='admin'){http_response_code(403);exit;}
$id=(int)($_GET['id']??0);if($id<=0){http_response_code(400);exit;}
$q=$pdo->prepare("SELECT * FROM work_task_photos WHERE id=? LIMIT 1");$q->execute([$id]);$p=$q->fetch(PDO::FETCH_ASSOC);
if(!$p){http_response_code(404);exit;}
$base=wt_env('WORKTRACKER_PRIVATE_UPLOAD_DIR',dirname(__DIR__,2).'/storage/private/job_intake');
$file=dirname(rtrim($base,'/')).'/task_photos/'.ltrim((string)$p['relative_path'],'/');
if(!is_file($file)){http_response_code(404);exit;}
header('Content-Type: '.($p['mime_type']?:'application/octet-stream'));
header('Content-Length: '.filesize($file));
header('Cache-Control: private, max-age=300');
readfile($file);