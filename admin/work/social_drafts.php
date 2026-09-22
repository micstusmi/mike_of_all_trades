<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../includes/work_tracker.php';

$id = (int)($_GET['id'] ?? 0);
$job = wt_job($pdo, $id);
$token = (string)($job['public_token'] ?? '');

$platforms = [
    'instagram' => [
        'name' => 'Instagram',
        'help' => 'Higher quality portrait/square style images. Best for carousel posts, stories, and polished progress updates.',
    ],
    'tiktok' => [
        'name' => 'TikTok',
        'help' => 'Portrait-first medium-quality images. Best for slideshow/reel style job journeys and advice-seeking updates.',
    ],
    'facebook' => [
        'name' => 'Facebook',
        'help' => 'Mixed orientation medium-quality images. Best for local explanation, homeowner trust, and longer captions.',
    ],
];

$draftsByPlatform = [];
$draftsByPhotoPlatform = [];
try {
    $draftStmt = $pdo->prepare("SELECT * FROM work_social_drafts WHERE job_id=? ORDER BY created_at DESC,id DESC LIMIT 80");
    $draftStmt->execute([$id]);
    foreach ($draftStmt->fetchAll(PDO::FETCH_ASSOC) as $draft) {
        $platform = (string)($draft['platform'] ?? 'general');
        $draftsByPlatform[$platform][] = $draft;
        preg_match_all('/\d+/', (string)($draft['selected_photo_ids'] ?? ''), $matches);
        foreach ($matches[0] ?? [] as $photoIdText) {
            $photoId = (int)$photoIdText;
            if ($photoId > 0 && empty($draftsByPhotoPlatform[$platform][$photoId])) {
                $draftsByPhotoPlatform[$platform][$photoId] = $draft;
            }
        }
    }
} catch (Throwable $e) {
    $draftsByPlatform = [];
    $draftsByPhotoPlatform = [];
}

