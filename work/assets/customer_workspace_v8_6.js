(function(){
  'use strict';

  const path=location.pathname;
  if(!/\/work\/(?:job|customer_job_record)\.php$/i.test(path)) return;

  function directHeading(card){
    return Array.from(card.children).find(el =>
      /^H[23]$/.test(el.tagName)
    ) || null;
  }

  function meta(title){
    const t=title.toLowerCase();

    if(/original arrangement/.test(t)){
      return ['Original arrangement','Original scope and pricing'];
    }

    if(/^b\.\s*agreement|agreement from this point forward/.test(t)){
      return ['Agreement terms','Terms applying to the authorised work'];
    }

    if(/variation|additional/.test(t)){
      return ['Changes / additional work','Changes to the original work or price'];
    }

    if(/position to date|forecast|payment|cost/.test(t)){
      return [title,'Job costs, payments and current financial position'];
    }

    if(/today.*work plan/.test(t)){
      return ["Today's work plan",'Planned work and priorities'];
    }

    if(/recorded job time|time went/.test(t)){
      return ['Where the job time went','Breakdown of recorded work time'];
    }

    if(/complimentary/.test(t)){
      return ['Complimentary extras','Extra work recorded at no charge'];
    }

    if(/activity history/.test(t)){
      return ['Work history','Recorded job activity and attendances'];
    }

    if(/daily reports/.test(t)){
      return ['Daily reports','Progress updates from Mike'];
    }

    if(/review.*sign/.test(t)){
      return ['Review & sign','Agreement and signature details'];
    }

    return [title,'Open to view more information'];
  }

  function makeAccordion(card){
    if(card.dataset.cw86Ready==='1') return;

    const heading=directHeading(card);
    if(!heading) return;

    const originalTitle=heading.textContent.trim();
    const [title,summary]=meta(originalTitle);

    const body=document.createElement('div');
    body.className='cw86-body';

    Array.from(card.children).forEach(child=>{
      if(child!==heading) body.appendChild(child);
    });

    heading.classList.add('cw86-original-heading');

    const button=document.createElement('button');
    button.type='button';
    button.className='cw86-toggle';
    button.setAttribute('aria-expanded','false');

    const chev=document.createElement('span');
    chev.className='cw86-chevron';
    chev.textContent='▶';

    const text=document.createElement('span');
    text.className='cw86-heading';

    const titleEl=document.createElement('span');
    titleEl.className='cw86-title';
    titleEl.textContent=title;

    const summaryEl=document.createElement('span');
    summaryEl.className='cw86-summary';
    summaryEl.textContent=summary;

    text.append(titleEl,summaryEl);
    button.append(chev,text);

    card.classList.add('cw86-section');
    card.dataset.cw86Ready='1';
    card.dataset.cw86Open='0';

    card.innerHTML='';
    card.append(button,heading,body);

    button.addEventListener('click',()=>{
      const open=card.dataset.cw86Open!=='1';
      card.dataset.cw86Open=open?'1':'0';
      button.setAttribute('aria-expanded',open?'true':'false');
      chev.textContent=open?'▼':'▶';
    });
  }

  function makeGeneratedAccordion(container,title,summary,content,className){
    const box=document.createElement('div');
    box.className=className;
    box.dataset.cw86Open='0';

    const button=document.createElement('button');
    button.type='button';
    button.className='cw86-toggle';
    button.setAttribute('aria-expanded','false');

    const chev=document.createElement('span');
    chev.className='cw86-chevron';
    chev.textContent='▶';

    const text=document.createElement('span');
    text.className='cw86-heading';

    const titleEl=document.createElement('span');
    titleEl.className='cw86-title';
    titleEl.textContent=title;

    const summaryEl=document.createElement('span');
    summaryEl.className='cw86-summary';
    summaryEl.textContent=summary;

    text.append(titleEl,summaryEl);
    button.append(chev,text);

    const body=document.createElement('div');
    body.className=className==='cw86-info'?'cw86-info-body':'cw86-body';
    body.append(content);

    box.append(button,body);

    button.addEventListener('click',()=>{
      const open=box.dataset.cw86Open!=='1';
      box.dataset.cw86Open=open?'1':'0';
      button.setAttribute('aria-expanded',open?'true':'false');
      chev.textContent=open?'▼':'▶';
    });

    container.append(box);
    return box;
  }

  function run(){
    const wrap=document.querySelector('.wrap');
    if(!wrap) return;

    const mainTitle=Array.from(wrap.children).find(el =>
      el.tagName==='H2' && /Current Job Record/i.test(el.textContent)
    );

    if(mainTitle){
      mainTitle.textContent='Your Job';
    }

    const customerCard=Array.from(wrap.children).find(el =>
      el.classList &&
      el.classList.contains('card') &&
      /Customer:/i.test(el.textContent) &&
      /Site:/i.test(el.textContent)
    );

    const scheduleCard=document.getElementById('customer-schedule');
    const taskCard=document.getElementById('customer-tasks');

    if(scheduleCard){
      scheduleCard.dataset.cw86KeepOpen='1';
    }

    if(taskCard){
      taskCard.classList.add('cw86-primary','cw86-task-hero');
      taskCard.dataset.cw86KeepOpen='1';

      const taskHeading=taskCard.querySelector(':scope > h2');
      if(taskHeading){
        taskHeading.textContent='Tasks & approximate progress';
      }
    }

    if(customerCard){
      if(scheduleCard){
        customerCard.insertAdjacentElement('afterend',scheduleCard);
      }

      if(taskCard){
        if(scheduleCard){
          scheduleCard.insertAdjacentElement('afterend',taskCard);
        }else{
          customerCard.insertAdjacentElement('afterend',taskCard);
        }
      }
    }

    const topGrid=Array.from(wrap.children).find(el =>
      el.classList && el.classList.contains('grid')
    );

    if(topGrid){
      const placeholder=document.createElement('div');
      topGrid.parentNode.insertBefore(placeholder,topGrid);

      const holder=document.createElement('div');
      holder.appendChild(topGrid);

      const costBox=makeGeneratedAccordion(
        holder,
        'Costs & payments',
        'Recorded total, payments and current balance',
        topGrid,
        'cw86-costs'
      );

      placeholder.replaceWith(costBox);
    }

    const infoCard=Array.from(wrap.children).find(el =>
      el.classList &&
      el.classList.contains('info') &&
      !el.querySelector('.task-list')
    );

    if(infoCard && !infoCard.querySelector('h2,h3')){
      const placeholder=document.createElement('div');
      infoCard.parentNode.insertBefore(placeholder,infoCard);

      const infoBox=makeGeneratedAccordion(
        wrap,
        'How job progress works',
        'Why time spent may differ from visual percentage complete',
        infoCard,
        'cw86-info'
      );

      placeholder.replaceWith(infoBox);
    }

    document.querySelectorAll('.card').forEach(card=>{
      if(card.dataset.cw86KeepOpen==='1') return;
      if(card.closest('.cw86-costs,.cw86-info')) return;
      makeAccordion(card);
    });
  }

  if(document.readyState==='loading'){
    document.addEventListener('DOMContentLoaded',run);
  }else{
    run();
  }
})();
