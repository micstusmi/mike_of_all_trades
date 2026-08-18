<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" href="/assets/favicon.png">
<link rel="shortcut icon" href="/assets/favicon.png">

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>AI Quote Assistant | Mike Of All Trades</title>

    <meta
        name="description"
        content="Tell Mike of All Trades what you need fixed, repaired, maintained or improved. Get help preparing a quote, checking availability or making a booking."
    >

    <!-- Meta Pixel Code -->
    <script>
    !function(f,b,e,v,n,t,s)
    {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
    n.callMethod.apply(n,arguments):n.queue.push(arguments)};
    if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
    n.queue=[];t=b.createElement(e);t.async=!0;
    t.src=v;s=b.getElementsByTagName(e)[0];
    s.parentNode.insertBefore(t,s)}(window, document,'script',
    'https://connect.facebook.net/en_US/fbevents.js');

    fbq('init', '2488391131669425');
    fbq('track', 'PageView');
    </script>

    <noscript>
        <img
            height="1"
            width="1"
            style="display:none"
            src="https://www.facebook.com/tr?id=2488391131669425&ev=PageView&noscript=1"
            alt=""
        >
    </noscript>
    <!-- End Meta Pixel Code -->

    <style>
        *{
            box-sizing:border-box;
        }

        body{
            font-family:Arial, Helvetica, sans-serif;
            background:#111;
            color:white;
            margin:0;
            padding:0 30px 40px;
        }

        .card{
            background:#1f1f1f;
            padding:20px;
            border-radius:14px;
            max-width:780px;
            margin:0 auto 40px;
        }

        textarea,
        input{
            width:100%;
            padding:14px;
            border-radius:10px;
            border:none;
            margin-bottom:12px;
            box-sizing:border-box;
        }

        textarea{
            min-height:70px;
        }

        button{
            padding:10px 14px;
            border:none;
            border-radius:999px;
            cursor:pointer;
            margin:4px;
            font-weight:bold;
        }

        .primary{
            background:#ffc107;
            color:#000;
        }

        #responseBox,
        #contactBox{
            margin-top:20px;
            padding:16px;
            background:#222;
            border-radius:12px;
            display:none;
        }

        #chatHistoryBox{
            margin-top:25px;
            display:flex;
            flex-direction:column;
            gap:10px;
        }

        .chat-bubble{
            max-width:78%;
            padding:12px 14px;
            border-radius:16px;
            line-height:1.35;
            scroll-margin-top:110px;
            scroll-margin-bottom:220px;
        }

        .chat-customer{
            align-self:flex-end;
            background:#0d6efd;
            color:white;
            border-bottom-right-radius:4px;
        }

        .chat-ai{
            align-self:flex-start;
            background:#2b2b2b;
            color:#f1f1f1;
            border-bottom-left-radius:4px;
        }

        .chat-role{
            font-size:12px;
            opacity:0.7;
            margin-bottom:4px;
        }

        #actionButtons{
            margin-top:25px;
            padding-top:15px;
            border-top:1px solid #2c2c2c;
            display:flex;
            flex-wrap:wrap;
            gap:6px;
            opacity:0.72;
        }

        #actionButtons button{
            background:#2d2d2d;
            color:#d5d5d5;
            border:1px solid #3d3d3d;
            font-size:13px;
        }

        #actionButtons button:hover{
            background:#3a3a3a;
            color:white;
        }



        .attachmentTools{
            margin:10px 0 12px;
        }

        .attachmentSummary{
            display:none;
            width:100%;
            align-items:center;
            justify-content:space-between;
            gap:10px;
            margin:8px 0 4px;
            padding:9px 12px;
            border-radius:12px;
            background:#181818;
            border:1px solid #3a3a3a;
            color:#ddd;
            font-size:12px;
            cursor:pointer;
        }

        .attachmentSummary strong{ color:#fff; }

        .attachmentSummary .attachmentSummaryAction{
            color:#8ec5ff;
            white-space:nowrap;
            font-weight:700;
        }

        .attachmentButton{
            display:inline-flex;
            align-items:center;
            gap:7px;
            background:#2d2d2d;
            color:#fff;
            border:1px solid #444;
            padding:10px 14px;
            border-radius:999px;
            cursor:pointer;
            font-weight:bold;
        }

        .attachmentButton:hover{
            background:#3a3a3a;
        }

        .attachmentHelp{
            color:#aaa;
            font-size:12px;
            margin:7px 0 0;
            line-height:1.4;
        }

        #attachmentPreview{
            display:flex;
            flex-wrap:wrap;
            gap:10px;
            margin:10px 0 4px;
        }

        .attachmentPreviewItem{
            position:relative;
            width:86px;
            background:#181818;
            border:1px solid #3a3a3a;
            border-radius:10px;
            padding:5px;
        }

        .attachmentPreviewItem img{
            width:100%;
            height:72px;
            object-fit:cover;
            display:block;
            border-radius:7px;
        }

        .pdfAttachmentIcon{
            width:100%;
            height:72px;
            border-radius:7px;
            background:#343a40;
            display:flex;
            align-items:center;
            justify-content:center;
            font-size:14px;
            font-weight:700;
            color:#fff;
        }

        .attachmentPreviewName{
            margin-top:5px;
            color:#ccc;
            font-size:10px;
            white-space:nowrap;
            overflow:hidden;
            text-overflow:ellipsis;
        }

        .removeAttachment{
            position:absolute;
            top:-7px;
            right:-7px;
            width:24px;
            height:24px;
            min-width:24px;
            padding:0;
            margin:0;
            border-radius:50%;
            background:#b02a37;
            color:#fff;
            border:2px solid #1f1f1f;
            font-size:16px;
            line-height:20px;
        }

        .chatInputBar{
            position:sticky;
            bottom:0;
            background:#1f1f1f;
            padding-top:15px;
            margin-top:20px;
        }

        .hint{
            color:#aaa;
            font-size:14px;
        }

        #contextualActions{
            margin-top:16px;
            display:flex;
            flex-wrap:wrap;
            align-items:center;
            gap:7px;
        }

        #contextualActions button{
            margin:0;
            border-radius:999px;
            font-size:12px;
            font-weight:600;
            padding:8px 11px;
            transition:0.18s ease;
        }

        /* Main next-step action only */
        #contextualActions .action-primary{
            background:#198754;
            color:#fff;
            border:1px solid #198754;
            padding:10px 14px;
            font-size:13px;
            font-weight:700;
        }

        #contextualActions .action-primary:hover{
            filter:brightness(1.08);
        }

        /* Secondary actions stay available without dominating the chat */
        #contextualActions .action-secondary{
            background:transparent;
            color:#aaa;
            border:1px solid #3b3b3b;
        }

        #contextualActions .action-secondary:hover{
            background:#2a2a2a;
            color:#fff;
            border-color:#555;
        }

        /* =========================================================
           CUSTOM AI PAGE HEADER
           ========================================================= */

        .ai-topbar{
            width:calc(100% + 60px);
            margin-left:-30px;
            margin-bottom:0;
            background:#121212;
            border-bottom:1px solid #2a2a2a;
            position:sticky;
            top:0;
            z-index:1000;
        }

        .ai-topbar-inner{
            max-width:1200px;
            margin:auto;
            padding:14px 24px;
            display:flex;
            justify-content:space-between;
            align-items:center;
            gap:20px;
        }

        .ai-logo{
            color:#fff;
            text-decoration:none;
            font-weight:700;
            letter-spacing:1px;
            font-size:0.95rem;
        }

        .ai-nav{
            display:flex;
            align-items:center;
            gap:18px;
            flex-wrap:wrap;
        }

        .ai-nav a{
            color:#bbb;
            text-decoration:none;
            font-size:0.9rem;
            transition:0.2s;
        }

        .ai-nav a:hover{
            color:#fff;
        }

        /* =========================================================
           PAID-AD / LANDING PAGE INTRO
           ========================================================= */

        .landing-intro{
            max-width:780px;
            margin:0 auto;
            padding:38px 20px 26px;
            text-align:left;
        }

        .landing-eyebrow{
            color:#ffc107;
            font-size:13px;
            font-weight:700;
            text-transform:uppercase;
            letter-spacing:1.3px;
            margin-bottom:9px;
        }

        .landing-intro h1{
            margin:0;
            font-size:34px;
            line-height:1.12;
            color:#fff;
        }

        .landing-intro p{
            margin:14px 0 0;
            color:#bdbdbd;
            font-size:16px;
            line-height:1.55;
            max-width:700px;
        }

        .landing-trust{
            display:flex;
            flex-wrap:wrap;
            gap:8px;
            margin-top:18px;
        }

        .trust-pill{
            display:inline-flex;
            align-items:center;
            gap:6px;
            padding:7px 11px;
            border-radius:999px;
            background:#1c1c1c;
            border:1px solid #333;
            color:#ddd;
            font-size:12px;
            line-height:1;
        }

        .trust-dot{
            width:7px;
            height:7px;
            background:#28a745;
            border-radius:50%;
            display:inline-block;
        }

        .assistant-heading{
            margin:0 0 8px;
            font-size:25px;
            line-height:1.2;
        }

        .assistant-subheading{
            margin-top:0;
            margin-bottom:6px;
        }

        .assistant-explainer{
            color:#cacaca;
            line-height:1.5;
            margin:14px 0 4px;
            font-size:14px;
        }

        /* =========================================================
           MOBILE
           ========================================================= */

        @media (max-width:768px){

            body{
                padding:0 12px 30px;
            }

            .ai-topbar{
                width:calc(100% + 24px);
                margin-left:-12px;
            }

            .ai-topbar-inner{
                flex-direction:column;
                align-items:flex-start;
                padding:13px 16px;
            }

            .ai-nav{
                width:100%;
                gap:14px;
            }

            .landing-intro{
                padding:26px 4px 20px;
            }

            .landing-intro h1{
                font-size:29px;
                line-height:1.12;
            }

            .landing-intro p{
                font-size:15px;
            }

            .landing-trust{
                gap:6px;
            }

            .trust-pill{
                font-size:11px;
                padding:7px 9px;
            }

            .card{
                max-width:100%;
                padding:16px;
                border-radius:12px;
            }

            .assistant-heading{
                font-size:25px;
                line-height:1.15;
            }

            .hint{
                font-size:13px;
            }

            .chat-bubble{
                max-width:92%;
                font-size:15px;
                padding:11px 12px;
            }

            #actionButtons{
                gap:5px;
            }

            #actionButtons button{
                font-size:12px;
                padding:9px 11px;
                flex:1 1 calc(50% - 10px);
            }

            .chatInputBar textarea{
                min-height:58px;
                font-size:16px;
            }

            .primary{
                width:100%;
                padding:14px;
                font-size:16px;
                margin-left:0;
                margin-right:0;
            }

            #contextualActions{
                gap:6px;
            }

            #contextualActions .action-primary,
            #contextualActions .action-secondary{
                font-size:12px;
                padding:8px 10px;
            }

            .chatInputBar{
                position:static;
                bottom:auto;
                padding-top:12px;
                margin-top:16px;
            }

            .attachmentTools.has-attachments .attachmentHelp{
                display:none;
            }

            .attachmentTools.has-attachments .attachmentSummary{
                display:flex;
            }

            #attachmentPreview{
                display:none;
                max-height:190px;
                overflow-y:auto;
                padding:6px 2px;
                margin-top:8px;
                gap:7px;
            }

            #attachmentPreview.mobile-expanded{
                display:flex;
            }

            .attachmentPreviewItem{
                width:72px;
                padding:4px;
            }

            .attachmentPreviewItem img,
            .pdfAttachmentIcon{
                height:58px;
            }

            .attachmentPreviewName{
                font-size:9px;
            }

            .attachmentButton{
                padding:9px 12px;
                font-size:13px;
            }

            input,
            textarea{
                font-size:16px;
            }
        }
    </style>
