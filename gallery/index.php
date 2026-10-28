<?php
 declare(strict_types=1);
 $root=realpath((string)($_SERVER['DOCUMENT_ROOT']??''));$templates=[];
 foreach(['header','sidebar','footer'] as $name){
     $path=$root?realpath($root.'/includes/'.$name.'.php'):false;
     if(!$root||!$path||!str_starts_with($path,$root.DIRECTORY_SEPARATOR)||!is_file($path)){http_response_code(503);exit('Existing website layout unavailable.');}
     $templates[$name]=$path;
 }
 $h=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
 $styles=['slideshow'=>'Slideshow','carousel'=>'Carousel','thumbnails'=>'Thumbnails','feed'=>'Gallery'];
 require_once $templates['header'];require_once $templates['sidebar'];
?>
<main class="moat-gallery-demo">
<style>
.moat-gallery-demo{min-width:0;max-width:100%;background:#fff;padding:clamp(14px,2vw,30px);color:#17191c}.moat-gallery-demo h1{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;font-size:clamp(28px,3vw,44px);line-height:1.15;letter-spacing:-.035em;font-weight:650;margin:0 0 28px;color:#17191c}.moat-gallery-demo h2{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;font-size:clamp(20px,2vw,28px);line-height:1.2;font-weight:600;letter-spacing:-.02em;margin:0 0 18px;color:#17191c}.moat-gallery-demo .mg-gallery{margin:0 0 36px;padding:0;background:#fff}.moat-gallery-demo iframe{display:block;width:100%;max-width:100%;height:900px;border:0;background:#fff}.moat-gallery-demo .mg-gallery:last-child{margin-bottom:0}@media(max-width:600px){.moat-gallery-demo{padding:18px 8px}.moat-gallery-demo h1{margin-bottom:20px}.moat-gallery-demo .mg-gallery{margin-bottom:24px}.moat-gallery-demo iframe{height:700px}}
</style>
<h1>Latest work</h1>
<?php foreach($styles as $view=>$label): $source='https://eztradie.com.au/gallery-embed.php?'.http_build_query(['inbox'=>'mike-of-all-trades','view'=>$view,'order'=>'newest','pair'=>'1','autoplay'=>$view==='slideshow'?'1':'0'],'','&',PHP_QUERY_RFC3986); ?>
<section class="mg-gallery"><h2><?= $h($label) ?></h2><iframe data-ez-gallery src="<?= $h($source) ?>" title="<?= $h($label) ?>" width="100%" height="900" loading="lazy" referrerpolicy="no-referrer" sandbox="allow-scripts allow-same-origin"></iframe></section>
<?php endforeach; ?>
<script>
(function(){
  'use strict';
  if(window.ezBusinessGallerySizing)return;window.ezBusinessGallerySizing=true;
  const origin='https://eztradie.com.au';let queued=false;
  function frames(){return [...document.querySelectorAll('iframe[data-ez-gallery]')].filter(frame=>{try{const url=new URL(frame.src);return url.origin===origin&&url.pathname==='/gallery-embed.php';}catch(error){return false;}});}
  function sendViewport(frame){
    const bounds=frame.getBoundingClientRect();if(bounds.top>=window.innerHeight||bounds.bottom<=0)return;
    const height=Math.max(180,Math.min(1600,window.innerHeight-Math.max(96,bounds.top)-16));
    frame.contentWindow.postMessage({type:'ezbusiness-gallery-viewport',version:1,height},origin);
  }
  function schedule(){if(queued)return;queued=true;requestAnimationFrame(()=>{queued=false;frames().forEach(sendViewport);});}
  window.addEventListener('message',function(event){
    if(event.origin!==origin||!event.data||event.data.type!=='ezbusiness-gallery-size'||event.data.version!==1)return;
    const height=event.data.height;if(typeof height!=='number'||!Number.isFinite(height)||height<40||height>1000000)return;
    frames().forEach(function(frame){
      if(frame.contentWindow!==event.source)return;
      frame.style.height=Math.min(24000,Math.max(80,Math.ceil(height)+4))+'px';sendViewport(frame);
    });
  });
  window.addEventListener('resize',schedule);window.addEventListener('scroll',schedule,{passive:true});
  document.addEventListener('DOMContentLoaded',()=>{frames().forEach(frame=>frame.addEventListener('load',schedule));schedule();});schedule();
})();
</script>
</main>
<?php require_once $templates['footer']; ?>
