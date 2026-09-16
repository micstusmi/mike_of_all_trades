<?php
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../includes/work_tracker.php';

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
<p class="muted">Up to 40 photos at once, but large iPhone batches may need to be uploaded in smaller groups if the server rejects the total batch size. Photos are still reduced to storage-safe web copies and branded social copies.</p>
<button class="btn">UPLOAD AND SORT PHOTOS</button>
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
document.getElementById('bulkPhotoForm')?.addEventListener('submit', function(){
    this.querySelectorAll('.client-photo-time').forEach(input => input.remove());
    const input = this.querySelector('input[type="file"][name="photos[]"]');
    const files = Array.from(input?.files || []);
    const totalBytes = files.reduce((sum, file) => sum + (file.size || 0), 0);
    const softLimit = 35 * 1024 * 1024;
    if (totalBytes > softLimit && !confirm('This is a large photo batch. If the upload fails, try 5-10 iPhone photos at a time. Continue anyway?')) {
        return false;
    }
    files.forEach(function(file){
        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'client_photo_mtime[]';
        hidden.value = String(file.lastModified || '');
        hidden.className = 'client-photo-time';
        input.insertAdjacentElement('afterend', hidden);
    });
});
</script>
</body>
</html>
