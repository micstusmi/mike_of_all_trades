<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isLocalhost = strpos($_SERVER['HTTP_HOST'], 'localhost') !== false;
$baseUrl = $isLocalhost ? '/mike_of_all_trades/' : '/';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mike Of All Trades | Instant Quotes</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<link rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">

<!-- Main Mike Of All Trades stylesheet -->
<link rel="stylesheet"
      href="<?= $baseUrl ?>css/style.css?v=20260816">

<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>

    <style>
        :root { --mike-orange:#f39200; --mike-navy:#1a252f; --mike-cyan:#0dcaf0; }

        body {
            margin:0;
            display:flex;
            flex-direction:column;
            min-height:100vh;
            background-color:#f4f7f6;
            font-family:'Segoe UI', sans-serif;
            font-size:1.1rem;
        }

        main { flex:1 0 auto; }

        .navbar {
            background-color:var(--mike-navy)!important;
            z-index:1031;
            min-height:90px;
            padding:0!important;
        }

        .navbar .container-header {
            max-width:98%!important;
            margin:0 auto;
            width:100%;
            height:100%;
            display:flex;
            align-items:center;
            justify-content:space-between;
            padding:0 20px;
        }

        .navbar-brand img { height:55px!important; width:auto; }

        .navbar-brand-text {
            color:#fff;
            font-weight:800;
            font-size:1.1rem;
            text-transform:uppercase;
            letter-spacing:1px;
            margin-left:15px;
            white-space:nowrap;
        }

        .header-nav-link {
            color:#fff!important;
            font-weight:700;
            text-transform:uppercase;
            font-size:.85rem!important;
            padding:0 12px!important;
        }

        .header-nav-link:hover { color:var(--mike-orange)!important; }

        .sidebar {
            position:fixed;
            top:90px;
            bottom:0;
            left:0;
            width:280px;
            background:#fff;
            border-right:1px solid #eee;
            z-index:1000;
            overflow-y:auto;
            padding-top:15px;
        }

        .sidebar .nav-link {
            color:#444!important;
            font-weight:500;
            padding:8px 40px;
            font-size:.95rem;
            display:block;
            text-decoration:none;
        }

        .sidebar .nav-link:hover {
            color:var(--mike-orange)!important;
            background-color:#f8f9fa;
        }

        .sidebar-heading {
            padding:12px 40px 4px;
            font-size:.7rem;
            font-weight:800;
            color:#bbb;
            text-transform:uppercase;
            letter-spacing:1.5px;
        }

        @media (min-width:992px) {
            main, .footer-section {
                margin-left:280px;
                padding:40px 60px;
            }

            .container { max-width:95%!important; }
        }

        @media (max-width:991.98px) {
            .navbar { height:80px; }
            .sidebar { display:none!important; }

            main, .footer-section {
                margin-left:0!important;
                padding:20px!important;
            }

            .navbar-collapse {
                background-color:var(--mike-navy);
                position:absolute;
                top:80px;
                left:0;
                width:100%;
                padding:10px 0;
            }
        }

        .dropdown-menu-dark {
            background-color:var(--mike-navy);
            border:1px solid rgba(255,255,255,.1);
        }
    </style>

    <link rel="icon" type="image/png" href="<?= $baseUrl ?>assets/favicon.png?v=1">

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
<img height="1"
     width="1"
     style="display:none"
     src="https://www.facebook.com/tr?id=2488391131669425&ev=PageView&noscript=1"/>
</noscript>
<!-- End Meta Pixel Code -->
</head>

<body>

<header class="navbar navbar-expand-lg navbar-dark sticky-top shadow">
    <div class="container-header">

        <div class="d-flex align-items-center">
            <a class="navbar-brand p-0 m-0" href="<?= $baseUrl ?>index.php">
                <img src="<?= $baseUrl ?>assets/logos/mike_of_all_trades_logo.png" class="rounded shadow-sm">
            </a>
            <div class="navbar-brand-text d-none d-lg-block">Mike Of All Trades</div>
        </div>

        <div class="d-flex align-items-center">
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#topNavbar">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="topNavbar">
                <ul class="navbar-nav ms-auto">

                    <li class="nav-item">
                        <a class="nav-link header-nav-link" href="<?= $baseUrl ?>index.php">HOME</a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link header-nav-link" href="<?= $baseUrl ?>quotes_bookings.php">
                            QUOTES / BOOKINGS
                            <span style="color:var(--mike-orange);font-size:.7rem;">(BETA)</span>
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link header-nav-link" href="<?= $baseUrl ?>about.php">ABOUT</a>
                    </li>

                    <li class="nav-item dropdown">
                        <a class="nav-link header-nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            SERVICES
                        </a>

                        <ul class="dropdown-menu dropdown-menu-end dropdown-menu-dark shadow">
                            <li><h6 class="dropdown-header">CREATIVE</h6></li>
                            <li><a class="dropdown-item" href="<?= $baseUrl ?>graphic_design.php">Graphic Design</a></li>
                            <li><a class="dropdown-item" href="<?= $baseUrl ?>photography.php">Photography</a></li>
                            <li><a class="dropdown-item" href="<?= $baseUrl ?>videography.php">Videography</a></li>

                            <li><hr class="dropdown-divider bg-secondary"></li>

                            <li><h6 class="dropdown-header">TECHNICAL</h6></li>
                            <li><a class="dropdown-item" href="<?= $baseUrl ?>web_design.php">Web Design</a></li>
                            <li><a class="dropdown-item" href="<?= $baseUrl ?>mobile_phone_applications.php">Mobile Apps</a></li>
                            <li><a class="dropdown-item" href="<?= $baseUrl ?>it_work.php">IT Work</a></li>
                            <li><a class="dropdown-item" href="<?= $baseUrl ?>ecommerce.php">E-commerce</a></li>

                            <li><hr class="dropdown-divider bg-secondary"></li>

                            <li><h6 class="dropdown-header">TRADES</h6></li>
                            <li><a class="dropdown-item" href="<?= $baseUrl ?>handyman.php">Handyman</a></li>
                            <li><a class="dropdown-item" href="<?= $baseUrl ?>property_maintenance.php">Property Maintenance</a></li>
                            <li><a class="dropdown-item" href="<?= $baseUrl ?>home_improvements.php">Home Improvements</a></li>
                            <li><a class="dropdown-item" href="<?= $baseUrl ?>signage.php">Signage</a></li>
                            <li><a class="dropdown-item" href="<?= $baseUrl ?>kitchen_and_bathroom_cabinets.php">Kitchen & Cabinets</a></li>
                            <li><a class="dropdown-item" href="<?= $baseUrl ?>flatpacks.php">Flatpacks</a></li>
                            <li><a class="dropdown-item" href="<?= $baseUrl ?>phone_and_data_cabling.php">Phone & Data</a></li>
                        </ul>
                    </li>

                    <?php if (!empty($_SESSION['user_id'])): ?>

                        <?php if (($_SESSION['user_role'] ?? '') === 'admin'): ?>
                            <li class="nav-item">
                                <a class="nav-link header-nav-link" style="color:var(--mike-orange)!important;" href="<?= $baseUrl ?>admin/dashboard.php">
                                    ADMIN
                                </a>
                            </li>
                        <?php else: ?>
                            <li class="nav-item">
                                <a class="nav-link header-nav-link" style="color:var(--mike-orange)!important;" href="<?= $baseUrl ?>customer/dashboard.php">
                                    MY BOOKINGS
                                </a>
                            </li>
                        <?php endif; ?>

                        <li class="nav-item">
                            <a class="nav-link header-nav-link text-warning" href="<?= $baseUrl ?>logout.php">
                                LOGOUT
                            </a>
                        </li>

                    <?php else: ?>

                        <li class="nav-item">
                            <a class="nav-link header-nav-link" href="<?= $baseUrl ?>login.php">
                                LOGIN
                            </a>
                        </li>

                        <li class="nav-item">
                            <a class="nav-link header-nav-link text-warning" href="<?= $baseUrl ?>register.php">
                                REGISTER
                            </a>
                        </li>

                    <?php endif; ?>

                </ul>
            </div>
        </div>
    </div>
</header>

<!-- =========================================================
     MIKE OF ALL TRADES - FLOATING AI CONCIERGE
     ========================================================= -->

<div id="mot-ai-concierge">

    <!-- Permanent floating Ask AI button -->
    <button
        id="mot-ai-button"
        type="button"
        aria-label="Open AI assistant"
        aria-expanded="false"
        title="Need help?"
    >
        <span class="mot-ai-icon">✦</span>
        <span class="mot-ai-button-text">Ask AI</span>
    </button>


    <!-- Popup menu -->
    <div id="mot-ai-popup" class="mot-ai-hidden">

        <button
            id="mot-ai-close"
            type="button"
            aria-label="Close assistant"
        >
            ×
        </button>


        <div class="mot-ai-popup-header">

            <div class="mot-ai-avatar">✦</div>

            <div>
                <strong>Need a hand?</strong>

                <div class="mot-ai-online">
                    <span></span>
                    AI assistant
                </div>
            </div>

        </div>


        <p class="mot-ai-message">
            I can help you find what you're looking for,
            answer questions, get a quote or check when
            Mike is available.
        </p>


        <div class="mot-ai-options">

            <a
                href="#"
                id="mot-ai-help"
                class="mot-ai-option mot-ai-primary"
            >
                <span class="mot-option-icon">💬</span>

                <span>
                    <strong>Ask AI</strong>
                    <small>Questions, services &amp; website help</small>
                </span>

                <span class="mot-arrow">›</span>
            </a>


            <a
                href="#"
                id="mot-ai-quote"
                class="mot-ai-option"
            >
                <span class="mot-option-icon">📝</span>

                <span>
                    <strong>Get a Quote</strong>
                    <small>Let AI help work out your job</small>
                </span>

                <span class="mot-arrow">›</span>
            </a>


            <a
                href="#"
                id="mot-ai-availability"
                class="mot-ai-option"
            >
                <span class="mot-option-icon">📅</span>

                <span>
                    <strong>Check Availability</strong>
                    <small>See when Mike is available</small>
                </span>

                <span class="mot-arrow">›</span>
            </a>


            <a
                href="#"
                id="mot-ai-book"
                class="mot-ai-option"
            >
                <span class="mot-option-icon">✓</span>

                <span>
                    <strong>Make a Booking</strong>
                    <small>Choose a suitable day and time</small>
                </span>

                <span class="mot-arrow">›</span>
            </a>

        </div>


        <button
            id="mot-ai-not-now"
            type="button"
        >
            No thanks, I'll look around
        </button>

    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    /*
     * =====================================================
     * MIKE OF ALL TRADES - FLOATING AI CONCIERGE
     * =====================================================
     */

    const BASE_URL = <?= json_encode($baseUrl) ?>;

    /*
     * Existing website destinations.
     *
     * Using BASE_URL means these work BOTH:
     *
     * localhost:
     * /mike_of_all_trades/...
     *
     * live website:
     * /...
     */

    const AI_PAGE           = BASE_URL + 'ai_helper.php';
    const QUOTE_PAGE        = BASE_URL + 'quotes_bookings.php';
    const AVAILABILITY_PAGE = BASE_URL + 'quotes_bookings.php';
    const BOOKING_PAGE      = BASE_URL + 'quotes_bookings.php';


    /*
     * Delay before automatically offering assistance.
     *
     * 20000 = 20 seconds
     * 15000 = 15 seconds
     */

    const POPUP_DELAY = 10000;


    /*
     * Elements
     */

    const popup        = document.getElementById('mot-ai-popup');
    const button       = document.getElementById('mot-ai-button');
    const closeButton  = document.getElementById('mot-ai-close');
    const notNowButton = document.getElementById('mot-ai-not-now');

    const helpButton         = document.getElementById('mot-ai-help');
    const quoteButton        = document.getElementById('mot-ai-quote');
    const availabilityButton = document.getElementById('mot-ai-availability');
    const bookingButton      = document.getElementById('mot-ai-book');


    /*
     * Safety check.
     *
     * If for some reason this header is loaded somewhere
     * without the concierge HTML, don't throw JS errors.
     */

    if (
        !popup ||
        !button ||
        !closeButton ||
        !notNowButton
    ) {
        return;
    }


    let popupOpen = false;
    let closeTimer = null;


    /*
     * =====================================================
     * HELPER: BUILD URL SAFELY
     * =====================================================
     *
     * This avoids problems such as:
     *
     * ai_helper.php?new=1?source=...
     *
     * and handles query parameters properly.
     */

    function buildUrl(page, params = {}) {

        const url = new URL(page, window.location.origin);

        Object.entries(params).forEach(function ([key, value]) {

            if (
                value !== null &&
                value !== undefined &&
                value !== ''
            ) {
                url.searchParams.set(key, value);
            }

        });

        return url.pathname + url.search;
    }


    /*
     * =====================================================
     * OPEN POPUP
     * =====================================================
     */

    function openPopup() {

        if (closeTimer) {
            clearTimeout(closeTimer);
            closeTimer = null;
        }

        popup.classList.remove('mot-ai-hidden');

        /*
         * Force browser to render the visible element before
         * adding the animation class.
         */

        requestAnimationFrame(function () {

            requestAnimationFrame(function () {
                popup.classList.add('mot-ai-visible');
            });

        });

        popupOpen = true;

        button.setAttribute('aria-expanded', 'true');
    }


    /*
     * =====================================================
     * CLOSE POPUP
     * =====================================================
     */

    function closePopup() {

        popup.classList.remove('mot-ai-visible');

        button.setAttribute('aria-expanded', 'false');

        popupOpen = false;

        closeTimer = setTimeout(function () {

            popup.classList.add('mot-ai-hidden');

            closeTimer = null;

        }, 250);
    }


    /*
     * =====================================================
     * PERMANENT ASK AI BUTTON
     * =====================================================
     */

    button.setAttribute('aria-expanded', 'false');

    button.addEventListener('click', function () {

        if (popupOpen) {

            closePopup();

        } else {

            openPopup();

            /*
             * If they manually opened it before the automatic
             * timer fired, don't pop it at them again.
             */

            sessionStorage.setItem('motAiPopupShown', '1');
        }

    });


    /*
     * =====================================================
     * X BUTTON
     * =====================================================
     */

    closeButton.addEventListener('click', function () {

        closePopup();

        /*
         * Don't automatically pop up again during this visit.
         */

        sessionStorage.setItem('motAiPopupDismissed', '1');

    });


    /*
     * =====================================================
     * NO THANKS
     * =====================================================
     */

    notNowButton.addEventListener('click', function () {

        closePopup();

        sessionStorage.setItem('motAiPopupDismissed', '1');

    });


    /*
     * =====================================================
     * ASK AI
     * =====================================================
     */

    if (helpButton) {

        helpButton.addEventListener('click', function (event) {

            event.preventDefault();

            window.location.href = buildUrl(
                AI_PAGE,
                {
                    new: '1',
                    source: 'floating-assistant',
                    intent: 'help'
                }
            );

        });

    }


    /*
     * =====================================================
     * GET A QUOTE
     * =====================================================
     */

    if (quoteButton) {

        quoteButton.addEventListener('click', function (event) {

            event.preventDefault();

            window.location.href = buildUrl(
                QUOTE_PAGE,
                {
                    source: 'floating-assistant',
                    intent: 'quote'
                }
            );

        });

    }


    /*
     * =====================================================
     * CHECK AVAILABILITY
     * =====================================================
     */

    if (availabilityButton) {

        availabilityButton.addEventListener('click', function (event) {

            event.preventDefault();

            window.location.href = buildUrl(
                AVAILABILITY_PAGE,
                {
                    source: 'floating-assistant',
                    intent: 'availability',
                    step: '2'
                }
            );

        });

    }


    /*
     * =====================================================
     * MAKE A BOOKING
     * =====================================================
     */

    if (bookingButton) {

        bookingButton.addEventListener('click', function (event) {

            event.preventDefault();

            window.location.href = buildUrl(
                BOOKING_PAGE,
                {
                    source: 'floating-assistant',
                    intent: 'booking'
                }
            );

        });

    }


    /*
     * =====================================================
     * AUTOMATIC 20 SECOND INVITATION
     * =====================================================
     *
     * sessionStorage means it appears once during this
     * browser-tab session rather than repeatedly appearing
     * as the customer moves between pages.
     */

    const dismissed =
        sessionStorage.getItem('motAiPopupDismissed') === '1';

    const alreadyShown =
        sessionStorage.getItem('motAiPopupShown') === '1';


    if (!dismissed && !alreadyShown) {

        setTimeout(function () {

            /*
             * They might have manually opened or dismissed
             * it during the 20 seconds.
             */

            const nowDismissed =
                sessionStorage.getItem('motAiPopupDismissed') === '1';

            const nowShown =
                sessionStorage.getItem('motAiPopupShown') === '1';


            if (
                !popupOpen &&
                !nowDismissed &&
                !nowShown
            ) {

                openPopup();

                sessionStorage.setItem(
                    'motAiPopupShown',
                    '1'
                );

            }

        }, POPUP_DELAY);

    }


    /*
     * =====================================================
     * ESC KEY CLOSES POPUP
     * =====================================================
     */

    document.addEventListener('keydown', function (event) {

        if (
            event.key === 'Escape' &&
            popupOpen
        ) {
            closePopup();
        }

    });

});
</script>

<?php include __DIR__ . '/sidebar.php'; ?>