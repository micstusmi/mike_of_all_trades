<?php
declare(strict_types=1);
// A public example page inside the original site shell. No tracker or user data.
$root=realpath((string)($_SERVER['DOCUMENT_ROOT']??''));$templates=[];
foreach(['header','sidebar','footer'] as $name){
    $path=$root?realpath($root.'/includes/'.$name.'.php'):false;
    if(!$root||!$path||!str_starts_with($path,$root.DIRECTORY_SEPARATOR)||!is_file($path)){http_response_code(503);exit('Existing website layout unavailable.');}
    $templates[$name]=$path;
}
$types=['slideshow'=>'Slideshow with thumbnails','feed'=>'Latest photos feed','thumbnails'=>'Thumbnail gallery','carousel'=>'Carousel with neighbouring photos'];
$descriptions=[
    'slideshow'=>'Use the arrows, select a thumbnail or press Play. Portraits can pair side by side on desktop; landscapes can stack together on mobile.',
    'feed'=>'Scroll through published work photos, with the newest at the top.',
    'thumbnails'=>'Browse the thumbnail gallery and select any photo to see it at full size.',
    'carousel'=>'See neighbouring photos at the edges, then use the arrows or swipe to move through the gallery.'
];
$h=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$url=static fn(array $options)=>'https://eztradie.com.au/gallery-embed.php?'.http_build_query(['inbox'=>'mike-of-all-trades']+$options,'','&',PHP_QUERY_RFC3986);
$first=is_string($_GET['first']??null)&&isset($types[$_GET['first']])?$_GET['first']:'slideshow';
$second=is_string($_GET['second']??null)&&isset($types[$_GET['second']])&&$_GET['second']!==$first?$_GET['second']:'';
$order=($_GET['order']??'newest')==='shuffle'?'shuffle':'newest';
$paired=($_GET['pair']??'1')==='1';$auto=($_GET['autoplay']??'0')==='1';
require_once $templates['header'];require_once $templates['sidebar'];
?>
<main class="moat-gallery-demo">
<style>
.moat-gallery-demo{min-width:0;max-width:100%}.moat-gallery-demo .mg-title{background:var(--mike-navy,#142d46);color:#fff;padding:26px;border-radius:10px;margin-bottom:25px;border-bottom:5px solid var(--mike-orange,#d87416)}.moat-gallery-demo .mg-title h1{color:inherit;margin:0 0 12px}.moat-gallery-demo .mg-title p{margin:0;max-width:75ch}.moat-gallery-demo .mg-jump{display:flex;gap:9px;flex-wrap:wrap;margin:18px 0}.moat-gallery-demo .mg-jump a{color:inherit;border:1px solid #ffffff70;padding:9px 12px;border-radius:6px;text-decoration:none}.moat-gallery-demo section{padding:24px 0;border-bottom:1px solid #d9dfe4;scroll-margin-top:90px}.moat-gallery-demo h2{font-size:1.5rem;margin:0 0 10px}.moat-gallery-demo section>p{color:#4f5b66;margin-bottom:15px}.moat-gallery-demo iframe{display:block;width:100%;max-width:100%;height:590px;border:1px solid #d9dfe4;border-radius:8px;background:#fff}.moat-gallery-demo .mg-options{display:flex;gap:14px;flex-wrap:wrap;align-items:end;background:#f3f5f7;padding:18px;border-radius:8px;margin-bottom:18px}.moat-gallery-demo label{display:flex;flex-direction:column;gap:7px}.moat-gallery-demo select,.moat-gallery-demo button{font:inherit;min-height:44px;padding:8px 11px;border:1px solid #b7c0c8;border-radius:5px;background:#fff}.moat-gallery-demo .mg-check{flex-direction:row;align-items:center;min-height:44px}.moat-gallery-demo .mg-apply{background:var(--mike-navy,#142d46);color:#fff;border:0}.moat-gallery-demo textarea{width:100%;min-height:105px;font:13px/1.5 monospace;padding:12px;border:1px solid #b7c0c8;border-radius:6px;overflow-wrap:anywhere}.moat-gallery-demo .mg-small{font-size:.9rem;color:#4f5b66}@media(max-width:600px){.moat-gallery-demo .mg-title{padding:20px}.moat-gallery-demo iframe{height:660px}.moat-gallery-demo .mg-options{display:grid;grid-template-columns:minmax(0,1fr)}.moat-gallery-demo select{width:100%}}
</style>
<header class="mg-title"><h1>Four ways to show your work</h1><p>Explore the live gallery examples below. Each shows the same published photo library in a different style, so there is no need to upload the photos again for each layout.</p><nav class="mg-jump" aria-label="Gallery examples"><?php foreach($types as $key=>$label): ?><a href="#gallery-<?= $key ?>"><?= $h($label) ?></a><?php endforeach; ?><a href="#combine-galleries">Combine two styles</a></nav></header>
<?php foreach($types as $key=>$label): ?>
<section id="gallery-<?= $key ?>"><h2><?= $h($label) ?></h2><p><?= $h($descriptions[$key]) ?></p><iframe src="<?= $h($url(['view'=>$key,'order'=>'newest','pair'=>'1'])) ?>" title="<?= $h($label.' — published work photos') ?>" width="100%" height="590" loading="lazy" referrerpolicy="no-referrer" sandbox="allow-scripts allow-same-origin"></iframe></section>
<?php endforeach; ?>
<section id="combine-galleries"><h2>Choose one or two styles for your website</h2><p>Choose the first section, then optionally add a different style beneath it. The order below is the order inside your iframe.</p>
<form method="get" action="#combine-galleries" class="mg-options">
<label>First section<select name="first"><?php foreach($types as $key=>$label): ?><option value="<?= $key ?>"<?= $key===$first?' selected':'' ?>><?= $h($label) ?></option><?php endforeach; ?></select></label>
<label>Second section<select name="second"><option value="">None — one section</option><?php foreach($types as $key=>$label): ?><option value="<?= $key ?>"<?= $key===$second?' selected':'' ?>><?= $h($label) ?></option><?php endforeach; ?></select></label>
<label>Photo order<select name="order"><option value="newest"<?= $order==='newest'?' selected':'' ?>>Newest first</option><option value="shuffle"<?= $order==='shuffle'?' selected':'' ?>>Shuffle each visit</option></select></label>
<label>Slideshow pairing<select name="pair"><option value="1"<?= $paired?' selected':'' ?>>Adapt to orientation and screen</option><option value="0"<?= !$paired?' selected':'' ?>>One photo per slide</option></select></label>
<label>Slideshow / carousel<select name="autoplay"><option value="0"<?= !$auto?' selected':'' ?>>Manual controls</option><option value="1"<?= $auto?' selected':'' ?>>Autoplay with pause control</option></select></label>
<button class="mg-apply" type="submit">Preview my combination</button></form>
<?php $custom=$url(['view'=>$first,'secondary'=>$second,'order'=>$order,'pair'=>$paired?'1':'0','autoplay'=>$auto?'1':'0']);$height=$second!==''?1120:620; ?>
<iframe src="<?= $h($custom) ?>" title="<?= $h($types[$first].($second!==''?' and '.$types[$second]:'')) ?>" width="100%" height="<?= $height ?>" style="height:<?= $height ?>px" loading="lazy" referrerpolicy="no-referrer" sandbox="allow-scripts allow-same-origin"></iframe>
<details><summary>Copy this gallery to your website</summary><p class="mg-small">Give this code to your website person. Put it in the page content area, keeping your website header, sidebar, menu and footer. Published photos are loaded when the gallery is opened or refreshed.</p><textarea readonly aria-label="Gallery iframe code"><?= $h('<iframe src="'.$custom.'" title="Our work gallery" width="100%" height="'.$height.'" loading="lazy" referrerpolicy="no-referrer" style="border:0;max-width:100%"></iframe>') ?></textarea></details>
</section>
<details><summary>Gallery help</summary><p>Only published photos are shown. Select a photo to enlarge it inside the gallery. Newest first keeps recent work at the top; shuffle changes the order on each new visit or refresh. Autoplay pauses while you interact with the gallery. Each iframe can use its own combination and settings.</p></details>
</main>
<?php require_once $templates['footer']; ?>
