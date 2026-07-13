<?php
$pageTitle = "Mike's AI Estimator";
require_once __DIR__ . '/includes/header.php';
?>

<style>
.ai-estimator-page{
    margin-left:260px;
    padding:30px;
    background:#f4f6f8;
    min-height:80vh;
    color:#2f343a;
}

.ai-estimator-wrap{
    max-width:1050px;
    margin:0 auto;
}

.ai-card{
    background:#fff;
    border-radius:16px;
    padding:24px;
    box-shadow:0 2px 10px rgba(0,0,0,.08);
    margin-bottom:20px;
}

.ai-title{
    text-align:center;
    margin-bottom:24px;
}

.ai-title h1{
    font-size:36px;
    margin-bottom:8px;
}

.ai-message{
    background:#eef6ff;
    border:1px solid #c8e0ff;
    border-radius:12px;
    padding:16px;
    margin:12px 0;
}

.ai-question h3{
    margin-top:0;
}

.ai-option{
    display:block;
    border:1px solid #ddd;
    border-radius:10px;
    padding:12px;
    margin:8px 0;
    cursor:pointer;
    background:#fafafa;
}

.ai-option:hover{
    border-color:#1d72b8;
    background:#eef6ff;
}

.ai-option input{
    width:auto;
    margin-right:8px;
}

.ai-button{
    padding:12px 18px;
    border:none;
    border-radius:8px;
    background:#1d72b8;
    color:#fff;
    cursor:pointer;
    font-size:16px;
    margin-top:10px;
}

.ai-button:hover{
    background:#155a91;
}

.ai-secondary{
    background:#555;
}

textarea,input{
    width:100%;
    padding:12px;
    border:1px solid #ccc;
    border-radius:8px;
    margin:8px 0 14px;
    font-size:15px;
}

textarea{
    min-height:120px;
}

.ai-complete{
    background:#eef9ee;
    border:1px solid #b9e4b9;
}

@media(max-width:900px){
    .ai-estimator-page{
        margin-left:0;
        padding:16px;
    }

    .ai-title h1{
        font-size:30px;
    }
}
</style>

<main class="ai-estimator-page">
    <div class="ai-estimator-wrap">
        <div class="ai-title">
            <h1>🤖 Mike's AI Painting Estimator</h1>
            <p>Answer a few questions, upload plans or photos, and Mike will review everything before preparing your final quote.</p>
        </div>

        <div class="ai-card">
            <div id="aiWelcome" class="ai-message">
                Starting estimator...
            </div>

            <div id="aiQuestionArea"></div>
            <div id="aiUploadArea" style="display:none;"></div>
        </div>
    </div>
</main>

<script>
let aiReference = null;
let aiMode = 'painting';

window.addEventListener('load', () => {
    aiSend({});
});

