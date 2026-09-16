<?php
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../includes/work_tracker.php';
require_once __DIR__ . '/../../includes/work_photo_queue.php';

$id = (int)($_GET['id'] ?? 0);
$job = wt_job($pdo, $id);
$tasks = wt_job_tasks($pdo, $id, false);

$q = $pdo->prepare("
    SELECT *
    FROM work_task_photos
    WHERE job_id=?
    ORDER BY task_id,photo_type,created_at,id
");
$q->execute([$id]);

$by = [];
foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $p) {
    $by[(int)$p['task_id']][(string)$p['photo_type']][] = $p;
}

$token = (string)($job['public_token'] ?? '');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Task photos</title>
<style>
body{font-family:system-ui;background:#f4f6f8;color:#17202a}
.wrap{max-width:1100px;margin:auto;padding:20px}
.card{background:#fff;border-radius:14px;padding:16px;margin:12px 0;box-shadow:0 2px 10px #0001}
.cols{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}
.photo{max-width:180px;max-height:140px;border-radius:8px;border:1px solid #ccd}
.muted{color:#66717c;font-size:13px}
.btn{background:#17202a;color:#fff;border:0;border-radius:9px;padding:10px 13px;font-weight:800}
input,textarea,select{width:100%;box-sizing:border-box;margin:5px 0;padding:9px}
.row{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}.notice{background:#e8f6ed;border:1px solid #b9dec4;border-radius:9px;padding:10px;margin:10px 0}.warn{background:#fff5de;border:1px solid #e4c06b;border-radius:9px;padding:10px;margin:10px 0}
.actions{display:flex;gap:6px;flex-wrap:wrap;margin-top:6px}
.actions a,.actions button{border:1px solid #ccd;background:#fff;border-radius:7px;padding:5px 7px;color:#17202a;text-decoration:none;font:inherit;font-size:12px;cursor:pointer}
.expired{min-height:120px;display:grid;place-items:center;background:#eef2f5;border-radius:8px;padding:10px;text-align:center}
.upload-progress{display:none;margin-top:14px;padding:12px;border:1px solid #9db5c8;border-radius:10px;background:#eef7ff}
.upload-progress.active{display:block}.upload-progress.safe{background:#e8f6ed;border-color:#82bf94}.upload-progress.bad{background:#fff0ef;border-color:#d69a94}
.progress-track{height:12px;background:#d9e1e7;border-radius:20px;overflow:hidden;margin:8px 0}.progress-bar{height:100%;width:0;background:#1677d2;transition:width .2s}
.upload-files{font-size:12px;max-height:150px;overflow:auto;margin-top:8px}.upload-files div{padding:2px 0}
@media(max-width:850px){.cols,.row{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="wrap">
<p><a href="manage_job.php?id=<?=$id?>">&larr; Manage job</a> · <a href="social_drafts.php?id=<?=$id?>">Social drafts</a></p>
<h1>Task photos - <?=wt_html((string)$job['customer_name'])?></h1>
<p class="muted">The website stores reduced web copies to save Lightsail space. Keep your full-size originals in iPhone Photos / Google Photos. If server copies expire later, this record keeps the original filename and job reference.</p>

<?php if (isset($_GET['bulk_uploaded'])): ?>
<div class="notice">
Bulk upload finished:
<?= (int)$_GET['bulk_uploaded'] ?> photo<?= (int)$_GET['bulk_uploaded'] === 1 ? '' : 's' ?> saved.
<?= (int)($_GET['bulk_auto'] ?? 0) ?> auto-assigned,
<?= (int)($_GET['bulk_fallback'] ?? 0) ?> used the fallback choice.
</div>
<?php endif; ?>
<?php if (!empty($_GET['photo_error'])): ?>
<div class="warn"><?=wt_html((string)$_GET['photo_error'])?></div>
<?php endif; ?>

<div class="card" id="bulk-upload">
<h2>Catch-up bulk photo upload</h2>
<p class="muted">Use this when you took photos quickly during the job but did not have time to attach them to tasks. The automatic mode reads the photo timestamp where possible and compares it with this job's task timers.</p>
<?php if (!$tasks): ?>
<div class="warn">No tasks are available yet. Add tasks on the Manage Job page first, then return here to bulk upload photos.</div>
<?php else: ?>
<form method="post" action="../../api/work/bulk_upload_task_photos.php" enctype="multipart/form-data" id="bulkPhotoForm">
<input type="hidden" name="job_id" value="<?=$id?>">
<div class="row">
<div>
<label>Assignment mode</label>
<select name="assignment_mode">
<option value="auto">Auto-sort using photo timestamp + task timers</option>
<option value="manual">Put all selected photos into the fallback choice below</option>
</select>
</div>
<div>
<label>Fallback task</label>
<select name="fallback_task_id" required>
<?php foreach ($tasks as $task): ?>
<?php if (($task['status'] ?? '') === 'cancelled') continue; ?>
<option value="<?=(int)$task['id']?>"><?=wt_html((string)$task['title'])?></option>
<?php endforeach; ?>
</select>
</div>
<div>
<label>Fallback stage</label>
<select name="fallback_photo_type">
<option value="progress">In progress</option>
<option value="before">Before</option>
<option value="after">After</option>
</select>
</div>
</div>
<label>Choose photos</label>
<input type="file" name="photos[]" accept="<?=wt_html(wt_task_photo_accept_attr())?>" multiple required>
<input name="bulk_note" placeholder="Optional note for this batch, e.g. catch-up upload from Tuesday">
<p class="muted">Choose up to 40 photos. Originals are uploaded individually so one large batch cannot exceed the combined server limit. Once every original is saved, you may leave while Lightsail converts, sorts and creates the social copies in the background.</p>
<button class="btn" id="bulkUploadButton">UPLOAD ORIGINALS</button>
<div id="bulkUploadProgress" class="upload-progress" aria-live="polite">
<strong id="bulkUploadHeading">Preparing upload…</strong>
<div class="progress-track"><div class="progress-bar" id="bulkUploadBar"></div></div>
<div id="bulkUploadMessage" class="muted"></div>
<div id="bulkUploadFiles" class="upload-files"></div>
</div>
</form>
<?php endif; ?>
</div>

<?php if (!$tasks): ?>
<div class="card"><p class="muted">No task photo sections to show yet.</p></div>
<?php endif; ?>

<?php foreach ($tasks as $task): ?>
<div class="card" id="task-<?=(int)$task['id']?>">
<h2><?=wt_html((string)$task['title'])?></h2>
<div class="cols">
<?php foreach (['before' => 'Before', 'progress' => 'In progress', 'after' => 'After'] as $type => $label): ?>
<div>
<h3><?=$label?></h3>
<?php foreach (($by[(int)$task['id']][$type] ?? []) as $p): ?>
<?php
$photoId = (int)$p['id'];
$originalAvailable = empty($p['file_deleted_at'])
    && !empty($p['relative_path'])
    && is_file(wt_task_photo_path((string)$p['relative_path']));
$socialReady = !empty($p['social_relative_path'])
    && empty($p['social_deleted_at'])
    && is_file(wt_task_photo_path((string)$p['social_relative_path']));
$photoUrl = '../../work/task_photo.php?id=' . $photoId . '&t=' . urlencode($token);
$socialUrl = $photoUrl . '&variant=social';
$thumbnailReady = !empty($p['thumbnail_relative_path']) && is_file(wt_task_photo_path((string)$p['thumbnail_relative_path']));
$thumbnailUrl = $photoUrl . '&variant=thumbnail';
?>
<div style="margin:8px 0">
<?php if ($originalAvailable || $socialReady): ?>
<img class="photo" loading="lazy" src="<?=wt_html($thumbnailReady ? $thumbnailUrl : ($socialReady ? $socialUrl : $photoUrl))?>">
<?php if (!$originalAvailable && $socialReady): ?><div class="warn">Fallback preview only — the old source file is missing.</div><?php endif; ?>
<?php else: ?>
<div class="expired muted">Old photo record only — source and preview files are missing<br><?=wt_html((string)($p['original_name'] ?? 'photo'))?></div>
<?php endif; ?>
<div class="muted">Uploaded by <?=wt_html((string)$p['uploader_type'])?> · <?=wt_html(date('j M Y, g:i a', strtotime((string)$p['created_at'])))?></div>
<div class="muted">Photo #<?=$photoId?> · <?=wt_html((string)($p['original_name'] ?? 'photo'))?></div>
<?php if (!empty($p['note'])): ?><div><?=wt_html((string)$p['note'])?></div><?php endif; ?>
<div class="actions">
<?php if ($originalAvailable): ?><a target="_blank" href="<?=wt_html($photoUrl)?>">Original</a><a href="<?=wt_html($photoUrl . '&download=1')?>">Download source</a><button type="button" class="share-photo" data-share-url="<?=wt_html($photoUrl)?>" data-download-url="<?=wt_html($photoUrl.'&download=1')?>" data-filename="<?=wt_html((string)($p['original_name']??'job-photo.jpg'))?>">Save to iPhone</button><?php endif; ?>
<?php if ($socialReady): ?>
<a target="_blank" href="<?=wt_html($socialUrl)?>">Branded</a>
<a href="<?=wt_html($socialUrl . '&download=1')?>">Save</a>
<button type="button" class="share-photo" data-share-url="<?=wt_html($socialUrl)?>" data-download-url="<?=wt_html($socialUrl.'&download=1')?>" data-filename="social-photo.jpg">Share image</button>
<?php endif; ?>
</div>
</div>
<?php endforeach; ?>
<form method="post" action="../../api/work/upload_task_photo_admin.php" enctype="multipart/form-data">
<input type="hidden" name="job_id" value="<?=$id?>">
<input type="hidden" name="task_id" value="<?=(int)$task['id']?>">
<input type="hidden" name="photo_type" value="<?=$type?>">
<input type="file" name="photos[]" accept="<?=wt_html(wt_task_photo_accept_attr())?>" multiple required>
<input name="note" placeholder="Optional note">
<button class="btn">ADD <?=strtoupper($label)?> PHOTO</button>
</form>
</div>
<?php endforeach; ?>
</div>
</div>
<?php endforeach; ?>
</div>
<script>
document.querySelectorAll('.share-photo').forEach(function(button){
    button.addEventListener('click', async function(){
        const url = new URL(button.dataset.shareUrl, location.href).href;
        try {
            const response=await fetch(url,{credentials:'same-origin'}); const blob=await response.blob();
            const file=new File([blob],button.dataset.filename||'job-photo.jpg',{type:blob.type||'image/jpeg'});
            if(navigator.share&&navigator.canShare&&navigator.canShare({files:[file]})){await navigator.share({title:'Mike Of All Trades job photo',files:[file]});return;}
        } catch(e) { if(e && e.name==='AbortError') return; }
        location.href=new URL(button.dataset.downloadUrl||button.dataset.shareUrl,location.href).href;
    });
});
const bulkForm=document.getElementById('bulkPhotoForm');
const progressBox=document.getElementById('bulkUploadProgress');
const progressHeading=document.getElementById('bulkUploadHeading');
const progressMessage=document.getElementById('bulkUploadMessage');
const progressBar=document.getElementById('bulkUploadBar');
const progressFiles=document.getElementById('bulkUploadFiles');
const uploadButton=document.getElementById('bulkUploadButton');
const jobId=<?=json_encode($id)?>;
const storageKey='mot-photo-upload-batch-'+jobId;
let originalsTransferring=false;
let transferFailures=[];

function batchToken(){
    if(window.crypto&&crypto.getRandomValues){const b=new Uint8Array(16);crypto.getRandomValues(b);return Array.from(b,x=>x.toString(16).padStart(2,'0')).join('');}
    return Date.now().toString(36)+Math.random().toString(36).slice(2);
}
function escapeHtml(value){return String(value).replace(/[&<>]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;'}[c]));}
function showProgress(kind,heading,message,percent){
    progressBox.className='upload-progress active'+(kind?' '+kind:'');
    progressHeading.textContent=heading; progressMessage.textContent=message||'';
    progressBar.style.width=Math.max(0,Math.min(100,percent||0))+'%';
}
async function uploadOne(file,index,total,token,attempt=1){
    const data=new FormData();
    data.append('photo',file,file.name); data.append('job_id',String(jobId));
    data.append('assignment_mode',bulkForm.elements.assignment_mode.value);
    data.append('fallback_task_id',bulkForm.elements.fallback_task_id.value);
    data.append('fallback_photo_type',bulkForm.elements.fallback_photo_type.value);
    data.append('bulk_note',bulkForm.elements.bulk_note.value||'');
    data.append('client_photo_mtime',String(file.lastModified||0));
    data.append('batch_token',token); data.append('client_file_key',index+'-'+file.size+'-'+(file.lastModified||0));
    try{
        const response=await fetch('../../api/work/queue_task_photo_upload.php',{method:'POST',body:data,credentials:'same-origin'});
        const result=await response.json().catch(()=>({ok:false,message:'The server returned an unreadable response.'}));
        if(!response.ok||!result.ok)throw new Error(result.message||('Upload failed with HTTP '+response.status));
        return result;
    }catch(error){
        if(attempt<3){await new Promise(resolve=>setTimeout(resolve,1000*attempt));return uploadOne(file,index,total,token,attempt+1);}
        throw error;
    }
}
async function pollBatch(token){
    try{
        const response=await fetch('../../api/work/task_photo_upload_status.php?job_id='+jobId+'&batch_token='+encodeURIComponent(token),{credentials:'same-origin',cache:'no-store'});
        const data=await response.json(); if(!data.ok)return;
        const s=data.summary; const finished=(s.complete||0)+(s.failed||0); const percent=s.total?Math.round(finished*100/s.total):0;
        const failedNames=transferFailures.concat((data.items||[]).filter(x=>x.status==='failed').map(x=>x.original_name+(x.error_message?' — '+x.error_message:'')));
        progressFiles.innerHTML=failedNames.map(x=>'<div>⚠️ '+escapeHtml(x)+'</div>').join('');
        if(s.queued||s.processing){
            showProgress('safe','Originals safely saved — you may leave this page',(s.complete||0)+' ready, '+(s.processing||0)+' processing, '+(s.queued||0)+' waiting'+(s.failed?', '+s.failed+' failed':''),percent);
            setTimeout(()=>pollBatch(token),4000);
        }else{
            localStorage.removeItem(storageKey);
            showProgress(s.failed?'bad':'safe',s.failed?'Background processing finished with a problem':'All photos are ready',(s.complete||0)+' completed'+(s.failed?', '+s.failed+' failed. The failed filenames are listed below.':''),100);
            if(!s.failed){progressMessage.insertAdjacentHTML('beforeend',' <a href="task_photos.php?id='+jobId+'">Refresh photos</a>');}
        }
    }catch(error){setTimeout(()=>pollBatch(token),6000);}
}
bulkForm?.addEventListener('submit',async function(event){
    event.preventDefault();
    const input=this.querySelector('input[type="file"][name="photos[]"]'); const files=Array.from(input?.files||[]);
    if(!files.length)return; if(files.length>40){alert('Please choose no more than 40 photos.');return;}
    const token=batchToken(); localStorage.setItem(storageKey,token); originalsTransferring=true;
    uploadButton.disabled=true; progressFiles.innerHTML=''; let saved=0; const failed=[]; transferFailures=[];
    for(let i=0;i<files.length;i++){
        showProgress('','Uploading original '+(i+1)+' of '+files.length,'Keep this tab open until every original reaches Lightsail.',Math.round(saved*100/files.length));
        try{await uploadOne(files[i],i,files.length,token);saved++;progressFiles.insertAdjacentHTML('beforeend','<div>✓ '+escapeHtml(files[i].name)+'</div>');}
        catch(error){failed.push(files[i].name+' — '+error.message);progressFiles.insertAdjacentHTML('beforeend','<div>✗ '+escapeHtml(files[i].name+' — '+error.message)+'</div>');}
        progressBar.style.width=Math.round((i+1)*100/files.length)+'%';
    }
    originalsTransferring=false; uploadButton.disabled=false; transferFailures=failed.slice();
    if(saved){showProgress('safe','Original transfer finished — you may safely leave',saved+' original'+(saved===1?'':'s')+' saved on Lightsail and processing in the background'+(failed.length?'; '+failed.length+' could not be uploaded.':'.'),100);pollBatch(token);}
    else{localStorage.removeItem(storageKey);showProgress('bad','No originals were saved','Check the errors below and try again.',100);}
});
window.addEventListener('beforeunload',function(event){if(!originalsTransferring)return;event.preventDefault();event.returnValue='Photo originals are still uploading. Leaving now will cancel the remaining transfers.';});
const previousBatch=localStorage.getItem(storageKey); if(previousBatch){showProgress('safe','Checking your background photo batch','You may continue working while Lightsail processes saved originals.',5);pollBatch(previousBatch);}
</script>
</body>
</html>
