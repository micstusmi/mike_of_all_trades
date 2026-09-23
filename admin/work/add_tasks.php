<?php
declare(strict_types=1);
require_once __DIR__.'/_auth.php';
require_once __DIR__.'/../../includes/work_tracker.php';

$id=(int)($_GET['id']??0);
$job=wt_job($pdo,$id);
$q=$pdo->prepare("SELECT id,title,description,status,task_origin,mike_estimate_low,mike_estimate_high,customer_visible FROM work_tasks WHERE job_id=? ORDER BY task_order,id");
$q->execute([$id]);
$tasks=$q->fetchAll(PDO::FETCH_ASSOC);
$statusLabels=['not_started'=>'Not started','in_progress'=>'In progress','blocked'=>'Blocked','completed'=>'Completed','cancelled'=>'Cancelled'];

$adminPageTitle='Add task/s — Job #'.$id;
$adminBreadcrumbs=['Work Tracker'=>'index.php','Manage job'=>'job.php?id='.$id,'Add task/s'=>''];
$adminJob=$job;
?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=wt_html($adminPageTitle)?></title><style>
body{font-family:system-ui,-apple-system,sans-serif;background:#eef2f4;color:#17202a;margin:0}.wrap{max-width:1100px;margin:auto;padding:18px}.card{background:#fff;border-radius:14px;padding:18px;margin:13px 0;box-shadow:0 2px 10px #0001}.grid{display:grid;grid-template-columns:2fr 1fr 1fr;gap:11px}.wide{grid-column:1/-1}label{display:block;font-size:12px;font-weight:800;margin-bottom:4px}input,select,textarea{width:100%;box-sizing:border-box;padding:10px;border:1px solid #cbd5dc;border-radius:8px;font:inherit}textarea{min-height:80px;resize:vertical}.check{display:flex;gap:7px;align-items:center;margin-top:25px}.check input{width:auto}.actions{display:flex;gap:9px;flex-wrap:wrap;margin-top:14px}.btn{display:inline-block;background:#17202a;color:#fff;border:0;border-radius:9px;padding:11px 14px;font-weight:850;text-decoration:none;cursor:pointer}.btn.primary{background:#087c2e}.btn.secondary{background:#fff;color:#17202a;border:1px solid #cbd5dc}.btn:disabled{opacity:.55;cursor:wait}.notice{display:none;padding:11px 13px;border-radius:9px;margin:12px 0}.notice.show{display:block}.notice.ok{background:#eaf7ee;border:1px solid #8ac69a}.notice.bad{background:#fdecec;border:1px solid #e6a0a0}.session-list,.existing-list{list-style:none;padding:0;margin:8px 0}.session-list li,.existing-list li{display:flex;justify-content:space-between;gap:12px;border-top:1px solid #e2e8ed;padding:10px 2px}.session-list li:first-child,.existing-list li:first-child{border-top:0}.meta{color:#667681;font-size:13px}.pill{font-size:11px;font-weight:800;background:#edf1f4;border-radius:999px;padding:4px 7px;white-space:nowrap}.hint{color:#667681;font-size:13px}.sticky-form{scroll-margin-top:135px}.bulk{background:#f7fafc;border:1px solid #d8e1e8;border-radius:11px;padding:13px;margin-top:12px}@media(max-width:760px){.grid{grid-template-columns:1fr}.wide{grid-column:auto}.check{margin-top:0}.session-list li,.existing-list li{display:block}.pill{display:inline-block;margin-top:5px}}
</style></head><body><?php require __DIR__.'/../../includes/admin_nav.php';?><main class="wrap">
<section class="card sticky-form" id="task-entry"><h1>Add task/s</h1><p class="hint">Add one detailed task or paste several task titles. After saving, this form stays ready for the next entry.</p><div id="result" class="notice" role="status" aria-live="polite"></div>
<form id="taskForm" enctype="multipart/form-data"><input type="hidden" name="job_id" value="<?=$id?>"><input type="hidden" name="request_token" id="requestToken">
<div class="grid"><div><label for="title">Task title</label><input id="title" name="title" maxlength="190" placeholder="e.g. Remove rotten plaster" autofocus></div><div><label for="origin">Origin</label><select id="origin" name="task_origin"><option value="original">Original scope</option><option value="customer_requested">Customer requested</option><option value="mike_added" selected>Mike added</option><option value="ai_suggested">AI suggested</option><option value="unforeseen">Unforeseen / discovered</option></select></div><label class="check"><input type="checkbox" name="customer_visible" value="1" checked> Show customer</label>
<div class="wide"><label for="description">Description / scope notes</label><textarea id="description" name="description" placeholder="Optional detail for a single task"></textarea></div><div><label for="estimateLow">Mike estimate low (h)</label><input id="estimateLow" type="number" min="0" step="0.1" name="mike_estimate_low"></div><div><label for="estimateHigh">Mike estimate high (h)</label><input id="estimateHigh" type="number" min="0" step="0.1" name="mike_estimate_high"></div></div>
<div class="bulk"><label for="bulkTasks">Or paste multiple task titles — one per line</label><textarea id="bulkTasks" name="bulk_tasks" placeholder="Remove rotten plaster&#10;Inspect wall framing for termite damage&#10;Replace damaged framing if required&#10;Cut and install new plaster"></textarea><div class="hint">Blank lines and repeated lines in the same batch are ignored. Shared origin, visibility and estimates apply to every line.</div></div>
<div class="actions"><button class="btn primary" type="submit" name="after_save" value="another">SAVE &amp; ADD ANOTHER</button><button class="btn" type="submit" name="after_save" value="finish">SAVE &amp; FINISH</button><a class="btn secondary" href="job.php?id=<?=$id?>&amp;view=tasks">CANCEL</a></div></form></section>

<section class="card" id="sessionCard" hidden><h2>Added during this session</h2><ul id="sessionList" class="session-list"></ul></section>

<section class="card"><h2>Existing tasks <span id="taskCount" class="pill"><?=count($tasks)?></span></h2><p class="hint">These tasks remain below the entry form so they do not interrupt rapid task creation.</p><ul class="existing-list"><?php foreach($tasks as $task):?><li><div><b><?=wt_html((string)$task['title'])?></b><?php if(trim((string)($task['description']??''))!==''):?><div class="meta"><?=wt_html((string)$task['description'])?></div><?php endif;?></div><span class="pill"><?=wt_html($statusLabels[$task['status']]??ucwords(str_replace('_',' ',(string)$task['status'])))?></span></li><?php endforeach;?><?php if(!$tasks):?><li id="noExisting" class="hint">No existing tasks yet.</li><?php endif;?></ul></section>
</main><script>
(() => {
  'use strict';
  const form=document.getElementById('taskForm'),title=document.getElementById('title'),bulk=document.getElementById('bulkTasks'),result=document.getElementById('result'),sessionCard=document.getElementById('sessionCard'),sessionList=document.getElementById('sessionList'),count=document.getElementById('taskCount');
  let pendingToken=null;
  const token=()=>crypto.randomUUID?crypto.randomUUID():(Date.now().toString(36)+'-'+Math.random().toString(36).slice(2));
  const show=(ok,message)=>{result.className='notice show '+(ok?'ok':'bad');result.textContent=message;};
  const escape=s=>String(s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  form.addEventListener('submit',async event=>{
    event.preventDefault();
    const finish=event.submitter?.value==='finish';
    if(!title.value.trim()&&!bulk.value.trim()){show(false,'Enter a task title or paste one or more task titles.');title.focus();return;}
    pendingToken=pendingToken||token();document.getElementById('requestToken').value=pendingToken;
    const buttons=[...form.querySelectorAll('button[type="submit"]')];buttons.forEach(b=>b.disabled=true);
    try{
      const response=await fetch('../../api/work/add_tasks.php',{method:'POST',body:new FormData(form),credentials:'same-origin'});
      const data=await response.json().catch(()=>({ok:false,error:'The server returned an unreadable response.'}));
      if(!response.ok||!data.ok)throw new Error(data.error||'The task/s could not be saved.');
      pendingToken=null;
      for(const task of data.tasks){const li=document.createElement('li');li.innerHTML='<div><b>✓ '+escape(task.title)+'</b></div><span class="pill">Saved</span>';sessionList.prepend(li);}
      sessionCard.hidden=false;count.textContent=String(Number(count.textContent||0)+data.tasks.length);
      show(true,(data.duplicate?'Already saved safely: ':'Saved: ')+data.tasks.length+' task'+(data.tasks.length===1?'':'s')+'.');
      if(finish){window.location.href='job.php?id=<?= $id ?>&view=tasks';return;}
      title.value='';bulk.value='';document.getElementById('description').value='';document.getElementById('estimateLow').value='';document.getElementById('estimateHigh').value='';title.focus();window.scrollTo({top:document.getElementById('task-entry').offsetTop-120,behavior:'smooth'});
    }catch(error){show(false,error.message||'The task/s could not be saved. You can retry safely.');}
    finally{buttons.forEach(b=>b.disabled=false);}
  });
})();
</script></body></html>