$photoStmt = $pdo->prepare("
    SELECT p.*,t.title AS task_title
    FROM work_task_photos p
    LEFT JOIN work_tasks t ON t.id=p.task_id
    WHERE p.job_id=? AND p.file_deleted_at IS NULL
    ORDER BY COALESCE(p.photo_taken_at,p.created_at) DESC,p.id DESC
    LIMIT 72
");
$photoStmt->execute([$id]);
$photos = $photoStmt->fetchAll(PDO::FETCH_ASSOC);

function sd_e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function sd_photo_url(array $photo, string $token, string $platform): string
{
    $photoId = (int)$photo['id'];
    $base = '../../work/task_photo.php?id=' . $photoId . '&t=' . urlencode($token);

    if (!empty($photo[$platform . '_relative_path'])) {
        return $base . '&variant=' . rawurlencode($platform);
    }

    if (!empty($photo['social_relative_path'])) {
        return $base . '&variant=social';
    }

    return $base;
}

function sd_photo_file_available(array $photo, string $field, ?string $deletedField = null): bool
{
    if ($deletedField !== null && !empty($photo[$deletedField])) {
        return false;
    }
    $relative = trim((string)($photo[$field] ?? ''));
    return $relative !== '' && is_file(wt_task_photo_path($relative));
}

function sd_admin_photo_preview(array $photo, string $platform): array
{
    $photoId = (int)$photo['id'];
    $base = 'task_photo_admin_view.php?id=' . $photoId;

    if (sd_photo_file_available($photo, $platform . '_relative_path')) {
        $version = substr((string)($photo[$platform . '_sha256'] ?? $photo[$platform . '_relative_path']), 0, 12);
        return ['url' => $base . '&variant=' . rawurlencode($platform) . '&v=' . rawurlencode($version), 'kind' => 'platform'];
    }

    if (sd_photo_file_available($photo, 'social_relative_path', 'social_deleted_at')) {
        $version = substr((string)($photo['social_sha256'] ?? $photo['social_relative_path']), 0, 12);
        return ['url' => $base . '&variant=social&v=' . rawurlencode($version), 'kind' => 'branded-fallback'];
    }

    if (sd_photo_file_available($photo, 'relative_path', 'file_deleted_at')) {
        $version = substr((string)($photo['sha256'] ?? $photo['relative_path'] ?? $photoId), 0, 12);
        return ['url' => $base . '&v=' . rawurlencode($version), 'kind' => 'original-fallback'];
    }

    return ['url' => '', 'kind' => 'missing'];
}

function sd_suburb_from_address(string $address): string
{
    if (preg_match("/\\b(?:Street|St|Road|Rd|Avenue|Ave|Boulevard|Blvd|Drive|Dr|Court|Ct|Crescent|Cres|Lane|Ln|Place|Pl|Way|Highway|Hwy)\\s+([A-Za-z][A-Za-z\\s'-]{2,})\\s+\\d{4}\\b/i", $address, $match)) {
        return trim($match[1]);
    }
    if (preg_match("/\\b([A-Za-z][A-Za-z'-]*(?:\\s+[A-Za-z][A-Za-z'-]*)?)\\s+\\d{4}\\b/", $address, $match)) {
        return trim($match[1]);
    }
    return '';
}

function sd_photo_card_draft(array $photo, string $platform, array $job): array
{
    $stage = wt_photo_stage_label((string)($photo['photo_type'] ?? 'progress'));
    $task = trim((string)($photo['task_title'] ?? 'this job'));
    if ($task === '') {
        $task = 'this job';
    }
    $suburb = sd_suburb_from_address((string)($job['job_address'] ?? ''));
    $where = $suburb !== '' ? ' in ' . $suburb : '';

    if ($platform === 'instagram') {
        return [
            'title' => 'The hidden stage that can make or break the finish',
            'caption' => "This is the unglamorous but crucial {$stage} stage of {$task}{$where}—where careful preparation, crisp details and patient decisions begin turning a tired problem into a satisfying result. The dramatic reveal gets the attention, but this is the exact moment lasting quality is created. Would you have guessed how much happens before the final photo?",
            'short_caption' => 'The transformation starts long before the glamorous reveal.',
            'hashtags' => '#MikeOfAllTrades #HomeMaintenance #BeforeAfter #MelbourneHomes #HandymanVictoria',
        ];
    }

    if ($platform === 'tiktok') {
        return [
            'title' => 'Wait until you see what this awkward stage becomes',
            'caption' => "It may look rough right now, but this {$stage} moment in {$task}{$where} is where the tension—and the real craftsmanship—lives. Every careful choice now is pushing this stubborn job closer to a clean, satisfying transformation. What would you tackle first?",
            'short_caption' => 'Messy middle. Careful choices. Satisfying result loading.',
            'hashtags' => '#tradielife #handyman #homerepair #beforeafter #propertymaintenance',
        ];
    }

    return [
        'title' => 'Why this “small” repair deserves a closer look',
        'caption' => "This deceptively important {$stage} stage of {$task}{$where} shows what homeowners rarely get to see: thoughtful preparation, practical judgement and patient problem-solving before the polished result arrives. It is incredibly satisfying watching an awkward problem become neat, dependable and cared for. Which part of the process surprises you most?",
        'short_caption' => 'A stubborn problem becoming a result worth feeling proud of.',
        'hashtags' => '#MikeOfAllTrades #LocalHandyman #HomeRepairs #PropertyMaintenance',
    ];
}

?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Social drafts - <?=sd_e((string)$job['customer_name'])?></title>
<link rel="icon" type="image/png" href="../../assets/favicon.png?v=20260916-logo">
<style>
body{font-family:system-ui,-apple-system,sans-serif;background:#f4f6f8;color:#17202a;margin:0}
.wrap{max-width:1240px;margin:auto;padding:18px}
.top{display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap}
.card{background:#fff;border:1px solid #dfe5e9;border-radius:8px;padding:16px;margin:14px 0;box-shadow:0 2px 10px #0001}
.platform-card{border-top:5px solid #17202a}
.platform-head{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap}
.page-tools{position:sticky;top:0;z-index:20;display:flex;gap:8px;flex-wrap:wrap;align-items:center;background:#f4f6f8ee;padding:9px 0;border-bottom:1px solid #d7e0e6;backdrop-filter:blur(5px)}
.btn{background:#17202a;color:#fff;border:0;border-radius:8px;padding:11px 14px;font-weight:850;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:8px}
.btn.secondary{background:#fff;color:#17202a;border:1px solid #ccd6dd}
.muted{color:#66717c;font-size:13px}
.photo-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:12px}
.photo{border:1px solid #d8e0e6;border-radius:8px;padding:8px;background:#fbfcfd}
.photo-preview{position:relative;overflow:hidden;border-radius:7px;background:#eef2f5}
.photo-preview img{width:100%;height:auto;display:block}
.photo-preview.fallback-preview img{object-position:center center}
.photo-preview.fallback-preview:before{content:"";position:absolute;left:0;right:0;top:0;height:42px;background:rgba(0,0,0,.58);z-index:1}
.photo-preview.fallback-preview:after{content:attr(data-stage);position:absolute;left:50%;top:8px;transform:translateX(-50%);z-index:2;color:#fff;font-weight:900;font-size:15px;letter-spacing:0;white-space:nowrap;text-shadow:0 1px 1px #000}
.photo-preview.missing-preview{display:grid;place-items:center;padding:16px;box-sizing:border-box;text-align:center;color:#59656f;font-weight:800}
.fallback-note{margin-top:7px;padding:7px;border-radius:6px;background:#fff3cd;color:#684f00;font-size:12px;font-weight:750}
.actions{display:flex;gap:6px;flex-wrap:wrap;margin-top:7px}
.actions a,.actions button{border:1px solid #ccd;background:#fff;border-radius:7px;padding:6px 8px;color:#17202a;text-decoration:none;font:inherit;font-size:12px;cursor:pointer}
.posted-line{margin-top:8px;display:flex;gap:8px;align-items:center;font-weight:800}
.posted-line input{width:20px;height:20px}
.suggested{margin-top:10px;border-top:1px solid #e1e7ec;padding-top:9px}
.suggested summary{cursor:pointer;font-weight:850}
.suggested h4{margin:8px 0 5px;font-size:14px}
.suggested textarea{min-height:92px;font-size:13px;background:#fff}
.suggested pre{white-space:pre-wrap;background:#fff;border:1px solid #e1e7ec;border-radius:7px;padding:8px;font:inherit;font-size:12px;margin:6px 0}
.copy-draft{margin-top:6px;border:1px solid #ccd;background:#fff;border-radius:7px;padding:6px 8px;color:#17202a;font:inherit;font-size:12px;cursor:pointer}
textarea{width:100%;box-sizing:border-box;min-height:120px;border:1px solid #ccd6dd;border-radius:8px;padding:10px;font:inherit}
.drafts{display:grid;grid-template-columns:repeat(2,1fr);gap:12px;margin:12px 0}
.draft{background:#f8fafb;border:1px solid #e1e7ec;border-radius:8px;padding:12px}
.draft h3{margin:0 0 5px}.draft pre{white-space:pre-wrap;background:#fff;border:1px solid #e1e7ec;border-radius:8px;padding:10px;font:inherit}
@media(max-width:950px){.photo-grid{grid-template-columns:repeat(2,1fr)}.drafts{grid-template-columns:1fr}}
@media(max-width:560px){.photo-grid{grid-template-columns:1fr}.wrap{padding:12px}}
</style>
</head>
<body><?php $adminPageTitle='Social Drafts';$adminBreadcrumbs=['Work Tracker'=>'index.php','Social drafts'=>''];$adminJob=$job??null;require __DIR__.'/../../includes/admin_nav.php';?>
<div class="wrap">
<div class="top">
<div>
<p><a href="manage_job.php?id=<?=$id?>">&larr; Manage job</a> · <a href="task_photos.php?id=<?=$id?>">Task photos</a></p>
<h1>Social drafts</h1>
<p class="muted"><?=sd_e((string)$job['customer_name'])?> · <?=sd_e((string)($job['job_address'] ?? ''))?></p>
<p><a class="btn" href="social_video_builder.php?id=<?=$id?>">Slideshows / reels / shorts</a></p>
</div>
</div>
<div class="page-tools" id="pageTop"><strong>Page navigation</strong><button type="button" class="btn secondary" id="collapseAllPhotos">Collapse all images</button><button type="button" class="btn secondary" id="expandAllPhotos">Expand all images</button><a class="btn secondary" href="#pageBottom">↓ Bottom</a></div>

<?php foreach ($platforms as $platform => $meta): ?>
<section class="card platform-card" id="<?=$platform?>">
<div class="platform-head">
<div>
<h2><?=sd_e($meta['name'])?></h2>
<p class="muted"><?=sd_e($meta['help'])?></p>
</div>
<div class="actions"><button type="button" class="btn secondary toggle-platform-images" data-platform="<?=sd_e($platform)?>">Collapse images</button><button class="btn generate-platform" data-platform="<?=sd_e($platform)?>">Generate <?=sd_e($meta['name'])?> AI draft</button></div>
</div>
<div class="muted platform-status" data-platform-status="<?=sd_e($platform)?>"></div>
<div class="drafts" data-new-draft="<?=sd_e($platform)?>" style="display:none"></div>

<?php $drafts = $draftsByPlatform[$platform] ?? []; ?>
<?php if ($drafts): ?>
<div class="drafts">
<?php foreach (array_slice($drafts, 0, 4) as $draft): ?>
<article class="draft">
<h3><?=sd_e((string)$draft['title'])?></h3>
<div class="muted"><?=sd_e((string)$draft['created_at'])?><?=!empty($draft['posted_at']) ? ' · Posted' : ''?></div>
<textarea readonly><?=sd_e((string)$draft['caption'])?></textarea>
<?php if (!empty($draft['short_caption'])): ?><pre><?=sd_e((string)$draft['short_caption'])?></pre><?php endif; ?>
<?php if (!empty($draft['hashtags'])): ?><pre><?=sd_e((string)$draft['hashtags'])?></pre><?php endif; ?>
</article>
<?php endforeach; ?>
</div>
<?php endif; ?>

<div class="photo-grid" data-photo-grid="<?=sd_e($platform)?>">
<?php foreach ($photos as $photo): ?>
<?php
$photoId = (int)$photo['id'];
$preview = sd_admin_photo_preview($photo, $platform);
$image = (string)$preview['url'];
$previewKind = (string)$preview['kind'];
$original = 'task_photo_admin_view.php?id=' . $photoId;
$download = $image !== '' ? $image . '&download=1' : '';
$postedAt = (string)($photo[$platform . '_posted_at'] ?? '');
$platformReady = $previewKind === 'platform';
$originalReady = sd_photo_file_available($photo, 'relative_path', 'file_deleted_at');
$draft = $draftsByPhotoPlatform[$platform][$photoId] ?? sd_photo_card_draft($photo, $platform, $job);
$draftTitle = (string)($draft['title'] ?? '');
$draftCaption = (string)($draft['caption'] ?? '');
$draftShort = (string)($draft['short_caption'] ?? '');
$draftTags = (string)($draft['hashtags'] ?? '');
?>
<div class="photo <?=sd_e($platform)?>">
<div class="photo-preview <?=$platformReady ? '' : ($image !== '' ? 'fallback-preview' : 'missing-preview')?>" data-stage="<?=sd_e(wt_photo_stage_label((string)$photo['photo_type']))?>">
<?php if ($image !== ''): ?>
<img loading="lazy" src="<?=sd_e($image)?>" alt="<?=sd_e($meta['name'])?> social photo">
<?php else: ?>
Old photo record only — no source or generated preview file is available on this server.
<?php endif; ?>
</div>
<b><?=sd_e(wt_photo_stage_label((string)$photo['photo_type']))?></b>
<div class="muted"><?=sd_e((string)($photo['task_title'] ?? 'Job photo'))?></div>
<?php if ($previewKind === 'branded-fallback'): ?><div class="fallback-note">Fallback preview only: the <?=sd_e($meta['name'])?> version is unavailable. This is an older branded copy and may not have the correct platform crop.</div><?php endif; ?>
<?php if ($previewKind === 'original-fallback'): ?><div class="fallback-note">Fallback preview only: no generated <?=sd_e($meta['name'])?> image exists. This is the stored source copy without the platform treatment.</div><?php endif; ?>
<?php if ($previewKind === 'missing'): ?><div class="fallback-note">Missing old source file. A correct social variant cannot be regenerated unless the original is uploaded again.</div><?php endif; ?>
<label class="posted-line">
<input type="checkbox" class="posted-checkbox" data-photo-id="<?=$photoId?>" data-platform="<?=sd_e($platform)?>" <?=$postedAt !== '' ? 'checked' : ''?>>
Posted
</label>
<div class="muted posted-state" data-posted-state="<?=$photoId?>-<?=sd_e($platform)?>"><?=$postedAt !== '' ? 'Posted ' . sd_e($postedAt) : ''?></div>
<div class="actions">
<?php if ($originalReady): ?><a target="_blank" href="<?=sd_e($original)?>">Stored source</a><?php endif; ?>
<?php if ($image !== ''): ?>
<a target="_blank" href="<?=sd_e($image)?>"><?=$platformReady ? sd_e($meta['name']) : 'Open fallback'?></a>
<a href="<?=sd_e($download)?>">Save</a>
<button type="button" class="share-photo" data-share-url="<?=sd_e($image)?>">Share</button>
<?php endif; ?>
</div>
<details class="suggested" open>
<summary>Suggested post</summary>
<h4><?=sd_e($draftTitle)?></h4>
<textarea readonly><?=sd_e($draftCaption)?></textarea>
<?php if ($draftShort !== ''): ?><pre><?=sd_e($draftShort)?></pre><?php endif; ?>
<?php if ($draftTags !== ''): ?><pre><?=sd_e($draftTags)?></pre><?php endif; ?>
<button type="button" class="copy-draft" data-title="<?=sd_e($draftTitle)?>" data-caption="<?=sd_e($draftCaption)?>" data-short="<?=sd_e($draftShort)?>" data-tags="<?=sd_e($draftTags)?>">Copy title + caption</button>
</details>
</div>
<?php endforeach; ?>
</div>
</section>
<?php endforeach; ?>
<div class="page-tools" id="pageBottom"><a class="btn secondary" href="#pageTop">↑ Top</a><button type="button" class="btn secondary" id="collapseAllPhotosBottom">Collapse all images</button><button type="button" class="btn secondary" id="expandAllPhotosBottom">Expand all images</button></div>
</div>
<script>
const jobId = <?= (int)$id ?>;
function escapeHtml(value) {
    return String(value).replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
}
function setPhotoGrids(collapsed) {
    document.querySelectorAll('[data-photo-grid]').forEach(grid => { grid.hidden = collapsed; });
    document.querySelectorAll('.toggle-platform-images').forEach(button => { button.textContent = collapsed ? 'Expand images' : 'Collapse images'; });
}
document.getElementById('collapseAllPhotos').addEventListener('click', () => setPhotoGrids(true));
document.getElementById('expandAllPhotos').addEventListener('click', () => setPhotoGrids(false));
document.getElementById('collapseAllPhotosBottom').addEventListener('click', () => setPhotoGrids(true));
document.getElementById('expandAllPhotosBottom').addEventListener('click', () => setPhotoGrids(false));
document.querySelectorAll('.toggle-platform-images').forEach(button => {
    button.addEventListener('click', () => {
        const grid = document.querySelector(`[data-photo-grid="${button.dataset.platform}"]`);
        if (!grid) return;
        grid.hidden = !grid.hidden;
        button.textContent = grid.hidden ? 'Expand images' : 'Collapse images';
    });
});
document.querySelectorAll('.generate-platform').forEach(button => {
    button.addEventListener('click', async function () {
        const platform = this.dataset.platform;
        const status = document.querySelector(`[data-platform-status="${platform}"]`);
        const box = document.querySelector(`[data-new-draft="${platform}"]`);
        this.disabled = true;
        this.textContent = 'Generating...';
        status.textContent = 'Asking AI for a platform-specific draft...';
        try {
            const fd = new FormData();
            fd.append('job_id', String(jobId));
            fd.append('platform', platform);
            const response = await fetch('../../api/work/generate_social_draft.php', {method:'POST', body:fd, credentials:'same-origin'});
            const data = await response.json();
            if (!response.ok || !data.ok) throw new Error(data.error || 'AI draft failed.');
            const draft = data.draft;
            box.style.display = '';
            box.insertAdjacentHTML('afterbegin', `
                <article class="draft">
                    <h3>${escapeHtml(draft.title)}</h3>
                    <div class="muted">New ${escapeHtml(platform)} draft saved just now.</div>
                    <textarea readonly>${escapeHtml(draft.caption)}</textarea>
                    <pre>${escapeHtml(draft.short_caption || '')}</pre>
                    <pre>${escapeHtml(draft.hashtags || '')}</pre>
                </article>
            `);
            status.textContent = 'Draft saved. Copy/paste when ready, then tick posted beside the photos you used.';
        } catch (error) {
            status.textContent = 'Could not generate draft: ' + error.message;
        } finally {
            this.disabled = false;
            this.textContent = 'Generate ' + this.closest('.platform-card').querySelector('h2').textContent + ' AI draft';
        }
    });
});
document.querySelectorAll('.posted-checkbox').forEach(input => {
    input.addEventListener('change', async function () {
        const state = document.querySelector(`[data-posted-state="${this.dataset.photoId}-${this.dataset.platform}"]`);
        const fd = new FormData();
        fd.append('photo_id', this.dataset.photoId);
        fd.append('platform', this.dataset.platform);
        fd.append('posted', this.checked ? '1' : '0');
        state.textContent = 'Saving...';
        try {
            const response = await fetch('../../api/work/update_social_photo_posted.php', {method:'POST', body:fd, credentials:'same-origin'});
            const data = await response.json();
            if (!response.ok || !data.ok) throw new Error(data.error || 'Could not save posted checkbox.');
            state.textContent = this.checked ? 'Posted just now' : '';
        } catch (error) {
            this.checked = !this.checked;
            state.textContent = error.message;
        }
    });
});
document.querySelectorAll('.share-photo').forEach(function(button){
    button.addEventListener('click', async function(){
        const url = new URL(button.dataset.shareUrl, location.href).href;
        if (navigator.share) {
            try { await navigator.share({title:'Mike Of All Trades job photo', url}); return; } catch(e) {}
        }
        window.open(url, '_blank');
    });
});
document.querySelectorAll('.copy-draft').forEach(function(button){
    button.addEventListener('click', async function(){
        const text = [button.dataset.title, button.dataset.caption, button.dataset.short, button.dataset.tags].filter(Boolean).join("\n\n");
        try {
            await navigator.clipboard.writeText(text);
            button.textContent = 'Copied';
            setTimeout(() => button.textContent = 'Copy title + caption', 1400);
        } catch (e) {
            window.prompt('Copy this draft:', text);
        }
    });
});
</script>
</body>
</html>