</head>

<body data-logged-in="<?= !empty($_SESSION['user_id']) ? '1' : '0' ?>">

<header class="ai-topbar">
    <div class="ai-topbar-inner">

        <a href="/" class="ai-logo">
            MIKE OF ALL TRADES
        </a>

        <nav class="ai-nav">
            <a href="/">Home</a>
            <a href="/quotes_bookings.php">Quotes / Bookings</a>

            <?php if (!empty($_SESSION['user_id'])): ?>
                <a href="/customer/dashboard.php">My Dashboard</a>
            <?php else: ?>
                <a href="/login.php">Login</a>
                <a href="/register.php">Register</a>
            <?php endif; ?>
        </nav>

    </div>
</header>

<section class="landing-intro">

    <div class="landing-eyebrow">
        Melbourne Property Maintenance & Handyman Services
    </div>

    <h1>
        Need something fixed around your home or business?
    </h1>

    <p>
        Tell Mike's AI assistant what you need done in your own words.
        It can help understand the job, prepare the details for a quote,
        check availability or help you make a booking.
    </p>

    <div class="landing-trust">

        <span class="trust-pill">
            <span class="trust-dot"></span>
            Melbourne based
        </span>

        <span class="trust-pill">
            Repairs & maintenance
        </span>

        <span class="trust-pill">
            Residential & commercial
        </span>

        <span class="trust-pill">
            No-obligation enquiry
        </span>

    </div>

