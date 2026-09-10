<?php
require_once __DIR__ . '/_admin_auth.php';
require_once __DIR__ . '/../../includes/work_tracker.php';
$id=(int)($_POST['job_id']??0); wt_job($pdo,$id);
$allowed=['website','ai_website','website_booking','phone','sms','whatsapp','messenger','signal','airtasker','email','friend_family','word_of_mouth','repeat_customer','other'];
$source=trim($_POST['job_source']??''); if($source!==''&&!in_array($source,$allowed,true))$source='other';
$detail=trim($_POST['job_source_detail']??''); $notes=trim($_POST['original_contact_notes']??'');
$q=$pdo->prepare("UPDATE work_jobs SET job_source=?,job_source_detail=?,original_contact_notes=? WHERE id=?");
$q->execute([$source?:null,$detail?:null,$notes?:null,$id]);
header("Location: ../../admin/work/manage_job.php?id=$id&source_saved=1"); exit;
