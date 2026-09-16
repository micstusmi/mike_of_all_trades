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
$by=['before'=>[],'progress'=>[],'after'=>[]];foreach($photos as $p){if(isset($by[$p['photo_type']]))$by[$p['photo_type']][]=$p;}
function e4c($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
?>
<h4>📷 Before / after photos</h4>
<p class="wt-photo-help">Photos saved here stay attached to this individual task. Originals are kept separately. Branded copies can be opened, shared, or saved to your iPhone Photos app from the image/share screen.</p>
<div class="wt-photo-columns">
<?php foreach(['before'=>'BEFORE','progress'=>'PROGRESS / STAGE','after'=>'AFTER'] as $type=>$label):?>
<div class="wt-photo-side">
<h5><?=$type==='before'?'📷':($type==='progress'?'🛠️':'✅')?> <?=$label?> photos</h5>
<?php if(!$by[$type]):?><div class="wt-photo-empty">No <?=strtolower($label)?> photos yet.</div><?php endif;?>
<div class="wt-photo-thumbs">
<?php foreach($by[$type] as $p):?>
<div class="wt-photo-thumb">
<?php $originalAvailable=empty($p['file_deleted_at'])&&!empty($p['relative_path'])&&is_file(wt_task_photo_path((string)$p['relative_path']));$socialReady=!empty($p['social_relative_path'])&&empty($p['social_deleted_at'])&&is_file(wt_task_photo_path((string)$p['social_relative_path']));$thumbReady=!empty($p['thumbnail_relative_path'])&&is_file(wt_task_photo_path((string)$p['thumbnail_relative_path']));$socialUrl='task_photo_admin_view.php?id='.(int)$p['id'].'&variant=social';$sourceUrl='task_photo_admin_view.php?id='.(int)$p['id'];$thumbUrl=$sourceUrl.'&variant=thumbnail';?>
<?php if($originalAvailable||$socialReady):?>
<a target="_blank" href="<?=e4c($socialReady?$socialUrl:$sourceUrl)?>"><img loading="lazy" src="<?=e4c($thumbReady?$thumbUrl:($socialReady?$socialUrl:$sourceUrl))?>" alt="<?=e4c($label)?> photo"></a>
<?php if(!$originalAvailable&&$socialReady):?><div class="wt-photo-empty">Fallback preview only — old source file missing.</div><?php endif;?>
<?php else:?>
<div class="wt-photo-empty">Old photo record only — source and preview files are missing. Original name: <?=e4c($p['original_name']??'photo')?></div>
<?php endif;?>
<small>Photo #<?=(int)$p['id']?> · <?=e4c($p['original_name']??'photo')?></small>
<?php if(trim((string)($p['note']??''))!==''):?><small title="<?=e4c($p['note'])?>"><?=e4c($p['note'])?></small><?php endif;?>
<div class="wt-photo-actions">
<?php if($originalAvailable):?><a target="_blank" href="<?=e4c($sourceUrl)?>">Original</a><a href="<?=e4c($sourceUrl.'&download=1')?>">Download source</a><button type="button" class="wt-photo-share" data-share-url="<?=e4c($sourceUrl)?>" data-download-url="<?=e4c($sourceUrl.'&download=1')?>" data-filename="<?=e4c($p['original_name']??'job-photo.jpg')?>">Save to iPhone</button><?php endif;?>
<?php if($socialReady):?>
<a target="_blank" href="<?=e4c($socialUrl)?>">Branded</a>
<a href="<?=e4c($socialUrl.'&download=1')?>">Save</a>
<button type="button" class="wt-photo-share" data-share-url="<?=e4c($socialUrl)?>" data-download-url="<?=e4c($socialUrl.'&download=1')?>" data-filename="social-photo.jpg">Share image</button>
<?php endif;?>
</div>
</div>
<?php endforeach;?>
</div>
<form class="wt-inline-photo-form" method="post" enctype="multipart/form-data" action="../../api/work/upload_task_photo_inline_admin.php">
<input type="hidden" name="job_id" value="<?=$jobId?>">
<input type="hidden" name="task_id" value="<?=$taskId?>">
<input type="hidden" name="photo_type" value="<?=$type?>">
<div><input type="file" name="photos[]" accept="<?=wt_html(wt_task_photo_accept_attr())?>" multiple required></div>
<input type="text" name="note" placeholder="Optional note">
<button class="wt-photo-btn <?=$type==='before'?'wt-photo-before':($type==='progress'?'':'wt-photo-after')?>" type="submit">+ ADD <?=$label?></button>
</form>
</div>
<?php endforeach;?>
</div>
<script>
document.querySelectorAll('.wt-photo-share').forEach(function (button) {
    if (button.dataset.bound === '1') return;
    button.dataset.bound = '1';
    button.addEventListener('click', async function () {
        const url = new URL(button.dataset.shareUrl, window.location.href).href;
        try {
            const response=await fetch(url,{credentials:'same-origin'}); const blob=await response.blob();
            const file=new File([blob],button.dataset.filename||'job-photo.jpg',{type:blob.type||'image/jpeg'});
            if(navigator.share&&navigator.canShare&&navigator.canShare({files:[file]})){await navigator.share({title:'Mike Of All Trades job photo',files:[file]});return;}
        } catch(e) { if(e&&e.name==='AbortError') return; }
        location.href=new URL(button.dataset.downloadUrl||button.dataset.shareUrl,window.location.href).href;
    });
});
</script>