</section>

<div class="card">

    <h2 class="assistant-heading">
        Briefly - tell us what you need done, if we have follow up questions, Mike's assistant will ask.
    </h2>

    <p class="hint assistant-subheading">
        Type your job below (or use voice to text on mobile), and then continue replying to Mike's assistant below.
    </p>

    <p class="assistant-explainer">
        You don't need to know the technical name for the job.
        Just describe the problem, repair, installation or maintenance work
        as you would explain it to Mike.
    </p>

    <div id="responseBox">
        <p id="replyText"></p>
    </div>

    <div id="contactBox">

        <h3>Send this chat to Mike</h3>

        <p class="hint">
            Mike can review the conversation and contact you offline.
        </p>

        <input
            id="customerName"
            placeholder="Your name"
            autocomplete="name"
        >

        <input
            id="customerEmail"
            type="email"
            placeholder="Your email"
            autocomplete="email"
        >

        <input
            id="customerPhone"
            type="tel"
            placeholder="Your phone"
            autocomplete="tel"
        >

        <button
            type="button"
            onclick="sendChatToMike()"
        >
            Send conversation to Mike
        </button>

        <p id="sendStatus"></p>

    </div>

    <div id="chatHistoryBox"></div>

    <div id="contextualActions"></div>

    <div id="actionButtons">

        <button
            type="button"
            onclick="goQuote()"
        >
            Get quote
        </button>

        <button
            type="button"
            onclick="goBooking()"
        >
            Make booking
        </button>

        <button
            type="button"
            onclick="goAvailability()"
        >
            See availability
        </button>

        <button
            type="button"
            onclick="showContactMikeBox()"
        >
            Send this chat to Mike
        </button>

    </div>

    <form id="aiForm" class="chatInputBar" enctype="multipart/form-data">

        <textarea
            id="messageInput"
            placeholder="For example: I have some rotten timber on the front of my house that needs repairing..."
        ></textarea>

        <div class="attachmentTools">
            <label class="attachmentButton" for="jobAttachments">
                📎 Add photos / PDF plans
            </label>

            <input
                id="jobAttachments"
                type="file"
                accept="image/jpeg,image/png,image/webp,application/pdf,.pdf"
                multiple
                hidden
            >

            <button
                id="attachmentSummary"
                class="attachmentSummary"
                type="button"
                aria-expanded="false"
                onclick="toggleMobileAttachmentManager()"
            >
                <span id="attachmentSummaryText"><strong>Files ready to send</strong></span>
                <span class="attachmentSummaryAction">Manage files</span>
            </button>

            <p class="attachmentHelp">
                Add up to 10 photos or PDF plans/documents (10 MB each). If size is hard to judge from a photo, Mike's assistant may ask for dimensions.
            </p>

            <div id="attachmentPreview"></div>
        </div>

        <button
            class="primary"
            type="submit"
        >
            Send
        </button>

    </form>

</div>

