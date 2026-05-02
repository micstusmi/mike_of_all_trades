<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mike Of All Trades | Portfolio</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <style>
        :root { --mike-orange: #f39200; --mike-navy: #1a252f; --mike-cyan: #0dcaf0; }
        
        body { margin: 0; display: flex; flex-direction: column; min-height: 100vh; background-color: #f4f7f6; font-family: 'Segoe UI', sans-serif; }

        /* --- 1. Global Navbar & Logo Fix --- */
        .navbar { background-color: var(--mike-navy) !important; z-index: 1031; height: 80px; padding: 0 !important; }
        
        .navbar .container-fluid {
            position: relative;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 20px !important;
        }

        /* This fixes the "Whole Screen" issue on desktop */
        .mobile-logo {
            height: 45px; /* Default desktop size */
            width: auto;
        }

        /* --- 2. The Hero Card (Global) --- */
        .hero-card { 
            background: linear-gradient(135deg, #1a252f 0%, #2c3e50 100%) !important; 
            color: #ffffff !important; 
            border-radius: 20px; 
            padding: 3.5rem 2rem; 
            margin-bottom: 2.5rem; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.2); 
            border: 1px solid rgba(255,255,255,0.1);
            text-align: center;
        }
        .hero-card h1 { color: #ffffff !important; margin-bottom: 1rem; }
        .hero-card .highlight { color: var(--mike-orange) !important; font-weight: 800; }
        .hero-card .lead { color: rgba(255,255,255,0.9) !important; margin-bottom: 2rem; }

        /* --- 3. Mobile Specific Styling --- */
        @media (max-width: 991.98px) {
            .mobile-logo { height: 45px !important; } /* 20% larger on mobile */

            .navbar-center-text {
                position: absolute;
                left: 50%;
                top: 50%;
                transform: translate(-50%, -50%);
                color: #ffffff;
                font-weight: 700;
                font-size: 1.05rem;
                white-space: nowrap;
                z-index: 1030;
            }

            .navbar-toggler {
                border: 2px solid var(--mike-cyan) !important;
                box-shadow: 0 0 10px rgba(13, 202, 240, 0.3);
                z-index: 1032;
            }

            .sidebar { display: none !important; }
            main, .footer-section { margin-left: 0 !important; padding: 20px !important; }

            .navbar-collapse {
                background-color: var(--mike-navy);
                position: absolute;
                top: 80px;
                left: 0;
                width: 100%;
                padding: 20px 0;
                border-top: 1px solid rgba(255,255,255,0.1);
                z-index: 1029;
            }

            .hero-card { padding: 2.5rem 1.2rem !important; margin-top: 10px; border-radius: 15px; }
            .hero-card h1 { font-size: 1.8rem !important; }
        }

        /* --- 4. Desktop Specific Styles --- */
        @media (min-width: 992px) {
            .navbar-center-text { display: none; }
            .sidebar { position: fixed; top: 80px; bottom: 0; left: 0; width: 260px; background: #fff; border-right: 1px solid #eee; z-index: 1000; overflow-y: auto; padding-top: 20px; }
            main, .footer-section { margin-left: 260px; padding: 40px; }
            .header-nav-link { color: #fff !important; font-weight: 600; text-transform: uppercase; padding: 0 15px !important; }
        }
    </style>
</head>
<body>

<header class="navbar navbar-expand-lg navbar-dark sticky-top shadow">
    <div class="container-fluid">
        <a class="navbar-brand p-0 m-0 d-flex align-items-center" href="index.php">
            <img src="<?php echo (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false) ? '' : '/'; ?>assets/logos/mike_of_all_trades_logo.jpg" 
                 class="mobile-logo rounded shadow-sm" alt="Logo">
            <span class="d-none d-lg-inline ms-3 fw-bold text-white">Mike Of All Trades</span>
        </a>

        <div class="navbar-center-text d-lg-none">Mike Of All Trades</div>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#topNavbar">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="topNavbar">
            <ul class="navbar-nav ms-auto mb-0">
                <li class="nav-item"><a class="nav-link header-nav-link" href="index.php">HOME</a></li>
                <li class="nav-item"><a class="nav-link header-nav-link" href="about.php">ABOUT</a></li>
            </ul>
        </div>
    </div>
</header>