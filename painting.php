<?php
$pageTitle = "Painting Quote Builder";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/painting_config.php';
require_once __DIR__ . '/includes/painting_questions.php';

$questionsJson = json_encode($paintingQuestions, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$configJson = json_encode($paintingConfig, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
?>

<style>
:root{
    --blue:#1d72b8;
    --blue2:#155a91;
    --dark:#111;
    --charcoal:#2f343a;
    --heading-charcoal:#3d434a;
    --charcoal2:#4b5563;
    --text:#5d6773;
    --muted:#666c73;
    --soft:#f4f6f8;
    --line:#000;
    --green:#2e7d32;
    --red:#8a3333;
    --popular:#ffd166;
}

*{
    box-sizing:border-box;
}

body{
    font-family:Arial,sans-serif;
    background:var(--soft);
    margin:0;
    color:var(--charcoal);
}

/* Main page layout */
.painting-page{
    margin-left:260px;
    padding:24px 30px 80px;
    background:var(--soft);
    min-height:calc(100vh - 285px);
}

.painting-container{
    width:100%;
    max-width:1280px;
    margin:0 auto;
    padding:0;
}

/* Landing section */
.page-title{
    text-align:center;
    margin-top:0;
    margin-bottom:12px;
}

.page-title h1{
    color:var(--heading-charcoal);
    font-size:30px;
    font-weight:600;
    margin:0 0 6px;
}

.page-title p{
    color:var(--charcoal2);
    font-size:18px;
    margin:8px 0;
}

.intro-helper{
    text-align:center !important;
    color:var(--text);
    font-size:12px;
    max-width:860px;
    margin:10px auto 0;
    line-height:1.45;
}

.labour-note{
    background:#fff3cd;
    border:1px solid #ffe69c;
    color:var(--heading-charcoal);
    padding:12px 16px;
    border-radius:8px;
    display:inline-block;
    margin-top:18px;
    font-size:16px;
}

/* Trust panel */
.trust-panel{
    background:#fff;
    border-radius:16px;
    padding:22px 24px;
    margin:20px auto 22px;
    max-width:980px;
    box-shadow:0 2px 10px rgba(0,0,0,.06);
}

.trust-panel h3{
    color:var(--heading-charcoal);
    margin:0 0 16px;
    text-align:center;
    font-size:25px;
}

.trust-grid{
    color:#40464d;
    display:grid;
    grid-template-columns:repeat(2,1fr);
    gap:14px 24px;
    font-size:16px;
    text-align:left;
}

.choose-heading{
    color:var(--heading-charcoal);
    text-align:center;
    margin:22px 0 20px;
    font-size:24px;
}

/* Cards */
.card,
.choice-card{
    background:#fff;
    border-radius:14px;
    padding:24px;
    box-shadow:0 2px 10px rgba(0,0,0,.08);
}

.quote-choice-grid{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:18px;
    align-items:stretch;
}

.quote-choice-grid::after{
    content:"No obligation. Final pricing may change after site inspection, photos, access review or scope changes.";
    grid-column:1 / -1;
    display:block;
    text-align:center;
    color:var(--muted);
    font-size:14px;
    margin-top:8px;
}

.choice-card{
    position:relative;
    cursor:pointer;
    border:2px solid transparent;
    display:flex;
    flex-direction:column;
    justify-content:space-between;
    min-height:195px;
}

.choice-card:hover{
    border-color:var(--blue);
}

.choice-card h2{
    color:var(--charcoal);
    font-size:25px;
    margin:0 0 14px;
}

.choice-card p{
    color:var(--text);
    font-size:17px;
    line-height:1.45;
    margin:0 0 18px;
}

.choice-card button{
    align-self:flex-start;
    padding:15px 26px;
    font-size:17px;
    font-weight:600;
}

.choice-card:nth-child(2)::before{
    content:"Most Popular";
    position:absolute;
    top:-10px;
    right:-10px;
    background:var(--popular);
    color:#111;
    font-size:12px;
    font-weight:700;
    padding:5px 9px;
    border-radius:999px;
}

.coming-soon-card{
    opacity:.78;
    cursor:not-allowed;
    border-style:dashed;
}

.coming-soon-card button{
    background:#777;
    cursor:not-allowed;
}

.coming-soon-badge{
    position:absolute;
    top:-10px;
    right:-10px;
    background:#111;
    color:#fff;
    font-size:11px;
    font-weight:700;
    letter-spacing:.04em;
    padding:5px 9px;
    border-radius:999px;
}

/* Shared controls */
button{
    padding:12px 18px;
    border:none;
    border-radius:8px;
    background:var(--blue);
    color:#fff;
    cursor:pointer;
    font-size:16px;
    margin:8px 8px 0 0;
}

button:hover{
    background:var(--blue2);
}

.secondary-btn{
    background:#555;
}

.danger-btn{
    background:var(--red);
}

.success-btn{
    background:var(--green);
}

input,
textarea,
select{
    width:100%;
    padding:12px;
    margin:8px 0 14px;
    border-radius:8px;
    border:1px solid #ccc;
    font-size:15px;
}

textarea{
    min-height:110px;
}

/* Quote builder */
.quote-area{
    margin-top:25px;
    display:none;
}

.builder-layout{
    display:grid;
    grid-template-columns:minmax(0,1fr) 330px;
    gap:22px;
    align-items:start;
}

.side-summary{
    position:sticky;
    top:20px;
}

.status-box{
    background:var(--dark);
    color:#fff;
    border-radius:12px;
    padding:18px;
    margin-bottom:18px;
}

.status-row{
    margin-bottom:16px;
}

.status-row:last-child{
    margin-bottom:0;
}

.status-label{
    display:flex;
    justify-content:space-between;
    gap:12px;
    margin-bottom:6px;
    font-size:14px;
}

.bar-bg{
    height:12px;
    background:#333;
    border-radius:20px;
    overflow:hidden;
}

.bar-fill{
    height:100%;
    background:var(--blue);
    width:0%;
    transition:.3s;
}

.accuracy-text{
    font-size:13px;
    color:#ddd;
    margin-top:6px;
    line-height:1.45;
}

.builder-section{
    border-top:4px solid var(--line);
    padding-top:22px;
    margin-top:22px;
}

.builder-section:first-child{
    border-top:none;
    margin-top:0;
}

.hidden{
    display:none!important;
}

.option-grid{
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:14px;
}

.option-card{
    border:2px solid #ddd;
    border-radius:12px;
    padding:16px;
    cursor:pointer;
    background:#fff;
}

.option-card:hover{
    border-color:var(--blue);
}

.option-card.selected{
    border-color:var(--blue);
    background:#eaf4ff;
}

.answer-summary{
    background:#f1f1f1;
    border-radius:8px;
    padding:10px;
    margin-top:12px;
    font-size:14px;
}

.answered-details{
    border-top:4px solid #000;
    margin-top:18px;
    padding-top:14px;
}

.answered-details summary{
    cursor:pointer;
    font-weight:bold;
    font-size:17px;
}

.multi-option{
    display:block;
    background:#fafafa;
    border:1px solid #ddd;
    border-radius:8px;
    padding:10px;
    margin:8px 0;
}

.multi-option input{
    width:auto;
    margin-right:8px;
}

/* Estimate displays */
.price-box{
    background:#eaf4ff;
    border:1px solid #b9dcff;
    border-radius:10px;
    padding:18px;
    margin-top:18px;
}

.price{
    font-size:32px;
    font-weight:bold;
    color:var(--blue);
}

.range{
    font-size:22px;
    font-weight:bold;
    color:var(--blue2);
}

.small-note{
    color:#666;
    font-size:13px;
    line-height:1.5;
}

.summary-mini{
    background:#fff;
    border-radius:14px;
    padding:18px;
    box-shadow:0 2px 10px rgba(0,0,0,.08);
}

.summary-mini h3{
    margin-top:0;
}

.summary-amount{
    font-size:26px;
    font-weight:bold;
    color:var(--blue);
    margin:5px 0 12px;
}

.summary-range{
    font-weight:bold;
    color:var(--blue2);
}

.summary-line{
    display:flex;
    justify-content:space-between;
    gap:12px;
    border-bottom:1px solid #eee;
    padding:7px 0;
}

.summary-line span:first-child{
    color:#555;
}

.estimate-table{
    width:100%;
    border-collapse:collapse;
    background:#fff;
    margin-top:12px;
}

.estimate-table th,
.estimate-table td{
    border-bottom:1px solid #ddd;
    text-align:left;
    padding:10px;
    vertical-align:top;
}

.estimate-table th{
    background:#f2f2f2;
}

.estimate-table td.num,
.estimate-table th.num{
    text-align:right;
    white-space:nowrap;
}

.explain{
    display:block;
    color:#666;
    font-size:12px;
    margin-top:3px;
    line-height:1.35;
}

.quote-summary-hero{
    background:#111;
    color:#fff;
    border-radius:14px;
    padding:22px;
    margin-bottom:18px;
}

.quote-summary-hero h2{
    margin-top:0;
}

.hero-grid{
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:14px;
}

.hero-tile{
    background:#222;
    border-radius:10px;
    padding:14px;
}

.hero-tile strong{
    display:block;
    font-size:13px;
    color:#ddd;
}

.hero-tile span{
    display:block;
    font-size:24px;
    font-weight:bold;
    margin-top:6px;
}

.ready-box{
    background:#eef9ee;
    border:1px solid #b9e4b9;
    border-radius:10px;
    padding:16px;
    margin-top:16px;
}

.resume-box{
    background:#eaf4ff;
    border:1px solid #b9dcff;
    border-radius:12px;
    padding:14px;
    margin:18px 0;
    text-align:center;
}

/* Responsive */
@media(max-width:1200px){
    .quote-choice-grid{
        grid-template-columns:repeat(2,1fr);
    }
}

@media(max-width:900px){
    .painting-page{
        margin-left:0;
        padding:28px 14px 45px;
        min-height:auto;
    }

    .painting-container{
        max-width:100%;
    }

    .page-title{
        margin-top:0;
    }

    .page-title h1{
        font-size:32px;
    }

    .trust-grid{
        grid-template-columns:1fr;
    }

    .option-grid,
    .builder-layout,
    .hero-grid{
        grid-template-columns:1fr;
    }

    .side-summary{
        position:static;
    }

    .status-label{
        display:block;
    }

    .price{
        font-size:26px;
    }

    .estimate-table{
        font-size:13px;
    }

    .estimate-table th,
    .estimate-table td{
        padding:7px;
    }
}

@media(max-width:700px){
    .quote-choice-grid{
        grid-template-columns:1fr;
    }

    .choice-card{
        min-height:auto;
    }
}

/* Force intro line to be truly centred */
.page-title .intro-helper{
    display:block !important;
    width:100% !important;
    max-width:810px !important;
    margin:15px auto 0 auto !important;
    text-align:center !important;
}
</style>

<main class="painting-page">
<div class="painting-container">
  <div class="page-title">
    <h1>🎨 Professional Painting - Instant Online Quote Builder</h1>
    <p>Get an instant estimate in under 2 minutes. It's so easy!</p>
    <p class="intro-helper">Whether you're painting one room or an entire property, choose from the lists of options that suit you.</p>

  <div class="trust-panel">
    <h3>Why use this estimator?</h3>
    <div class="trust-grid">
      <div>✓ Fast / instant estimates</div>
      <div>✓ Free, no obligation estimate</div>
      <div>✓ Choose between three estimate types depending on how precise / accurate / thorough you need the estimate to be</div>
      <div>✓ Full transparency with no hidden costs, showing a full breakdown of all costs including labour & materials</div>
    </div>
  </div>

  <h2 class="choose-heading">Select how precise you'd like your estimate to be</h2>
  </div>

  <div id="resumeBox" class="resume-box hidden">
    <strong>You have a saved painting quote in progress.</strong><br>
    <button type="button" onclick="resumeSavedQuote()">Resume Saved Quote</button>
    <button type="button" class="secondary-btn" onclick="clearSavedQuote();document.getElementById('resumeBox').classList.add('hidden')">Clear Saved Quote</button>
  </div>

  <div class="quote-choice-grid">
    <div class="choice-card" onclick="startQuote('quick', true)"><h2>⚡ Rough Estimate</h2><p><strong>30–60 seconds</strong><br>Budget estimate for early planning.</p><button type="button">Start Quick Estimate</button></div>
    <div class="choice-card" onclick="startQuote('detailed', true)"><h2>📋 Standard Estimate</h2><p><strong>2–3 minutes</strong><br>Recommended for most homeowners.</p><button type="button">Start Detailed Estimate</button></div>
    <div class="choice-card" onclick="startQuote('precise', true)"><h2>⭐ Precise Estimate</h2><p><strong>4–5 minutes</strong><br>Highest estimating accuracy.</p><button type="button">Start Professional Estimate</button></div>
    <div class="choice-card coming-soon-card"><span class="coming-soon-badge">COMING SOON</span><h2>🤖 AI Assisted</h2><p><strong>Upload photos or plans</strong><br>Let AI help interpret the job details.</p><button type="button" disabled>Coming Soon</button></div>
  </div>

  <div id="quoteArea" class="quote-area"></div>
</div>
</main>

<script>
const QUESTIONS = <?php echo $questionsJson; ?>;
const CONFIG = <?php echo $configJson; ?>;
const STORAGE_KEY = 'painting_quote_builder_v4';
let mode = 'quick';
let currentIndex = 0;
let flow = [];
let answers = {};
let finalRequestHtml = '';

window.addEventListener('load', () => {
  if(localStorage.getItem(STORAGE_KEY)) document.getElementById('resumeBox').classList.remove('hidden');
});

function startQuote(selectedMode, reset){
  mode = selectedMode;
  currentIndex = 0;
  finalRequestHtml = '';
  if(reset) answers = { quote_mode: mode };
  rebuildFlow();
  renderPage();
  saveProgress();
}

function resumeSavedQuote(){
  try{
    const saved = JSON.parse(localStorage.getItem(STORAGE_KEY));
    mode = saved.mode || 'quick';
    currentIndex = saved.currentIndex || 0;
    answers = saved.answers || {quote_mode:mode};
    finalRequestHtml = saved.finalRequestHtml || '';
    rebuildFlow();
    renderPage();
  }catch(e){ alert('Saved quote could not be restored.'); }
}

function saveProgress(){
  localStorage.setItem(STORAGE_KEY, JSON.stringify({mode,currentIndex,answers,finalRequestHtml}));
}
function clearSavedQuote(){ localStorage.removeItem(STORAGE_KEY); }

function rebuildFlow(){ flow = QUESTIONS.filter(q => shouldShow(q)); }
function shouldShow(q){
  if(q.modes && !q.modes.includes(mode)) return false;
  if(!q.show_if) return true;
  for(const key in q.show_if){
    const allowed = q.show_if[key];
    const actual = answers[key];
    if(actual === undefined || actual === null || actual === '') return false;
    if(Array.isArray(allowed) && !allowed.includes(actual)) return false;
    if(!Array.isArray(allowed) && actual !== allowed) return false;
  }
  return true;
}
function answerKey(q){ return q.save_as || q.id; }
function titleForMode(){ return mode === 'quick' ? 'Quick Estimate' : mode === 'detailed' ? 'Detailed Estimate' : 'Precise Quote'; }

function renderPage(){
  rebuildFlow();
  const area = document.getElementById('quoteArea');
  area.style.display = 'block';
  area.className = 'quote-area';
  area.innerHTML = `
    <div class="builder-layout">
      <div class="card">
        <div class="status-box">
          <div class="status-row"><div class="status-label"><strong>Progress</strong><span id="progressText"></span></div><div class="bar-bg"><div id="progressBar" class="bar-fill"></div></div></div>
          <div class="status-row"><div class="status-label"><strong>Estimated Quote Accuracy</strong><span id="accuracyText"></span></div><div class="bar-bg"><div id="accuracyBar" class="bar-fill"></div></div><div id="accuracyNote" class="accuracy-text"></div></div>
        </div>
        <h2>${titleForMode()}</h2>
        <p>Answer one question at a time. Previous answers stay visible and can be opened or changed.</p>
        <div id="answeredSections"></div>
        <div id="activeQuestion"></div>
        <div id="finalSummary"></div>
      </div>
      <aside class="side-summary" id="sideSummary"></aside>
    </div>`;
  renderAnswered();
  renderActive();
  renderSideSummary();
  updateStatus();
  saveProgress();
  area.scrollIntoView({behavior:'smooth'});
}

function renderAnswered(){
  let html = '';
  for(let i=0;i<currentIndex;i++){
    const q = flow[i]; if(!q) continue;
    const key = answerKey(q); if(answers[key] === undefined) continue;
    html += `<details class="answered-details"><summary>✓ ${escapeHtml(q.question)}</summary><div class="answer-summary">${escapeHtml(formatAnswer(q, answers[key]))}</div><button type="button" class="secondary-btn" onclick="editQuestion(${i})">Change answer</button></details>`;
  }
  document.getElementById('answeredSections').innerHTML = html;
}

function renderActive(){
  rebuildFlow();
  const q = flow[currentIndex];
  const active = document.getElementById('activeQuestion');
  if(!q){ active.innerHTML = ''; renderFinalSummary(); return; }
  let html = `<div class="builder-section"><h3>${currentIndex+1}. ${escapeHtml(q.question)}</h3>`;
  if(q.type === 'choice'){
    html += '<div class="option-grid">';
    q.options.forEach(o => { html += `<div class="option-card" onclick="setAnswer('${escapeAttr(answerKey(q))}','${escapeAttr(o.value)}')"><h4>${escapeHtml(o.label)}</h4>${o.help ? `<p>${escapeHtml(o.help)}</p>` : ''}</div>`; });
    html += '</div>';
  } else if(q.type === 'multi'){
    q.options.forEach(o => { html += `<label class="multi-option"><input type="checkbox" name="${escapeAttr(answerKey(q))}" value="${escapeAttr(o.value)}"> ${escapeHtml(o.label)}</label>`; });
    html += `<button type="button" onclick="setMultiAnswer('${escapeAttr(answerKey(q))}')">Continue</button>`;
  } else if(q.type === 'number'){
    html += `<input id="input_${escapeAttr(answerKey(q))}" type="number" min="0" step="1" placeholder="${escapeAttr(q.placeholder || '')}"><button type="button" onclick="setInputAnswer('${escapeAttr(answerKey(q))}')">Continue</button>`;
  } else if(q.type === 'text'){
    html += `<textarea id="input_${escapeAttr(answerKey(q))}" placeholder="${escapeAttr(q.placeholder || '')}"></textarea><button type="button" onclick="setInputAnswer('${escapeAttr(answerKey(q))}')">Continue</button>`;
  }
  html += '</div>';
  active.innerHTML = html;
}

function setAnswer(key,value){ answers[key]=value; advanceAfterAnswer(); }
function setMultiAnswer(key){
  const checked = Array.from(document.querySelectorAll(`input[name="${cssEscape(key)}"]:checked`)).map(i=>i.value);
  if(checked.length === 0){ alert('Please choose at least one option.'); return; }
  answers[key]=checked; advanceAfterAnswer();
}
function setInputAnswer(key){
  const el = document.getElementById('input_'+key);
  answers[key] = el ? el.value.trim() : '';
  advanceAfterAnswer();
}
function advanceAfterAnswer(){ currentIndex++; rebuildFlow(); while(currentIndex < flow.length && answers[answerKey(flow[currentIndex])] !== undefined) currentIndex++; renderPage(); }
function editQuestion(index){
  currentIndex = index;
  const keep = {};
  for(let i=0;i<index;i++){ const q=flow[i]; if(q) keep[answerKey(q)] = answers[answerKey(q)]; }
  keep.quote_mode = mode;
  answers = keep;
  finalRequestHtml = '';
  renderPage();
}

function formatAnswer(q,value){
  if(Array.isArray(value)) return value.map(v => labelFor(q,v)).join(', ');
  return labelFor(q,value);
}
function labelFor(q,value){
  if(!q || !q.options) return value;
  const found = q.options.find(o => o.value == value);
  return found ? found.label : value;
}

function updateStatus(){
  rebuildFlow();
  const totalSteps = Math.max(flow.length,1);
  const step = Math.min(currentIndex+1,totalSteps);
  const done = currentIndex >= totalSteps;
  const progress = done ? 100 : Math.round((step/totalSteps)*100);
  const progressText = done ? `Final summary` : `Step ${step} of ${totalSteps}`;
  setText('progressText',progressText); setWidth('progressBar',progress+'%');
  const acc = estimateAccuracy();
  setText('accuracyText', acc.percent + '%'); setWidth('accuracyBar', acc.percent + '%'); setText('accuracyNote', acc.note);
}
function estimateAccuracy(){
  rebuildFlow();
  const answeredCount = Math.max(Object.keys(answers).filter(k=>k!=='quote_mode').length,0);
  const ratio = Math.min(answeredCount / Math.max(flow.length,1), 1);
  let base=50,max=62,note='Quick estimate: rough guide only. Price could vary a lot after photos or site inspection.';
  if(mode==='detailed'){base=65;max=84;note='Detailed estimate: stronger price range from more job information. Still subject to inspection.';}
  if(mode==='precise'){base=76;max=95;note='Precise estimate: tighter manual estimate from detailed measurements and variables. Final fixed quote still requires review.';}
  const percent = Math.min(98, Math.round(base + ((max-base)*ratio)));
  return {percent,note};
}

function calculateEstimate(){
  const fallback = CONFIG.fallback_prices || {}, rates = CONFIG.rates || {};
  const finishAdjustments = CONFIG.finish_adjustments || {}, conditionAdders = CONFIG.condition_adders || {}, accessAdders = CONFIG.access_adders || {};
  const surfaces = Array.isArray(answers.surfaces) ? answers.surfaces : [];
  const jobType = answers.job_type || 'small', jobSize = answers.job_size || 'small';
  const isExterior = jobType === 'exterior';
  const fallbackPrice = fallback[jobType] && fallback[jobType][jobSize] ? fallback[jobType][jobSize] : 500;
  let items = [], subtotal = 0;
  const num = key => { const n=Number(answers[key]); return isNaN(n)||n<0?0:n; };
  function add(item,qty,rate,total,explanation){ total=Math.round(total/10)*10; if(total>0){ subtotal += total; items.push({item,qty,rate,total,explanation}); } }
  function addAdj(item,percent,explanation){ const total = subtotal * percent; if(Math.abs(total)>1) add(item,'—',Math.round(percent*100)+'%',total,explanation); }

  if(isExterior){
    const exteriorM2 = num('exterior_wall_m2'), trimM = num('linear_trim_m'), windows = num('window_frames_count'), doors = num('doors_count');
    let wallRate = rates.exterior_walls_m2 || 38;
    if(answers.finish_level==='budget' && rates.exterior_walls_m2_budget) wallRate = rates.exterior_walls_m2_budget;
    if(answers.finish_level==='premium' && rates.exterior_walls_m2_premium) wallRate = rates.exterior_walls_m2_premium;
    if(exteriorM2>0) add('Exterior walls / surfaces', exteriorM2+' m²', '$'+wallRate+'/m²', exteriorM2*wallRate, 'Measured exterior surface area supplied by customer.');
    else if(surfaces.includes('exterior_walls') || mode!=='precise') add('Exterior walls / surfaces', 'allowance', 'based on size', fallbackPrice*0.65, 'Allowance based on rough exterior job size because exact m² was not supplied.');
    if(surfaces.includes('eaves')) add('Eaves', trimM>0?trimM+' m':'allowance', trimM>0?'$'+(rates.eaves_linear_m||18)+'/m':'based on size', trimM>0?trimM*(rates.eaves_linear_m||18):fallbackPrice*0.16, 'Extra cutting-in, ladder movement and overhead work.');
    if(surfaces.includes('fascia')) add('Fascia / barge boards', trimM>0?trimM+' m':'allowance', trimM>0?'$'+(rates.fascia_linear_m||16)+'/m':'based on size', trimM>0?trimM*(rates.fascia_linear_m||16):fallbackPrice*0.12, 'Exterior trim boards usually require extra prep and ladder work.');
    if(surfaces.includes('gutters')) add('Gutters / downpipes', 'allowance', 'based on size', fallbackPrice*0.08, 'Allowance for gutters and downpipes if included in scope.');
    if(surfaces.includes('pergola_deck')) add('Pergola / deck / verandah', 'allowance', 'based on size', fallbackPrice*0.18, 'Extra surfaces and access complexity.');
    if(surfaces.includes('fence')) add('Fence', 'allowance', 'based on size', fallbackPrice*0.15, 'Basic fence painting allowance based on selected job size.');
    if(surfaces.includes('doors') || doors>0) add('Exterior doors', doors||2, '$'+(rates.door_each||90)+' each', (doors||2)*(rates.door_each||90), 'Doors require cutting-in and separate coating from wall surfaces.');
    if(surfaces.includes('window_frames') || windows>0) add('Exterior window frames', windows||8, '$'+(rates.exterior_window_frame_each||135)+' each', (windows||8)*(rates.exterior_window_frame_each||135), 'Window frames are time-heavy due to sanding, cutting-in and detail work.');
  } else {
    const wallM2=num('wall_area_m2'), floorM2=num('floor_area_m2'), doors=num('doors_count'), windows=num('window_frames_count');
    if(wallM2>0) add('Interior walls', wallM2+' m²', '$'+(rates.interior_walls_m2||11)+'/m²', wallM2*(rates.interior_walls_m2||11), 'Measured wall area supplied by customer.');
    else if(floorM2>0 && surfaces.includes('walls')) add('Interior walls', 'approx from '+floorM2+' m² floor area', '$'+(rates.interior_walls_m2||11)+'/m² wall area', floorM2*2.4*(rates.interior_walls_m2||11), 'Wall area estimated from floor area because exact wall m² was not supplied.');
    else if(surfaces.includes('walls') || mode!=='precise') add('Interior walls', 'allowance', 'based on size', fallbackPrice*0.60, 'Allowance based on rough job size.');
    if(surfaces.includes('ceilings')) add('Ceilings', floorM2>0?floorM2+' m²':'allowance', floorM2>0?'$'+(rates.ceilings_m2||12)+'/m²':'based on size', floorM2>0?floorM2*(rates.ceilings_m2||12):fallbackPrice*0.22, 'Ceilings require overhead rolling and extra cutting-in.');
    if(surfaces.includes('skirting')) add('Skirting boards', 'allowance', 'based on size', fallbackPrice*0.12, 'Low trim requires separate brushing/cutting and protection of flooring.');
    if(surfaces.includes('architraves')) add('Architraves', 'allowance', 'based on size', fallbackPrice*0.14, 'Door/window trim is detail work and often requires sanding.');
    if(surfaces.includes('doors') || doors>0) add('Doors', doors||3, '$'+(rates.door_each||90)+' each', (doors||3)*(rates.door_each||90), 'Doors are priced per door for both sides where accessible.');
    if(surfaces.includes('window_frames') || windows>0) add('Window frames', windows||4, '$'+(rates.window_frame_each||120)+' each', (windows||4)*(rates.window_frame_each||120), 'Window frames require detailed cutting-in and prep.');
    if(surfaces.includes('robes') || num('robes_count')>0) add('Built-in / walk-in robes', num('robes_count')||1, '$'+(rates.robe_each||160)+' each', (num('robes_count')||1)*(rates.robe_each||160), 'Robes add extra internal surfaces and cutting-in.');
    if(num('room_count')>0) add('Room complexity', num('room_count')+' rooms', '$'+(rates.room_each||140)+' each', num('room_count')*(rates.room_each||140), 'Extra rooms add setup, cutting-in and movement time.');
    if(num('hallways')>0) add('Hallways / entries', num('hallways'), '$'+(rates.hallway_each||180)+' each', num('hallways')*(rates.hallway_each||180), 'Hallways often involve more doors, corners and cutting-in.');
  }
  if(subtotal===0) add('Minimum job allowance','minimum','—', CONFIG.minimum_labour||250, 'Minimum labour charge for small jobs.');

  if(answers.finish_level && finishAdjustments[answers.finish_level] !== undefined) addAdj('Finish level adjustment', finishAdjustments[answers.finish_level], 'Adjustment for selected preparation and finish standard.');
  if(answers.condition && conditionAdders[answers.condition]) addAdj('Surface condition', conditionAdders[answers.condition], 'Extra prep for damaged, rough or imperfect surfaces.');
  if(answers.access && accessAdders[answers.access]) addAdj('Access', accessAdders[answers.access], 'Extra setup time for furniture, ladders, height or difficult areas.');
  if(answers.ceiling_height==='high') addAdj('High ceilings',0.08,'High walls/ceilings take more time and ladder work.');
  if(answers.ceiling_height==='mixed') addAdj('Mixed ceiling heights',0.04,'Some higher areas allowed for.');
  if(answers.colour_change==='dark_to_light') addAdj('Dark-to-light colour change',0.10,'May require extra coverage and spot priming.');
  if(answers.furnished==='partly_furnished') addAdj('Partly furnished',0.04,'Moving/protecting furniture adds time.');
  if(answers.furnished==='furnished') addAdj('Furnished home',0.08,'More masking, moving and protection required.');
  if(answers.storeys==='double') addAdj('Double-storey exterior',0.12,'Higher access and ladder movement.');
  if(answers.storeys==='split') addAdj('Split-level exterior',0.08,'Mixed-height access allowance.');
  if(answers.exterior_surface_type==='weatherboard') addAdj('Weatherboard / timber surface',0.10,'More lines, joins and sanding than flat render.');
  if(answers.exterior_surface_type==='mixed') addAdj('Mixed exterior surface',0.07,'Different surfaces require different prep and application.');
  if(answers.exterior_peeling==='some') addAdj('Some peeling/flaking',0.10,'Scraping, sanding and priming of problem areas.');
  if(answers.exterior_peeling==='lots') addAdj('Heavy peeling/flaking',0.24,'Significant preparation likely required.');
  if(answers.ground_slope==='some_slope') addAdj('Sloped ground',0.06,'Ladder setup takes longer.');
  if(answers.ground_slope==='steep') addAdj('Steep ground',0.14,'Difficult ground access can significantly slow work.');
  if(answers.washing_needed==='light') add('Light exterior wash','allowance','—',rates.wash_light||250,'Basic wash before painting.');
  if(answers.washing_needed==='heavy') add('Heavy exterior wash','allowance','—',rates.wash_heavy||650,'Heavier cleaning/mould/dirt preparation.');
  if(Array.isArray(answers.repairs) && !answers.repairs.includes('none')) add('Repairs / problem areas', answers.repairs.length, '$'+(rates.repair_item||180)+' each', answers.repairs.length*(rates.repair_item||180), 'Allowance based on selected repair types.');

  let labour = Math.max(CONFIG.minimum_labour||250, Math.round(subtotal/50)*50);
  const materialsPercent = isExterior ? (CONFIG.materials_percent_exterior||0.20) : (CONFIG.materials_percent_interior||0.18);
  let materials = 0;
  if(answers.paint_supply==='mike'){
    const rawMaterials = Math.max(CONFIG.minimum_materials||250, Math.round((labour*materialsPercent)/50)*50);
    const markup = CONFIG.materials_markup !== undefined ? Number(CONFIG.materials_markup) : 0.50;
    materials = Math.round((rawMaterials * (1 + markup))/50)*50;
  }
  const acc = estimateAccuracy().percent;
  const rangeFactor = mode==='quick' ? 0.50 : mode==='detailed' ? 0.25 : 0.12;
  const low = Math.round((labour*(1-rangeFactor))/50)*50, high = Math.round((labour*(1+rangeFactor))/50)*50;
  return {labour,materials,total:labour+materials,low,high,accuracy:acc,items,rangeFactor};
}

function renderSideSummary(){
  const est = calculateEstimate();
  const side = document.getElementById('sideSummary'); if(!side) return;
  side.innerHTML = `<div class="summary-mini"><h3>Current Estimate</h3><div class="summary-line"><span>Labour range</span><strong>$${est.low.toLocaleString()} - $${est.high.toLocaleString()}</strong></div><div class="summary-line"><span>Labour midpoint</span><strong>$${est.labour.toLocaleString()}</strong></div><div class="summary-line"><span>Paint/materials</span><strong>$${est.materials.toLocaleString()}</strong></div><p>Total midpoint</p><div class="summary-amount">$${est.total.toLocaleString()}</div><div class="summary-line"><span>Estimated accuracy</span><strong>${est.accuracy}%</strong></div><p class="small-note">This updates as each answer is selected.</p></div>`;
}

function renderFinalSummary(){
  const est = calculateEstimate();
  const final = document.getElementById('finalSummary');
  const materialsText = answers.paint_supply === 'mike' ? `$${est.materials.toLocaleString()}` : '$0 - customer supplies';
  final.innerHTML = `
    <div class="builder-section">
      <div class="quote-summary-hero">
        <h2>🎨 Painting Estimate Summary</h2>
        <div class="hero-grid">
          <div class="hero-tile"><strong>Estimated Labour Range</strong><span>$${est.low.toLocaleString()} - $${est.high.toLocaleString()}</span></div>
          <div class="hero-tile"><strong>Labour Midpoint</strong><span>$${est.labour.toLocaleString()}</span></div>
          <div class="hero-tile"><strong>Estimated Accuracy</strong><span>${est.accuracy}%</span></div>
        </div>
        <p class="accuracy-text">Most jobs should sit near the midpoint if the information supplied is accurate, but final price may change after inspection, photos, measurements or scope changes.</p>
      </div>
      <div class="price-box">
        <p>Estimated paint/materials including supply markup:</p><div class="price">${materialsText}</div>
        <p>Total midpoint estimate:</p><div class="price">$${est.total.toLocaleString()}</div>
      </div>
      <h3>Labour Breakdown</h3>
      ${estimateTable(est.items)}
      ${upgradeButtonHtml()}
      <h3>Your details</h3>
      <input id="customer_name" placeholder="Your name">
      <input id="customer_phone" placeholder="Phone number">
      <input id="customer_email" placeholder="Email address">
      <input id="customer_address" placeholder="Job address or suburb">
      <textarea id="customer_notes" placeholder="Extra notes"></textarea>
      <button type="button" class="success-btn" onclick="requestQuote()">Request Official Quote</button>
      <button type="button" class="danger-btn" onclick="startQuote(mode,true)">Start Again</button>
      <div id="quoteReadyBox"></div>
    </div>`;
  renderSideSummary(); updateStatus(); saveProgress();
}

function estimateTable(items){
  if(!items || !items.length) return '<p>No estimate items yet.</p>';
  return `<table class="estimate-table"><thead><tr><th>Item</th><th class="num">Qty</th><th class="num">Rate</th><th class="num">Total</th></tr></thead><tbody>${items.map(i=>`<tr><td><strong>${escapeHtml(i.item)}</strong><span class="explain">${escapeHtml(i.explanation||'')}</span></td><td class="num">${escapeHtml(i.qty)}</td><td class="num">${escapeHtml(i.rate)}</td><td class="num"><strong>$${Math.round(i.total).toLocaleString()}</strong></td></tr>`).join('')}</tbody></table>`;
}

function upgradeButtonHtml(){
  if(mode==='quick') return '<button type="button" onclick="upgradeMode(\'detailed\')">Continue to Detailed Estimate</button>';
  if(mode==='detailed') return '<button type="button" onclick="upgradeMode(\'precise\')">Continue to Precise Quote</button>';
  return '';
}
function upgradeMode(newMode){
  mode=newMode; answers.quote_mode=newMode; rebuildFlow();
  while(currentIndex < flow.length && answers[answerKey(flow[currentIndex])] !== undefined) currentIndex++;
  renderPage();
}
function requestQuote(){
  const name=val('customer_name'), phone=val('customer_phone'), email=val('customer_email'), address=val('customer_address'), notes=val('customer_notes');
  if(!name || !phone || !email){ alert('Please enter your name, phone and email.'); return; }
  const est=calculateEstimate();
  const ready = document.getElementById('quoteReadyBox');
  const payload = {
    source: 'manual_painting_quote_builder',
    customer: { name, phone, email, address, notes },
    mode,
    answers,
    estimate: est,
    created_at: new Date().toISOString()
  };

  ready.innerHTML = `<div class="ready-box"><h3>Generating Official Quote...</h3><p>Please wait while your quote request is prepared.</p></div>`;

  fetch('painting_quote.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload)
  })
  .then(response => response.json())
  .then(data => {
    if(!data.success){ throw new Error(data.message || 'Quote could not be created.'); }

    const zohoLine = data.zoho && data.zoho.mode === 'live_zoho'
  ? `<p><strong>Quote emailed:</strong> A document including your official estimate has been created and emailed to ${escapeHtml(email)}. Please allow up to 60 seconds for it to arrive. If you cannot see it, please check your Junk/Spam folder.</p>`
  : `<p><strong>Status:</strong> Your estimate has been saved. If you need Mike to review it and follow it up with you please click here.</p>`;

    ready.innerHTML = `
      <div class="ready-box">
        <h3>Quote Request Ready</h3>
        <p><strong>${escapeHtml(name)}</strong>, your painting quote request has been prepared.</p>
        <p><strong>Estimated labour range:</strong> $${est.low.toLocaleString()} - $${est.high.toLocaleString()}</p>
        <p><strong>Total midpoint estimate:</strong> $${est.total.toLocaleString()}</p>
        ${zohoLine}
        <p><strong>Scope of works:</strong></p>
        <p>${escapeHtml(data.scope_of_works || '')}</p>
        <p class="small-note">Saved reference: ${escapeHtml(data.reference || '')}</p>
      </div>`;
    clearSavedQuote();
  })
  .catch(error => {
    ready.innerHTML = `<div class="ready-box" style="border-color:#e0b4b4;background:#fff0f0"><h3>Quote Not Sent</h3><p>${escapeHtml(error.message)}</p><p class="small-note">The estimate is still visible above. Check that <code>painting_quote.php</code> is in the same folder as <code>painting.php</code>.</p></div>`;
  });
}

function val(id){ const el=document.getElementById(id); return el?el.value.trim():''; }
function setText(id,text){ const el=document.getElementById(id); if(el) el.innerText=text; }
function setWidth(id,width){ const el=document.getElementById(id); if(el) el.style.width=width; }
function cssEscape(str){ return String(str).replace(/[^a-zA-Z0-9_-]/g,'\\$&'); }
function escapeAttr(str){ return escapeHtml(str).replace(/`/g,'&#96;'); }
function escapeHtml(str){ return String(str).replace(/[&<>'"]/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[ch])); }
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