<script>
    const pageParams = new URLSearchParams(window.location.search);

    if(pageParams.get('new') === '1'){
        localStorage.removeItem('aiConversationToken');
        localStorage.removeItem('aiJobIntake');
        localStorage.removeItem('aiQuoteAttachmentKey');
    }

    let aiConversationToken =
        localStorage.getItem('aiConversationToken') || '';

    /*
     * Original quote files are retained in the CUSTOMER'S BROWSER only
     * until the formal Zoho quote is successfully emailed.
     *
     * They are not intentionally stored permanently on the web server.
     */
    let aiQuoteAttachmentKey =
        localStorage.getItem('aiQuoteAttachmentKey') || '';

    if(!aiQuoteAttachmentKey){
        aiQuoteAttachmentKey =
            'mot_quote_' +
            Date.now() +
            '_' +
            Math.random().toString(36).slice(2, 12);

        localStorage.setItem(
            'aiQuoteAttachmentKey',
            aiQuoteAttachmentKey
        );
    }

    let chatHistory = [];

    let lastAiData = {};

    let isSavingConversation = false;

    /*
     * =========================================================
     * ORIGINAL QUOTE ATTACHMENT STORAGE (INDEXEDDB)
     * =========================================================
     *
     * The AI receives temporary upload copies through ai_intake.php,
     * but the ORIGINAL browser File objects are also retained locally
     * so they can later travel with the formal Zoho estimate email.
     *
     * Maximum retained files per quote conversation: 10.
     */

    const AI_QUOTE_DB_NAME = 'MikeOfAllTradesQuoteFiles';
    const AI_QUOTE_DB_VERSION = 1;
    const AI_QUOTE_STORE = 'quoteFiles';
    const AI_QUOTE_MAX_RETAINED_FILES = 10;
    const AI_QUOTE_FILE_TTL_MS = 14 * 24 * 60 * 60 * 1000;

    function openAiQuoteDb(){
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
                        { keyPath: 'id' }
                    );

                    store.createIndex(
                        'attachmentKey',
                        'attachmentKey',
                        { unique:false }
                    );

                    store.createIndex(
                        'createdAt',
                        'createdAt',
                        { unique:false }
                    );
                }
            };

            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    async function getStoredAiQuoteAttachments(){
        const db = await openAiQuoteDb();

        return new Promise((resolve, reject) => {
            const tx = db.transaction(
                AI_QUOTE_STORE,
                'readonly'
            );

            const store = tx.objectStore(AI_QUOTE_STORE);
            const index = store.index('attachmentKey');
            const request = index.getAll(aiQuoteAttachmentKey);

            request.onsuccess = () => {
                resolve(request.result || []);
            };

            request.onerror = () => reject(request.error);
        });
    }

    async function retainAiQuoteAttachments(files){
        if(!files || files.length === 0){
            return;
        }

        const existing = await getStoredAiQuoteAttachments();

        const existingIds = new Set(
            existing.map(item => item.id)
        );

        const uniqueNew = [];

        files.forEach(file => {
            const id =
                aiQuoteAttachmentKey +
                '::' +
                file.name +
                '::' +
                file.size +
                '::' +
                file.lastModified;

            if(!existingIds.has(id)){
                existingIds.add(id);

                uniqueNew.push({
                    id,
                    attachmentKey: aiQuoteAttachmentKey,
                    name: file.name,
                    type: file.type,
                    size: file.size,
                    lastModified: file.lastModified,
                    createdAt: Date.now(),
                    file: file
                });
            }
        });

        if(existing.length + uniqueNew.length > AI_QUOTE_MAX_RETAINED_FILES){
            throw new Error(
                'A quote can retain a maximum of 10 unique photos/PDFs. ' +
                'Please remove some files before continuing.'
            );
        }

        if(uniqueNew.length === 0){
            return;
        }

        const db = await openAiQuoteDb();

        await new Promise((resolve, reject) => {
            const tx = db.transaction(
                AI_QUOTE_STORE,
                'readwrite'
            );

            const store = tx.objectStore(AI_QUOTE_STORE);

            uniqueNew.forEach(item => {
                store.put(item);
            });

            tx.oncomplete = () => resolve();
            tx.onerror = () => reject(tx.error);
            tx.onabort = () => reject(tx.error);
        });
    }

    async function cleanupExpiredAiQuoteAttachments(){
        try{
            const db = await openAiQuoteDb();
            const cutoff = Date.now() - AI_QUOTE_FILE_TTL_MS;

            await new Promise((resolve, reject) => {
                const tx = db.transaction(
                    AI_QUOTE_STORE,
                    'readwrite'
                );

                const store = tx.objectStore(AI_QUOTE_STORE);
                const request = store.openCursor();

                request.onsuccess = function(event){
                    const cursor = event.target.result;

                    if(!cursor){
                        return;
                    }

                    const value = cursor.value || {};

                    if((value.createdAt || 0) < cutoff){
                        cursor.delete();
                    }

                    cursor.continue();
                };

                tx.oncomplete = () => resolve();
                tx.onerror = () => reject(tx.error);
            });
        }catch(err){
            console.log(
                'Could not clean old AI quote attachments:',
                err
            );
        }
    }

    cleanupExpiredAiQuoteAttachments();

    let selectedAttachments = [];

    const attachmentInput =
        document.getElementById('jobAttachments');

    attachmentInput.addEventListener('change', function(){

        const incomingFiles = Array.from(this.files || []);

        incomingFiles.forEach(file => {

            if(selectedAttachments.length >= 10){
                return;
            }

            const allowedTypes = [
                'image/jpeg',
                'image/png',
                'image/webp',
                'application/pdf'
            ];

            if(!allowedTypes.includes(file.type)){
                return;
            }

            if(file.size > 10 * 1024 * 1024){
                return;
            }

            const alreadySelected =
                selectedAttachments.some(existing =>
                    existing.name === file.name &&
                    existing.size === file.size &&
                    existing.lastModified === file.lastModified
                );

            if(!alreadySelected){
                selectedAttachments.push(file);
            }
        });

        this.value = '';

        renderAttachmentPreview();
    });


    function toggleMobileAttachmentManager(){
        const preview = document.getElementById('attachmentPreview');
        const summary = document.getElementById('attachmentSummary');

        if(!preview || !summary){
            return;
        }

        const expanded = preview.classList.toggle('mobile-expanded');

        summary.setAttribute(
            'aria-expanded',
            expanded ? 'true' : 'false'
        );

        const action = summary.querySelector('.attachmentSummaryAction');

        if(action){
            action.textContent = expanded ? 'Hide files' : 'Manage files';
        }
    }


    function updateAttachmentSummary(){
        const tools = document.querySelector('.attachmentTools');
        const summary = document.getElementById('attachmentSummary');
        const summaryText = document.getElementById('attachmentSummaryText');
        const preview = document.getElementById('attachmentPreview');

        if(!tools || !summary || !summaryText || !preview){
            return;
        }

        const photoCount = selectedAttachments.filter(
            file =>
                file.type !== 'application/pdf' &&
                !file.name.toLowerCase().endsWith('.pdf')
        ).length;

        const pdfCount = selectedAttachments.length - photoCount;

        if(selectedAttachments.length === 0){
            tools.classList.remove('has-attachments');
            preview.classList.remove('mobile-expanded');
            summary.setAttribute('aria-expanded', 'false');
            return;
        }

        tools.classList.add('has-attachments');

        const parts = [];

        if(photoCount){
            parts.push(photoCount + (photoCount === 1 ? ' photo' : ' photos'));
        }

        if(pdfCount){
            parts.push(pdfCount + (pdfCount === 1 ? ' PDF' : ' PDFs'));
        }

        summaryText.innerHTML =
            '<strong>📎 ' +
            selectedAttachments.length +
            (selectedAttachments.length === 1 ? ' file ready' : ' files ready') +
            '</strong><br>' +
            escapeHtml(parts.join(' · '));
    }


    function renderAttachmentPreview(){
        const box = document.getElementById('attachmentPreview');

        box.innerHTML = '';

        selectedAttachments.forEach((file, index) => {
            const item = document.createElement('div');
            item.className = 'attachmentPreviewItem';

            let visual;

            if(file.type === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf')){
                visual = document.createElement('div');
                visual.className = 'pdfAttachmentIcon';
                visual.textContent = 'PDF';
            }else{
                visual = document.createElement('img');
                visual.alt = 'Selected job photo';

                const objectUrl = URL.createObjectURL(file);
                visual.src = objectUrl;
                visual.onload = () => URL.revokeObjectURL(objectUrl);
            }

            const name = document.createElement('div');
            name.className = 'attachmentPreviewName';
            name.textContent = file.name;

            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'removeAttachment';
            remove.setAttribute('aria-label', 'Remove attachment');
            remove.textContent = '×';
            remove.onclick = function(){
                selectedAttachments.splice(index, 1);
                renderAttachmentPreview();
            };

            item.appendChild(visual);
            item.appendChild(name);
            item.appendChild(remove);
            box.appendChild(item);
        });

        updateAttachmentSummary();
    }


    document
        .getElementById('aiForm')
        .addEventListener('submit', async function(e){

            e.preventDefault();

            // A fresh customer submission means they want to follow
            // the live conversation again.
            chatAutoScrollEnabled = true;

            const message =
                document.getElementById('messageInput').value.trim();

            const attachmentsForThisMessage =
                [...selectedAttachments];

            if(!message && attachmentsForThisMessage.length === 0){
                return;
            }

            /*
             * Track meaningful engagement with the AI assistant.
             *
             * We deliberately do NOT send the actual job description
             * or any personally identifying information to Meta.
             */
            if(
                typeof fbq === 'function' &&
                !window.motAiStartedTracked
            ){
                fbq('trackCustom', 'AIQuoteStarted');

                window.motAiStartedTracked = true;
            }

            try{
                await retainAiQuoteAttachments(
                    attachmentsForThisMessage
                );
            }catch(err){
                alert(
                    err.message ||
                    'These files could not be retained for the formal quote.'
                );
                return;
            }

            await sendMessageToAI(
                message,
                'Customer',
                attachmentsForThisMessage
            );

            document.getElementById('messageInput').value = '';

            selectedAttachments = [];
            renderAttachmentPreview();

            document.getElementById('messageInput').placeholder =
                'Continue typing or responding to this chat...';
        });


    function parseAiStructuredResponse(rawValue){
        if(rawValue && typeof rawValue === 'object'){
            return rawValue;
        }

        let text = String(rawValue || '').trim();

        if(!text){
            throw new Error('The AI returned an empty response.');
        }

        text = text
            .replace(/^```(?:json)?\s*/i, '')
            .replace(/\s*```$/i, '')
            .trim();

        try{
            return JSON.parse(text);
        }catch(firstError){
            const firstBrace = text.indexOf('{');
            const lastBrace = text.lastIndexOf('}');

            if(firstBrace !== -1 && lastBrace > firstBrace){
                return JSON.parse(text.slice(firstBrace, lastBrace + 1));
            }

            throw firstError;
        }
    }


    async function sendMessageToAI(message, roleLabel, attachments = []){

        const photoCount = attachments.filter(file => file.type !== 'application/pdf').length;
        const pdfCount = attachments.filter(file => file.type === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf')).length;

        const attachmentParts = [];

        if(photoCount){
            attachmentParts.push('📷 ' + photoCount + (photoCount === 1 ? ' photo attached' : ' photos attached'));
        }

        if(pdfCount){
            attachmentParts.push('📄 ' + pdfCount + (pdfCount === 1 ? ' PDF attached' : ' PDFs attached'));
        }

        const attachmentSummary = attachmentParts.join(' · ');

        const historyMessage =
            [message, attachmentSummary]
                .filter(Boolean)
                .join('\n');

        chatHistory.push({
            role: roleLabel,
            message: historyMessage
        });

        const thinkingId =
            'thinking_' + Date.now();

        chatHistory.push({
            id: thinkingId,
            role: 'AI',
            message: 'Thinking. Please wait a second.'
        });

        renderChatHistory();

        saveAiConversation();

        const lowerMessage =
            message.toLowerCase().trim();


        if(
            lowerMessage === 'day' ||
            lowerMessage === 'week' ||
            lowerMessage === 'month'
        ){

            let calendarView =
                lowerMessage === 'day'
                    ? 'day'
                    : lowerMessage === 'month'
                        ? 'month'
                        : 'week';

            window.location.href =
                'quotes_bookings.php?' +
                'ai_intake=1' +
                '&step=availability' +
                '&view=' +
                calendarView;

            return;
        }


        const fd = new FormData();

        fd.append(
            'job',
            message
        );

        fd.append(
            'history',
            formatChatHistory()
        );

        attachments.forEach(file => {
            fd.append('attachments[]', file, file.name);
        });

        let data;

        try{

            const r =
                await fetch(
                    'ai_intake.php',
                    {
                        method:'POST',
                        body:fd
                    }
                );

            const responseText = await r.text();

            try{
                data = JSON.parse(responseText);
            }catch(parseError){
                console.log('Non-JSON response from ai_intake.php:', responseText);
                throw new Error('The server returned an unexpected response.');
            }

        }catch(err){

            console.log('AI request error:', err);

            replaceThinkingMessage(
                thinkingId,
                'Sorry, I had trouble contacting the AI. Please try sending your message again.'
            );

            return;
        }


        if(!data.success){

            replaceThinkingMessage(
                thinkingId,
                data.message || 'AI request failed.'
            );

            console.log(data);

            return;
        }


        let parsed;

        try{

            parsed =
                data.parsed && typeof data.parsed === 'object'
                    ? data.parsed
                    : parseAiStructuredResponse(data.raw);

        }catch(err){

            replaceThinkingMessage(
                thinkingId,
                'Sorry, the AI response was incomplete. Please try sending your message again.'
            );

            console.log('Could not parse AI response:', data.raw, err);

            return;
        }


        lastAiData = parsed;

        renderContextualActions(
            parsed.intent
        );


        if(parsed.intent === 'availability'){

            try{

                const availabilityResponse =
                    await fetch(
                        'ai_availability_summary.php'
                    );

                const availabilityData =
                    await availabilityResponse.json();

                replaceThinkingMessage(
                    thinkingId,

                    availabilityData.success &&
                    availabilityData.summary

                        ? availabilityData.summary

                        : (
                            parsed.reply ||
                            'No reply returned.'
                        )
                );

            }catch(err){

                replaceThinkingMessage(
                    thinkingId,
                    parsed.reply ||
                    'No reply returned.'
                );
            }

        }else{

            replaceThinkingMessage(
                thinkingId,
                parsed.reply ||
                'No reply returned.'
            );
        }


        document
            .getElementById('responseBox')
            .style.display = 'none';

        renderChatHistory();

        saveAiConversation();
    }


    function replaceThinkingMessage(
        thinkingId,
        newMessage
    ){

        const index =
            chatHistory.findIndex(
                item => item.id === thinkingId
            );

        if(index !== -1){

            chatHistory[index].message =
                newMessage;
        }

        renderChatHistory();

        saveAiConversation();
    }


    async function goQuote(){

        await saveAiConversation();

        if(!aiConversationToken){

            alert(
                'Could not prepare quote preview yet. ' +
                'Please try again in a moment.'
            );

            return;
        }

        saveAiIntake(
            'Proceed to quote preview'
        );

        /*
         * This records that a visitor progressed from chatting
         * into the actual quote flow.
         *
         * It is NOT yet treated as a completed Lead.
         */
        if(typeof fbq === 'function'){

            fbq(
                'trackCustom',
                'AIQuoteReview'
            );
        }


        const intake =
            JSON.parse(
                localStorage.getItem(
                    'aiJobIntake'
                ) || '{}'
            );


        const params =
            new URLSearchParams();


        params.set(
            'token',
            aiConversationToken
        );

        params.set(
            'attachment_key',
            aiQuoteAttachmentKey
        );


        if(intake.estimated_hours){

            params.set(
                'hours',
                intake.estimated_hours
            );
        }


        if(intake.estimated_price){

            params.set(
                'price',
                intake.estimated_price
            );
        }


        if(intake.service){

            params.set(
                'service',
                intake.service
            );
        }


        if(intake.suburb){

            params.set(
                'suburb',
                intake.suburb
            );
        }


        window.location.href =
            'ai_quote_preview.php?' +
            params.toString();
    }


    async function goBooking(){

        await saveAiConversation();

        if(!aiConversationToken){

            alert(
                'Could not prepare booking preview yet. ' +
                'Please try again in a moment.'
            );

            return;
        }


        saveAiIntake(
            'Make a booking'
        );


        if(typeof fbq === 'function'){

            fbq(
                'trackCustom',
                'AIBookingStarted'
            );
        }


        const intake =
            JSON.parse(
                localStorage.getItem(
                    'aiJobIntake'
                ) || '{}'
            );


        const params =
            new URLSearchParams();


        params.set(
            'token',
            aiConversationToken
        );


        if(intake.estimated_hours){

            params.set(
                'hours',
                intake.estimated_hours
            );
        }


        if(intake.service){

            params.set(
                'service',
                intake.service
            );
        }


        if(intake.suburb){

            params.set(
                'suburb',
                intake.suburb
            );
        }


        window.location.href =
            'ai_booking_preview.php?' +
            params.toString();
    }


    function goAvailability(){

        saveAiIntake(
            'See availability'
        );


        if(typeof fbq === 'function'){

            fbq(
                'trackCustom',
                'AvailabilityViewed'
            );
        }


        window.location.href =
            'quotes_bookings.php?' +
            'ai_intake=1' +
            '&step=availability' +
            '&view=week';
    }


    function focusCorrection(){

        document.getElementById(
            'messageInput'
        ).placeholder =
            'Tell us what the AI got wrong, ' +
            'or what direction you want to go instead...';

        document
            .getElementById(
                'messageInput'
            )
            .focus();
    }


    function showContactMikeBox(){

        document
            .getElementById(
                'contactBox'
            )
            .style.display =
            'block';

        document
            .getElementById(
                'customerName'
            )
            .focus();
    }


    function saveAiIntake(option){

        const data = {

            original_job:
                formatChatHistory(),

            understood_job:
                lastAiData.understood_job || '',

            selected_option:
                option,

            conversation_token:
                aiConversationToken,

            estimated_hours:
                lastAiData.estimated_hours || '',

            estimated_price:
                lastAiData.estimated_price || '',

            service:
                lastAiData.service || '',

            suburb:
                lastAiData.suburb || '',

            quote_ready:
                lastAiData.quote_ready || false
        };


        sessionStorage.setItem(
            'aiJobIntake',
            JSON.stringify(data)
        );


        localStorage.setItem(
            'aiJobIntake',
            JSON.stringify(data)
        );
    }


    function sendChatToMike(){

        const name =
            document
                .getElementById(
                    'customerName'
                )
                .value
                .trim();

        const email =
            document
                .getElementById(
                    'customerEmail'
                )
                .value
                .trim();

        const phone =
            document
                .getElementById(
                    'customerPhone'
                )
                .value
                .trim();


        if(!name){

            document
                .getElementById(
                    'sendStatus'
                )
                .innerText =
                'Please enter your name.';

            return;
        }


        if(!email && !phone){

            document
                .getElementById(
                    'sendStatus'
                )
                .innerText =
                'Please enter either an email address or phone number.';

            return;
        }


        const fd =
            new FormData();


        fd.append(
            'name',
            name
        );


        fd.append(
            'email',
            email
        );


        fd.append(
            'phone',
            phone
        );


        fd.append(
            'chat',
            formatChatHistory()
        );


        document
            .getElementById(
                'sendStatus'
            )
            .innerText =
            'Sending...';


        fetch(
            'ai_send_to_mike.php',
            {
                method:'POST',
                body:fd
            }
        )

        .then(
            r => r.json()
        )

        .then(res => {

            document
                .getElementById(
                    'sendStatus'
                )
                .innerText =
                res.message ||
                'Sent to Mike.';


            /*
             * Only count the enquiry as a Lead after the server
             * confirms the request succeeded.
             *
             * No name/email/phone/job description is sent to Meta.
             */
            if(
                res.success &&
                typeof fbq === 'function'
            ){

                fbq(
                    'track',
                    'Lead'
                );
            }
        })

        .catch(err => {

            document
                .getElementById(
                    'sendStatus'
                )
                .innerText =
                'Sorry, the message could not be sent. Please try again.';

            console.log(err);
        });
    }


    function formatChatHistory(){

        return chatHistory
            .map(
                item =>
                    item.role +
                    ': ' +
                    item.message
            )
            .join("\n\n");
    }


    function renderContextualActions(intent){

        const box =
            document.getElementById(
                'contextualActions'
            );

        const fallbackButtons =
            document.getElementById(
                'actionButtons'
            );


        if(fallbackButtons){

            fallbackButtons.style.display =
                'none';
        }


        box.innerHTML = '';


        const fullChat =
            formatChatHistory()
                .toLowerCase();


        const looksLikeBookingFlow =

            intent === 'booking' ||

            fullChat.includes('book ') ||

            fullChat.includes('booked') ||

            fullChat.includes('booking') ||

            fullChat.includes('reserve') ||

            fullChat.includes('lock it in') ||

            fullChat.includes('just book') ||

            fullChat.includes('book him in') ||

            fullChat.includes('book mike');


        const looksLikeQuoteFlow =

            !looksLikeBookingFlow &&

            (
                fullChat.includes('quote') ||

                fullChat.includes('$') ||

                fullChat.includes('estimate') ||

                fullChat.includes(
                    'review the quote form'
                ) ||

                fullChat.includes(
                    'send the formal quote'
                )
            );


        if(
            !looksLikeBookingFlow &&
            (
                intent === 'job_quote' ||
                intent === 'quote_ready' ||
                intent === 'quote' ||
                looksLikeQuoteFlow
            )
        ){

            box.innerHTML += `
                <button
                    type="button"
                    class="action-primary"
                    onclick="goQuote()"
                >
                    Review quote form
                </button>

                <button
                    type="button"
                    class="action-secondary"
                    onclick="goBooking()"
                >
                    Book instead
                </button>
            `;
        }


        if(looksLikeBookingFlow){

            box.innerHTML += `
                <button
                    type="button"
                    class="action-primary"
                    onclick="goBooking()"
                >
                    Book Mike in
                </button>
            `;
        }


        if(intent === 'availability'){

            box.innerHTML += `
                <button
                    type="button"
                    class="action-primary"
                    onclick="goAvailability()"
                >
                    Open availability calendar
                </button>
            `;
        }


        box.innerHTML += `
            <button
                type="button"
                class="action-secondary"
                onclick="saveChatForLater()"
            >
                Save this chat
            </button>

            <button
                type="button"
                class="action-secondary"
                onclick="showContactMikeBox()"
            >
                Send to Mike
            </button>

            <button
                type="button"
                class="action-secondary"
                onclick="disableAiHelper()"
            >
                Continue without AI
            </button>
        `;
    }


    /*
     * =========================================================
     * SMART CHAT AUTO-SCROLL
     * =========================================================
     *
     * Keep the newest AI/customer message visible without blindly
     * forcing the whole page to the absolute bottom. This is important
     * because the message input is sticky and can otherwise cover the
     * latest AI response, especially on mobile.
     */

    let chatAutoScrollEnabled = true;
    let chatAutoScrollTimer = null;

    function getChatScrollOffset(){
        const topbar = document.querySelector('.ai-topbar');
        const topbarHeight = topbar ? topbar.getBoundingClientRect().height : 0;

        return topbarHeight + 18;
    }

    function scrollChatBubbleIntoView(bubble, behavior = 'smooth'){
        if(!bubble){
            return;
        }

        const rect = bubble.getBoundingClientRect();
        const topOffset = getChatScrollOffset();
        const viewportHeight =
            window.innerHeight || document.documentElement.clientHeight;

        /*
         * Aim to show the START of the newest response. This is better
         * than scrolling to the page bottom when an AI answer is taller
         * than the available viewport.
         */
        const targetTop =
            window.scrollY +
            rect.top -
            topOffset;

        window.scrollTo({
            top: Math.max(0, targetTop),
            behavior
        });
    }

    function scrollToLatestChatMessage(behavior = 'smooth'){
        if(!chatAutoScrollEnabled){
            return;
        }

        const box = document.getElementById('chatHistoryBox');

        if(!box){
            return;
        }

        const bubbles = box.querySelectorAll('.chat-bubble');

        if(!bubbles.length){
            return;
        }

        const latest = bubbles[bubbles.length - 1];

        /*
         * Wait until the browser has laid out the newly rendered text,
         * buttons and attachment areas before calculating the position.
         */
        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                scrollChatBubbleIntoView(latest, behavior);
            });
        });

        clearTimeout(chatAutoScrollTimer);

        /*
         * A second gentle correction catches layout changes caused by
         * fonts, wrapping, contextual buttons, images, or mobile browser
         * chrome resizing after the first render.
         */
        chatAutoScrollTimer = setTimeout(() => {
            if(chatAutoScrollEnabled){
                scrollChatBubbleIntoView(latest, 'smooth');
            }
        }, 180);
    }

    /*
     * If the customer deliberately scrolls well above the latest chat,
     * stop dragging them back down. Auto-scroll is re-enabled when they
     * return near the current conversation or submit another message.
     */
    let lastKnownScrollY = window.scrollY;

    window.addEventListener('scroll', function(){
        const currentY = window.scrollY;
        const movingUp = currentY < lastKnownScrollY;

        if(movingUp){
            const box = document.getElementById('chatHistoryBox');

            if(box){
                const rect = box.getBoundingClientRect();

                if(rect.bottom > window.innerHeight + 180){
                    chatAutoScrollEnabled = false;
                }
            }
        }

        lastKnownScrollY = currentY;
    }, { passive:true });


    function renderChatHistory(){

        const box =
            document.getElementById(
                'chatHistoryBox'
            );


        box.innerHTML = '';


        chatHistory.forEach(item => {

            const div =
                document.createElement(
                    'div'
                );


            const isCustomer =
                item.role
                    .toLowerCase()
                    .includes(
                        'customer'
                    );


            div.className =
                'chat-bubble ' +
                (
                    isCustomer
                        ? 'chat-customer'
                        : 'chat-ai'
                );


            div.innerHTML =

                '<div class="chat-role">' +
                    escapeHtml(item.role) +
                '</div>' +

                escapeHtml(item.message)
                    .replace(
                        /\n/g,
                        '<br>'
                    );


            box.appendChild(div);
        });

        scrollToLatestChatMessage();
    }


    function escapeHtml(value){

        return String(value)
            .replace(
                /&/g,
                '&amp;'
            )
            .replace(
                /</g,
                '&lt;'
            )
            .replace(
                />/g,
                '&gt;'
            )
            .replace(
                /"/g,
                '&quot;'
            )
            .replace(
                /'/g,
                '&#039;'
            );
    }


    async function saveAiConversation(){

        if(chatHistory.length === 0){

            return;
        }


        isSavingConversation = true;


        const fd =
            new FormData();


        fd.append(
            'token',
            aiConversationToken
        );


        fd.append(
            'chat',
            formatChatHistory()
        );


        try{

            const r =
                await fetch(
                    'save_ai_conversation.php',
                    {
                        method:'POST',
                        body:fd
                    }
                );


            const data =
                await r.json();


            if(
                data.success &&
                data.token
            ){

                aiConversationToken =
                    data.token;


                localStorage.setItem(
                    'aiConversationToken',
                    aiConversationToken
                );
            }

        }catch(err){

            console.log(
                'Could not save AI conversation:',
                err
            );
        }


        isSavingConversation = false;
    }


    function disableAiHelper(){

        saveAiIntake(
            'Continue without AI'
        );


        window.location.href =
            'quotes_bookings.php?manual=1';
    }


    async function saveChatForLater(){

        const fd =
            new FormData();


        fd.append(
            'token',
            aiConversationToken
        );


        fd.append(
            'chat',
            formatChatHistory()
        );


        try{

            const r =
                await fetch(
                    'save_ai_chat.php',
                    {
                        method:'POST',
                        body:fd
                    }
                );


            const data =
                await r.json();


            if(!data.success){

                alert(
                    data.message ||
                    'Could not save the chat.'
                );

                return;
            }


            if(data.token){

                aiConversationToken =
                    data.token;


                localStorage.setItem(
                    'aiConversationToken',
                    aiConversationToken
                );


                const loggedIn =
                    document.body.dataset.loggedIn ===
                    '1';


                if(!loggedIn){

                    alert(
                        'Your chat has been saved.\n\n' +
                        'To keep it safely attached to your account, ' +
                        'you will now be taken to login/register.'
                    );


                    localStorage.removeItem(
                        'aiConversationToken'
                    );


                    localStorage.removeItem(
                        'aiJobIntake'
                    );


                    window.location.href =
                        'login.php?claim_ai_chat=' +
                        encodeURIComponent(
                            data.token
                        );


                    return;
                }


                alert(
                    'Chat saved successfully.'
                );


                localStorage.removeItem(
                    'aiConversationToken'
                );


                localStorage.removeItem(
                    'aiJobIntake'
                );


                window.location.href =
                    'customer/ai_chats.php';


                return;
            }

        }catch(err){

            alert(
                'Could not save the chat: ' +
                err.message
            );
        }
    }
</script>

</body>
</html>