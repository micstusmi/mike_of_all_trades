<?php
declare(strict_types=1);
require_once __DIR__.'/_admin_auth.php';
require_once __DIR__.'/../../includes/work_tracker.php';
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit('POST required.');}
$jobId=(int)($_POST['job_id']??0);wt_job($pdo,$jobId);
$url=trim((string)($_POST['media_archive_url']??''));$description=mb_substr(trim((string)($_POST['media_archive_description']??'')),0,2000);$verified=trim((string)($_POST['media_archive_verified_at']??''));$visible=!empty($_POST['media_archive_customer_visible'])?1:0;
if($url!==''){
    if(strlen($url)>2048||filter_var($url,FILTER_VALIDATE_URL)===false){http_response_code(400);exit('Enter a valid Google Drive folder URL.');}
    $host=mb_strtolower((string)(parse_url($url,PHP_URL_HOST)??''));
    if(!in_array($host,['drive.google.com','docs.google.com'],true)){http_response_code(400);exit('The master archive must be a Google Drive folder link.');}
}
if($verified!==''&&!preg_match('/^\d{4}-\d{2}-\d{2}$/',$verified)){http_response_code(400);exit('Enter a valid verification date.');}
if($url===''){$description='';$verified='';$visible=0;}
$q=$pdo->prepare('UPDATE work_jobs SET media_archive_url=?,media_archive_description=?,media_archive_verified_at=?,media_archive_customer_visible=?,media_archive_updated_at=NOW() WHERE id=?');
$q->execute([$url?:null,$description?:null,$verified?:null,$visible,$jobId]);
$return=(string)($_POST['return_to']??'manage_job.php?id='.$jobId);if(!preg_match('/^[A-Za-z0-9_\/.?=&-]+$/',$return))$return='manage_job.php?id='.$jobId;
header('Location: ../../admin/work/'.$return.(str_contains($return,'?')?'&':'?').'archive_saved=1#master-archive');exit;
