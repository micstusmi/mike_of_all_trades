<?php
 declare(strict_types=1);
 $root=realpath((string)($_SERVER['DOCUMENT_ROOT']??''));$templates=[];
 foreach(['header','sidebar','footer'] as $name){
     $path=$root?realpath($root.'/includes/'.$name.'.php'):false;
     if(!$root||!$path||!str_starts_with($path,$root.DIRECTORY_SEPARATOR)||!is_file($path)){http_response_code(503);exit('Existing website layout unavailable.');}
     $templates[$name]=$path;
 }
 $h=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
 $styles=['slideshow'=>'Work photos slideshow','carousel'=>'Work photos carousel','thumbnails'=>'Work photos thumbnails','feed'=>'Latest work photos'];
 require_once $templates['header'];require_once $templates['sidebar'];
?>
<main class="moat-gallery-demo">
<style>
.moat-gallery-demo{min-width:0;max-width:100%;background:#fff;padding:clamp(14px,2vw,30px);color:#17191c}.moat-gallery-demo h1{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;font-size:clamp(28px,3vw,44px);line-height:1.15;letter-spacing:-.035em;font-weight:650;margin:0 0 28px;color:#17191c}.moat-gallery-demo .mg-gallery{margin:0 0 36px;padding:0;background:#fff}.moat-gallery-demo iframe{display:block;width:100%;max-width:100%;height:900px;border:0;background:#fff}.moat-gallery-demo .mg-gallery:last-child{margin-bottom:0}@media(max-width:600px){.moat-gallery-demo{padding:18px 8px}.moat-gallery-demo h1{margin-bottom:20px}.moat-gallery-demo .mg-gallery{margin-bottom:24px}.moat-gallery-demo iframe{height:700px}}
</style>
<h1>Latest work</h1>
<?php foreach($styles as $view=>$label): $source='https://eztradie.com.au/gallery-embed.php?'.http_build_query(['inbox'=>'mike-of-all-trades','view'=>$view,'order'=>'newest','pair'=>'1'],'','&',PHP_QUERY_RFC3986); ?>
<div class="mg-gallery"><iframe data-ez-gallery src="<?= $h($source) ?>" title="<?= $h($label) ?>" width="100%" height="900" loading="lazy" referrerpolicy="no-referrer" sandbox="allow-scripts allow-same-origin"></iframe></div>
<?php endforeach; ?>
<script>
window.addEventListener('message',function(event){
 if(event.origin!=='https://eztradie.com.au'||!event.data||event.data.type!=='ezbusiness-gallery-size'||event.data.version!==1)return;
 const height=event.data.height;if(typeof height!=='number'||!Number.isFinite(height)||height<40||height>1000000)return;
 document.querySelectorAll('.moat-gallery-demo iframe[data-ez-gallery]').forEach(function(frame){const url=new URL(frame.src);if(frame.contentWindow===event.source&&url.origin===event.origin&&url.pathname==='/gallery-embed.php')frame.style.height=Math.min(24000,Math.max(80,Math.ceil(height)+4))+'px';});
});
</script>
</main>
<?php require_once $templates['footer']; ?>
