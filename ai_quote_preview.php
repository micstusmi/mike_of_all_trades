<?php
require_once __DIR__ . '/includes/db.php';

$token = trim($_GET['token'] ?? '');
$attachmentKey = trim($_GET['attachment_key'] ?? '');
$estimatedHours = trim($_GET['hours'] ?? '4');
$estimatedPrice = trim($_GET['price'] ?? '400');
$service = trim($_GET['service'] ?? '');
$suburb = trim($_GET['suburb'] ?? '');

$conversation = null;

if ($token) {
    $stmt = $pdo->prepare("
        SELECT *
        FROM ai_conversations
        WHERE conversation_token = ?
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $conversation = $stmt->fetch(PDO::FETCH_ASSOC);
}

include __DIR__ . '/includes/header.php';
?>

<main class="py-5 bg-dark text-white">
    <div class="container" style="max-width:900px;">

        <h2 class="text-info mb-4">AI Quote Preview</h2>

        <?php if (!$conversation): ?>

            <div class="alert alert-warning">
                AI conversation not found.
            </div>

        <?php else: ?>

            <div class="card bg-secondary text-white p-4 rounded-4 border-0 shadow-lg">

                <h4 class="text-info">Quote Draft</h4>

                <p class="text-warning small">
                    Estimated pricing and timeframes are a guide only. Final pricing may vary depending on materials,
                    access, existing conditions, and any unexpected issues discovered during the job.
                </p>

                <hr>

                <label class="form-label fw-bold">Customer Email</label>
                <input
                    id="customerEmail"
                    class="form-control mb-3"
                    value="<?= htmlspecialchars($conversation['customer_email'] ?? '') ?>"
                    placeholder="Customer email"
                >

                <label class="form-label fw-bold">Job Summary / Quote Notes</label>
                <textarea
                    id="quoteNotes"
                    class="form-control mb-3"
                    rows="12"
                ><?= htmlspecialchars($conversation['conversation_text']) ?></textarea>

                <label class="form-label fw-bold">Estimated Labour Hours</label>
                <input
                    id="estimatedHours"
                    type="number"
                    step="0.5"
                    class="form-control mb-3"
                    value="<?= htmlspecialchars($estimatedHours) ?>"
                >

                <label class="form-label fw-bold">Estimated Price</label>
                <input
                    id="estimatedPrice"
                    type="number"
                    class="form-control mb-3"
                    value="<?= htmlspecialchars($estimatedPrice) ?>"
                >

                <input
                    type="hidden"
                    id="conversationToken"
                    value="<?= htmlspecialchars($token) ?>"
                >

                <input
                    type="hidden"
                    id="attachmentKey"
                    value="<?= htmlspecialchars($attachmentKey) ?>"
                >

                <div id="quoteAttachmentPanel"
                     class="bg-dark border border-secondary rounded-3 p-3 mb-3"
                     style="display:none;">
                    <div class="fw-bold text-info mb-1">
                        Files supplied with this quote
                    </div>

                    <div class="small text-light mb-2">
                        These original photos/PDFs will be sent with the formal Zoho quote for reference.
                    </div>

                    <div id="quoteAttachmentList"
                         class="small text-light"></div>
                </div>

                <p class="small text-light">
                    By sending this quotation, the quote will be subject to
                    <a href="/terms" target="_blank" class="text-info">Mike Of All Trades' Terms &amp; Conditions</a>.
                </p>

                <div class="d-grid gap-2">
                    <button
                        id="sendAiQuoteButton"
                        class="btn btn-warning rounded-pill fw-bold"
                        type="button"
                        onclick="sendAiQuote()"
                        <?= empty(trim($conversation['customer_email'] ?? '')) ? 'disabled' : '' ?>
                    >
                        Email quote now with chat details
                    </button>

                    <a
                        class="btn btn-outline-light rounded-pill"
                        href="view_ai_conversation.php?token=<?= urlencode($token) ?>"
                        target="_blank"
                    >
                        View full AI conversation
                    </a>
                </div>

                <div id="quoteStatus" class="mt-3 small"></div>

            </div>

        <?php endif; ?>

    </div>
</main>

<script>
const AI_QUOTE_DB_NAME = 'MikeOfAllTradesQuoteFiles';
const AI_QUOTE_DB_VERSION = 1;
const AI_QUOTE_STORE = 'quoteFiles';

function openQuoteFileDb(){
    return new Promise((resolve, reject) => {
        const request = indexedDB.open(
            AI_QUOTE_DB_NAME,
            AI_QUOTE_DB_VERSION
        );

        request.onupgradeneeded = function(event){
            const db = event.target.result;

            if(!db.objectStoreNames.contains(AI_QUOTE_STORE)){
                const store = db.createObjectStore(
                    AI_QUOTE_STORE,
                    { keyPath:'id' }
                );

                store.createIndex(
                    'attachmentKey',
                    'attachmentKey',
                    { unique:false }
                );
            }
        };

        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

async function getQuoteFiles(){
    const attachmentKey =
        document.getElementById('attachmentKey').value;

    if(!attachmentKey){
        return [];
    }

    const db = await openQuoteFileDb();

    return new Promise((resolve, reject) => {
        const tx = db.transaction(
            AI_QUOTE_STORE,
            'readonly'
        );

        const store = tx.objectStore(AI_QUOTE_STORE);
        const index = store.index('attachmentKey');
        const request = index.getAll(attachmentKey);

        request.onsuccess = () => resolve(request.result || []);
        request.onerror = () => reject(request.error);
    });
}

async function deleteQuoteFiles(){
    const attachmentKey =
        document.getElementById('attachmentKey').value;

    if(!attachmentKey){
        return;
    }

    const db = await openQuoteFileDb();

    await new Promise((resolve, reject) => {
        const tx = db.transaction(
            AI_QUOTE_STORE,
            'readwrite'
        );

        const store = tx.objectStore(AI_QUOTE_STORE);
        const index = store.index('attachmentKey');
        const request = index.openCursor(attachmentKey);

        request.onsuccess = function(event){
            const cursor = event.target.result;

            if(!cursor){
                return;
            }

            cursor.delete();
            cursor.continue();
        };

        tx.oncomplete = () => resolve();
        tx.onerror = () => reject(tx.error);
    });

    localStorage.removeItem('aiQuoteAttachmentKey');
}

async function renderQuoteFiles(){
    try{
        const items = await getQuoteFiles();

        if(items.length === 0){
            return;
        }

        const panel = document.getElementById('quoteAttachmentPanel');
        const list = document.getElementById('quoteAttachmentList');

        list.innerHTML = '';

        items.forEach(item => {
            const row = document.createElement('div');

            const isPdf =
                item.type === 'application/pdf' ||
                String(item.name || '').toLowerCase().endsWith('.pdf');

            row.textContent =
                (isPdf ? '📄 ' : '📷 ') +
                (item.name || 'attachment');

            list.appendChild(row);
        });

        panel.style.display = 'block';

    }catch(err){
        console.log(
            'Could not display retained quote files:',
            err
        );
    }
}

function isValidQuoteEmail(value){
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(
        String(value || '').trim()
    );
}

function updateQuoteSendButtonState(){
    const emailInput =
        document.getElementById('customerEmail');

    const button =
        document.getElementById('sendAiQuoteButton');

    const status =
        document.getElementById('quoteStatus');

    if(!emailInput || !button){
        return;
    }

    const valid =
        isValidQuoteEmail(emailInput.value);

    if(aiQuoteSubmissionInProgress || aiQuoteSubmissionSucceeded){
        return;
    }

    button.disabled = !valid;
    button.classList.toggle('disabled', !valid);

    if(!valid){
        button.innerText = 'Enter a valid email to send quote';

        if(status){
            status.innerText = '';
        }
    }else{
        button.innerText = 'Email quote now with chat details';
    }
}

let aiQuoteSubmissionInProgress = false;
let aiQuoteSubmissionSucceeded = false;

async function sendAiQuote(){
    const status = document.getElementById('quoteStatus');
    const button = document.getElementById('sendAiQuoteButton');
    const emailInput = document.getElementById('customerEmail');

    /*
     * Validate before changing button state or sending anything.
     */
    const emailValue =
        emailInput
            ? emailInput.value.trim()
            : '';

    if(!isValidQuoteEmail(emailValue)){
        if(status){
            status.innerText =
                'Please enter a valid customer email address before sending the quote.';
        }

        if(emailInput){
            emailInput.focus();
        }

        updateQuoteSendButtonState();
        return;
    }

    /*
     * Front-end double-click protection.
     */
    if(aiQuoteSubmissionInProgress || aiQuoteSubmissionSucceeded){
        return;
    }

    aiQuoteSubmissionInProgress = true;

    if(button){
        button.disabled = true;
        button.innerText = 'Sending quote...';
        button.classList.add('disabled');
    }

    status.innerText = 'Preparing quote and attachments...';

    try{
        const retainedFiles = await getQuoteFiles();

        const fd = new FormData();

        fd.append(
            'email',
            document.getElementById('customerEmail').value
        );

        fd.append(
            'notes',
            document.getElementById('quoteNotes').value
        );

        fd.append(
            'hours',
            document.getElementById('estimatedHours').value
        );

        fd.append(
            'price',
            document.getElementById('estimatedPrice').value
        );

        fd.append(
            'conversation_token',
            document.getElementById('conversationToken').value
        );

        retainedFiles.forEach(item => {
            if(item.file){
                fd.append(
                    'attachments[]',
                    item.file,
                    item.name || item.file.name
                );
            }
        });

        status.innerText =
            retainedFiles.length > 0
                ? 'Creating Zoho quote and sending supplied files...'
                : 'Creating and sending Zoho quote...';

        const response = await fetch(
            'generate_quote_request.php',
            {
                method:'POST',
                body:fd
            }
        );

        const text = await response.text();

        let res;

        try{
            res = JSON.parse(text);
        }catch(err){
            throw new Error(
                text.substring(0, 500) ||
                'Invalid server response.'
            );
        }

        if(!res.success){
            throw new Error(
                res.message ||
                'Quote failed.'
            );
        }

        aiQuoteSubmissionSucceeded = true;

        if(button){
            button.disabled = true;
            button.classList.add('disabled');
            button.innerText =
                res.duplicate_prevented
                    ? '✓ Quote already sent'
                    : '✓ Quote sent successfully';
        }

        status.innerText =
            res.duplicate_prevented
                ? 'This exact quote had already been sent, so a duplicate was prevented.'
                : (
                    retainedFiles.length > 0
                        ? 'Quote sent successfully with the supplied files.'
                        : 'Quote sent successfully.'
                );

        /*
         * Delete browser originals only after Zoho / the server
         * confirms that this exact quote is safely sent already.
         */
        await deleteQuoteFiles();

    }catch(err){

        aiQuoteSubmissionInProgress = false;

        status.innerText =
            'Quote failed: ' +
            err.message;

        /*
         * A genuine failure can be retried.
         */
        if(button){
            button.disabled = false;
            button.classList.remove('disabled');
        }

        updateQuoteSendButtonState();

        if(
            button &&
            isValidQuoteEmail(
                document.getElementById('customerEmail')?.value
            )
        ){
            button.innerText = 'Try sending quote again';
        }

        return;
    }

    aiQuoteSubmissionInProgress = false;
}

document.addEventListener(
    'DOMContentLoaded',
    function(){
        renderQuoteFiles();

        const emailInput =
            document.getElementById('customerEmail');

        if(emailInput){
            emailInput.addEventListener(
                'input',
                updateQuoteSendButtonState
            );

            emailInput.addEventListener(
                'blur',
                updateQuoteSendButtonState
            );
        }

        updateQuoteSendButtonState();
    }
);
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
