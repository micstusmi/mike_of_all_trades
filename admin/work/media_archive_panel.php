<?php
declare(strict_types=1);
if(!isset($job)||!is_array($job))return;
$archiveJobId=(int)($job['id']??$id??0);$archiveReturn=$archiveReturn??('manage_job.php?id='.$archiveJobId);
?>
<section class="card media-archive-panel" id="master-archive" style="border:2px solid #b8c9d8">
<h2 style="margin-top:0">Master photo/video archive</h2>
<p><b>Complete original archive:</b> save the permanent Google Drive folder containing every original photo and video for this job. This is separate from the reduced, website-optimised copies stored on Lightsail.</p>
<?php if(isset($_GET['archive_saved'])):?><div style="padding:10px;border-radius:8px;background:#e8f6ed;margin:10px 0"><b>Archive link saved.</b></div><?php endif;?>
<?php if(!empty($job['media_archive_url'])):?><p><a class="btn" href="<?=wt_html((string)$job['media_archive_url'])?>" target="_blank" rel="noopener">OPEN GOOGLE DRIVE FOLDER</a> <button class="btn secondary" type="button" data-copy-archive="<?=wt_html((string)$job['media_archive_url'])?>">COPY LINK</button></p><?php endif;?>
<form method="post" action="../../api/work/save_media_archive.php">
<input type="hidden" name="job_id" value="<?=$archiveJobId?>"><input type="hidden" name="return_to" value="<?=wt_html($archiveReturn)?>">
<label>Google Drive folder link</label><input type="url" name="media_archive_url" value="<?=wt_html((string)($job['media_archive_url']??''))?>" placeholder="https://drive.google.com/drive/folders/…" style="width:100%">
<label style="margin-top:9px">Folder description (optional)</label><textarea name="media_archive_description" rows="2" placeholder="Complete originals, site videos, evidence and marketing source files"><?=wt_html((string)($job['media_archive_description']??''))?></textarea>
<div class="row" style="margin-top:9px"><div><label>Date last verified</label><input type="date" name="media_archive_verified_at" value="<?=wt_html((string)($job['media_archive_verified_at']??''))?>"></div><div style="align-self:center"><label style="font-size:15px"><input type="checkbox" name="media_archive_customer_visible" value="1" style="width:auto" <?=!empty($job['media_archive_customer_visible'])?'checked':''?>> Share this archive link in the customer portal</label></div></div>
<p class="muted">Admin-only unless the customer-sharing box is deliberately selected. Clear the URL and save to remove the link.</p><button class="btn" type="submit">SAVE ARCHIVE LINK</button>
</form></section>
<script>document.querySelectorAll('[data-copy-archive]').forEach(b=>b.addEventListener('click',async()=>{try{await navigator.clipboard.writeText(b.dataset.copyArchive);const old=b.textContent;b.textContent='COPIED';setTimeout(()=>b.textContent=old,1600)}catch(e){prompt('Copy this Google Drive link:',b.dataset.copyArchive)}}));</script>
