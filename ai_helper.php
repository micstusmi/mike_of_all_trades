<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" href="assets/favicon.ico">

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
            margin-top:18px;
            display:flex;
            flex-wrap:wrap;
            gap:8px;
        }

        #contextualActions button{
            background:#198754;
            color:white;
            border:none;
            padding:11px 14px;
            border-radius:999px;
            font-size:14px;
            font-weight:bold;
        }

        #contextualActions button:hover{
            opacity:0.92;
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
        Tell us what you need done
    </h2>

    <p class="hint assistant-subheading">
        Type your job below, or continue replying to Mike's assistant here.
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

    <form id="aiForm" class="chatInputBar">

        <textarea
            id="messageInput"
            placeholder="For example: I have some rotten timber on the front of my house that needs repairing..."
            required
        ></textarea>

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
    }

    let aiConversationToken =
        localStorage.getItem('aiConversationToken') || '';

    let chatHistory = [];

    let lastAiData = {};

    let isSavingConversation = false;


    document
        .getElementById('aiForm')
        .addEventListener('submit', async function(e){

            e.preventDefault();

            const message =
                document.getElementById('messageInput').value.trim();

            if(!message){
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

            await sendMessageToAI(
                message,
                'Customer'
            );

            document.getElementById('messageInput').value = '';

            document.getElementById('messageInput').placeholder =
                'Continue typing or responding to this chat...';
        });


    async function sendMessageToAI(message, roleLabel){

        chatHistory.push({
            role: roleLabel,
            message: message
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

            data = await r.json();

        }catch(err){

            replaceThinkingMessage(
                thinkingId,
                'Sorry, I had trouble contacting the AI.'
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
                JSON.parse(
                    data.raw
                );

        }catch(err){

            replaceThinkingMessage(
                thinkingId,
                'Sorry, I had trouble understanding the AI response.'
            );

            console.log(data.raw);

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
            'Review quote form'
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
                    onclick="goQuote()"
                >
                    Review quote form
                </button>

                <button
                    type="button"
                    onclick="goBooking()"
                >
                    Book Mike in with these chat details
                </button>
            `;
        }


        if(looksLikeBookingFlow){

            box.innerHTML += `
                <button
                    type="button"
                    onclick="goBooking()"
                >
                    Book Mike in with these chat details
                </button>
            `;
        }


        if(intent === 'availability'){

            box.innerHTML += `
                <button
                    type="button"
                    onclick="goAvailability()"
                >
                    Open Mike's availability calendar
                </button>
            `;
        }


        box.innerHTML += `
            <button
                type="button"
                onclick="saveChatForLater()"
            >
                Save this chat for later
            </button>

            <button
                type="button"
                onclick="showContactMikeBox()"
            >
                Send this chat to Mike
            </button>

            <button
                type="button"
                onclick="disableAiHelper()"
            >
                Continue without AI
            </button>
        `;
    }


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