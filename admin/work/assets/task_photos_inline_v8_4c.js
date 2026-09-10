/* Mike of All Trades V8.4C — inline task before/after photos */
(() => {
'use strict';

const params=new URLSearchParams(location.search);
const jobId=params.get('id');
if(!jobId) return;

function taskForms(){
  const found=[];
  document.querySelectorAll('input[name="task_id"]').forEach(inp=>{
    const form=inp.closest('form');
    if(!form || found.includes(form)) return;
    const action=(form.getAttribute('action')||'').toLowerCase();
    if(action.includes('task') || form.querySelector('button')){
      found.push(form);
    }
  });
  return found;
}

async function loadPanel(panel, taskId){
  try{
    const r=await fetch(`task_photo_inline_panel.php?job_id=${encodeURIComponent(jobId)}&task_id=${encodeURIComponent(taskId)}`, {credentials:'same-origin'});
    if(!r.ok) throw new Error(`HTTP ${r.status}`);
    panel.innerHTML=await r.text();
    wirePanel(panel, taskId);
  }catch(e){
    panel.innerHTML='<div class="wt-photo-error">Could not load task photos: '+String(e.message||e)+'</div>';
  }
}

function wirePanel(panel, taskId){
  panel.querySelectorAll('form.wt-inline-photo-form').forEach(form=>{
    form.addEventListener('submit', async ev=>{
      ev.preventDefault();
      const btn=form.querySelector('button[type="submit"]');
      const old=btn ? btn.textContent : '';
      if(btn){btn.disabled=true;btn.textContent='Uploading…';}
      try{
        const fd=new FormData(form);
        const r=await fetch(form.action,{method:'POST',body:fd,credentials:'same-origin'});
        const data=await r.json().catch(()=>null);
        if(!r.ok || !data || !data.ok) throw new Error(data?.error || `Upload failed (HTTP ${r.status})`);
        await loadPanel(panel,taskId);
      }catch(e){
        alert(e.message||'Photo upload failed.');
        if(btn){btn.disabled=false;btn.textContent=old;}
      }
    });
  });
}

function install(){
  for(const form of taskForms()){
    if(form.dataset.inlinePhotosInstalled==='1') continue;
    const tid=form.querySelector('input[name="task_id"]')?.value;
    if(!tid) continue;
    form.dataset.inlinePhotosInstalled='1';

    const panel=document.createElement('div');
    panel.className='wt-inline-task-photos';
    panel.id='task-photo-'+tid;
    panel.innerHTML='<div class="wt-photo-loading">Loading before / after photos…</div>';

    // Put photo panel immediately after the task edit form so we never nest forms.
    form.insertAdjacentElement('afterend',panel);
    loadPanel(panel,tid);
  }
}

if(document.readyState==='loading'){
 document.addEventListener('DOMContentLoaded',()=>setTimeout(install,100));
}else setTimeout(install,100);

// V8.3 may progressively rebuild/wrap sections; run one second pass.
setTimeout(install,800);
})();