function aiSend(payload){
    payload.mode = aiMode;
    if(aiReference) payload.reference = aiReference;

    fetch('ai_chat.php', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body:JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(data => {
        if(!data.success){ throw new Error(data.message || 'AI estimator error.'); }

        aiReference = data.reference;
        document.getElementById('aiWelcome').innerText = data.welcome_message;

        if(data.conversation_complete){
            renderAiComplete(data);
        } else {
            renderQuestion(data.next_question);
        }
    })
    .catch(err => {
        document.getElementById('aiQuestionArea').innerHTML =
            `<div class="ai-message" style="background:#fff0f0;border-color:#e0b4b4">${escapeHtml(err.message)}</div>`;
    });
}

function renderQuestion(q){
    const area = document.getElementById('aiQuestionArea');

    let html = `<div class="ai-question"><h3>${escapeHtml(q.question)}</h3>`;

    if(q.type === 'choice'){
        q.options.forEach(opt => {
            html += `<label class="ai-option"><input type="radio" name="ai_answer" value="${escapeAttr(opt)}"> ${escapeHtml(opt)}</label>`;
        });
        html += `<button class="ai-button" onclick="submitChoice('${escapeAttr(q.id)}')">Continue</button>`;
    }

    if(q.type === 'multi'){
        q.options.forEach(opt => {
            html += `<label class="ai-option"><input type="checkbox" name="ai_answer" value="${escapeAttr(opt)}"> ${escapeHtml(opt)}</label>`;
        });
        html += `<button class="ai-button" onclick="submitMulti('${escapeAttr(q.id)}')">Continue</button>`;
    }

    if(q.type === 'text'){
        html += `<textarea id="aiTextAnswer" placeholder="Type your answer here..."></textarea>`;
        html += `<button class="ai-button" onclick="submitText('${escapeAttr(q.id)}')">Continue</button>`;
    }

    html += `
        <button class="ai-button ai-secondary" onclick="showUploadBox()">Upload plans/photos now</button>
    `;

    html += `</div>`;

    area.innerHTML = html;
}

function submitChoice(id){
    const selected = document.querySelector('input[name="ai_answer"]:checked');
    if(!selected){ alert('Please choose an option.'); return; }
    aiSend({answer_id:id, answer:selected.value});
}

function submitMulti(id){
    const selected = Array.from(document.querySelectorAll('input[name="ai_answer"]:checked')).map(x => x.value);
    if(selected.length === 0){ alert('Please choose at least one option.'); return; }
    aiSend({answer_id:id, answer:selected});
}

function submitText(id){
    const text = document.getElementById('aiTextAnswer').value.trim();
    if(!text){ alert('Please enter an answer.'); return; }
    aiSend({answer_id:id, answer:text});
}

function showUploadBox(){
    const area = document.getElementById('aiUploadArea');
    area.style.display = 'block';
    area.innerHTML = `
        <div class="ai-card">
            <h3>Upload plans/photos</h3>
            <p>You can upload floor plans, room photos, exterior photos or PDFs.</p>
            <form id="aiUploadForm">
                <input type="file" name="uploads[]" multiple accept=".jpg,.jpeg,.png,.webp,.pdf,image/*,application/pdf">
                <button class="ai-button" type="submit">Upload Files</button>
            </form>
            <div id="aiUploadResult"></div>
        </div>
    `;

    document.getElementById('aiUploadForm').addEventListener('submit', uploadFiles);
}

function uploadFiles(e){
    e.preventDefault();

    const result = document.getElementById('aiUploadResult');
    const fd = new FormData(e.target);
    fd.append('reference', aiReference);

    result.innerHTML = `<p>Uploading...</p>`;

    fetch('ai_upload.php', {
        method:'POST',
        body:fd
    })
    .then(r => r.json())
    .then(data => {
        if(!data.success){ throw new Error(data.message || 'Upload failed.'); }

        result.innerHTML = `
            <div class="ai-message ai-complete">
                <strong>Upload received.</strong><br>
                Saved files: ${data.saved_files.length}
            </div>
        `;
    })
    .catch(err => {
        result.innerHTML = `<div class="ai-message" style="background:#fff0f0;border-color:#e0b4b4">${escapeHtml(err.message)}</div>`;
    });
}

function renderAiComplete(data){
    document.getElementById('aiQuestionArea').innerHTML = `
        <div class="ai-message ai-complete">
            <h3>AI Painting Estimate Request Prepared</h3>
            <p>Thanks. Your answers have been saved under reference:</p>
            <p><strong>${escapeHtml(data.reference)}</strong></p>
            <p>You can also upload plans/photos now if you haven't already. Mike will review everything before preparing the final quote.</p>
            <button class="ai-button" onclick="showUploadBox()">Upload plans/photos</button>
            <a class="ai-button ai-secondary" href="painting.php" style="display:inline-block;text-decoration:none;">Back to Painting Quote Builder</a>
        </div>
    `;
}

function escapeHtml(str){
    return String(str).replace(/[&<>'"]/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[ch]));
}

function escapeAttr(str){
    return escapeHtml(str).replace(/`/g,'&#96;');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
