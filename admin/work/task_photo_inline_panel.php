<?php
session_start();
require_once __DIR__.'/../../includes/work_tracker.php';
$role=$_SESSION['user_role']??$_SESSION['role']??null;
if($role!=='admin'){http_response_code(403);exit('Admin login required.');}
$jobId=(int)($_GET['job_id']??0);$taskId=(int)($_GET['task_id']??0);
if($jobId<=0||$taskId<=0){http_response_code(400);exit('Invalid task.');}

$chk=$pdo->prepare("SELECT id,title FROM work_tasks WHERE id=? AND job_id=? LIMIT 1");
$chk->execute([$taskId,$jobId]);$task=$chk->fetch(PDO::FETCH_ASSOC);
if(!$task){http_response_code(404);exit('Task not found.');}

$q=$pdo->prepare("SELECT * FROM work_task_photos WHERE job_id=? AND task_id=? ORDER BY photo_type,created_at,id");
$q->execute([$jobId,$taskId]);$photos=$q->fetchAll(PDO::FETCH_ASSOC);
$by=['before'=>[],'after'=>[]];foreach($photos as $p){if(isset($by[$p['photo_type']]))$by[$p['photo_type']][]=$p;}
function e4c($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
?>
<h4>📷 Before / after photos</h4>
<p class="wt-photo-help">Photos saved here stay attached to this individual task. Originals are kept separately.</p>
<div class="wt-photo-columns">
<?php foreach(['before'=>'BEFORE','after'=>'AFTER'] as $type=>$label):?>
<div class="wt-photo-side">
<h5><?=$type==='before'?'📷':'✅'?> <?=$label?> photos</h5>
<?php if(!$by[$type]):?><div class="wt-photo-empty">No <?=strtolower($label)?> photos yet.</div><?php endif;?>
<div class="wt-photo-thumbs">
<?php foreach($by[$type] as $p):?>
<div class="wt-photo-thumb">
<a target="_blank" href="task_photo_admin_view.php?id=<?=(int)$p['id']?>"><img loading="lazy" src="task_photo_admin_view.php?id=<?=(int)$p['id']?>" alt="<?=e4c($label)?> photo"></a>
<?php if(trim((string)($p['note']??''))!==''):?><small title="<?=e4c($p['note'])?>"><?=e4c($p['note'])?></small><?php endif;?>
</div>
<?php endforeach;?>
</div>
<form class="wt-inline-photo-form" method="post" enctype="multipart/form-data" action="../../api/work/upload_task_photo_inline_admin.php">
<input type="hidden" name="job_id" value="<?=$jobId?>">
<input type="hidden" name="task_id" value="<?=$taskId?>">
<input type="hidden" name="photo_type" value="<?=$type?>">
<div><input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" capture="environment" multiple required></div>
<input type="text" name="note" placeholder="Optional note">
<button class="wt-photo-btn <?=$type==='before'?'wt-photo-before':'wt-photo-after'?>" type="submit">+ ADD <?=$label?></button>
</form>
</div>
<?php endforeach;?>
</div>