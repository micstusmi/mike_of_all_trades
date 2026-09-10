/* V8.4B: finish collapsing legacy loose Manage Job cards after V8.3. */
(() => {
'use strict';
function run(){
 const headings=['Workers','Recent sessions','Add material / expense','Progress payment','Send daily progress report','SMS history'];
 const stateKey='mot-workspace-v84b-job-'+(new URLSearchParams(location.search).get('id')||'unknown');
 let state={};try{state=JSON.parse(localStorage.getItem(stateKey)||'{}')}catch(_){}
 for(const card of document.querySelectorAll('.card')){
   if(card.classList.contains('wt83-section')) continue;
   const h=Array.from(card.children).find(x=>/^H[234]$/.test(x.tagName));
   if(!h)continue;
   const title=(h.textContent||'').replace(/\s+/g,' ').trim();
   if(!headings.includes(title))continue;
   const key=title.toLowerCase().replace(/[^a-z0-9]+/g,'-');
   const body=document.createElement('div'); body.className='wt83-section-body';
   for(const node of Array.from(card.childNodes)){ if(node!==h)body.appendChild(node); }
   h.style.display='none';
   const b=document.createElement('button');b.type='button';b.className='wt83-section-toggle';
   const left=document.createElement('span');left.className='wt83-section-heading';
   const t=document.createElement('span');t.className='wt83-section-title';t.textContent=title;
   const sub=document.createElement('span');sub.className='wt83-section-summary';
   const subtitles={
    'Workers':'Workers and hourly rates',
    'Recent sessions':'Latest tracked activity and running sessions',
    'Add material / expense':'Quick legacy material entry — detailed materials manager available above',
    'Progress payment':'Record payments and request progress payment',
    'Send daily progress report':'Work completed, issues and next priorities',
    'SMS history':'Recent customer text messages'
   };
   sub.textContent=subtitles[title]||'Open to view and edit';
   left.append(t,sub);const chev=document.createElement('span');chev.className='wt83-chevron';chev.textContent='⌄';b.append(left,chev);
   card.classList.add('wt83-section');card.dataset.wt83Open=state[key]?'1':'0';b.setAttribute('aria-expanded',state[key]?'true':'false');
   card.insertBefore(b,card.firstChild);card.appendChild(body);
   b.addEventListener('click',()=>{const o=card.dataset.wt83Open!=='1';card.dataset.wt83Open=o?'1':'0';b.setAttribute('aria-expanded',o?'true':'false');state[key]=o;try{localStorage.setItem(stateKey,JSON.stringify(state))}catch(_){}});
 }
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',()=>setTimeout(run,60));else setTimeout(run,60);
})();