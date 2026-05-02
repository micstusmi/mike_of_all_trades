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

        /* --- 1. Navbar Structure --- */
        .navbar { 
            background-color: var(--mike-navy) !important; 
            z-index: 1031; 
            height: 80px; 
            padding: 0 !important; 
        }
        
        .navbar .container-fluid {
            position: relative; /* Essential for the absolute center text */
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: space-between; /* Pushes Logo left and Toggler right */
            padding: 0 20px !important; /* Forces symmetrical 20px gaps on LHS/RHS */
        }

        /* --- 2. Mobile Specific Styling --- */
        @media (max-width: 991.98px) {
            /* Logo Styling (LHS) */
            .mobile-logo {
                height: 45px !important; /* 20% larger than 35px */
                width: auto;
                display: block;
            }

            /* The "Alone" Center Text */
            .navbar-center-text {
                position: absolute;
                left: 50%;
                top: 50%;
                transform: translate(-50%, -50%); /* Locks to dead center of the 80px bar */
                color: #ffffff;
                font-weight: 700;
                font-size: 1.05rem;
                white-space: nowrap;
                letter-spacing: 0.5px;
                z-index: 1030;
                pointer-events: none; /* Prevents text from blocking burger clicks */
            }

            /* Hamburger Styling (RHS) */
            .navbar-toggler {
                border: 2px solid var(--mike-cyan) !important;
                box-shadow: 0 0 10px rgba(13, 202, 240, 0.3);
                padding: 4px 8px;
                z-index: 1032;
            }

            /* Layout Fixes */
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
            .navbar-nav .nav-item { padding: 12px 0; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.05); }
            .header-nav-link { font-size: 1.1rem; }
        }

        /* --- 3. Desktop Styles --- */
        @media (min-width: 992px) {
            .navbar-center-text { display: none; }
            .sidebar { position: fixed; top: 80px; bottom: 0; left: 0; width: 260px; background: #fff; border-right: 1px solid #eee; z-index: 1000; overflow-y: auto; padding-top: 20px; }
            main { margin-left: 260px; padding: 40px; }
            .footer-section { margin-left: 260px; background: #fff; border-top: 1px solid #eee; padding: 30px 40px; }
            .header-nav-link { color: #fff !important; font-weight: 600; text-transform: uppercase; padding: 0 15px !important; }
        }
    </style>
</head>
<body>

<header class="navbar navbar-expand-lg navbar-dark sticky-top shadow">
    <div class="container-fluid">
        <a class="navbar-brand p-0 m-0 d-flex align-items-center" href="index.php">
            <img src="<?php echo (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false) ? '' : '/'; ?>assets/logos/mike_of_all_trades_logo.jpg" 
                 class="mobile-logo rounded shadow-sm" alt="Mike Of All Trades Logo">
            <span class="d-none d-lg-inline ms-3 fw-bold">Mike Of All Trades</span>
        </a>

        <div class="navbar-center-text d-lg-none">
            Mike Of All Trades
        </div>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#topNavbar">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="topNavbar">
            <ul class="navbar-nav ms-auto mb-0">
                <li class="nav-item"><a class="nav-link header-nav-link" href="index.php">HOME</a></li>
                <li class="nav-item"><a class="nav-link header-nav-link" href="about.php">ABOUT</a></li>
                <li class="nav-item dropdown">
                    <a class="nav-link header-nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">SERVICES</a>
                    <ul class="dropdown-menu dropdown-menu-end dropdown-menu-dark shadow">
                        <li><h6 class="dropdown-header text-info small fw-bold">CREATIVE</h6></li>
                        <li><a class="dropdown-item" href="graphic_design.php">Graphic Design</a></li>
                        <li><a class="dropdown-item" href="photography.php">Photography</a></li>
                        <li><a class="dropdown-item" href="videography.php">Videography</a></li>
                        <li><hr class="dropdown-divider bg-secondary"></li>
                        <li><h6 class="dropdown-header text-info small fw-bold">TECHNICAL</h6></li>
                        <li><a class="dropdown-item" href="web_design.php">Web Design</a></li>
                        <li><a class="dropdown-item" href="mobile_phone_applications.php">Mobile Apps</a></li>
                        <li><a class="dropdown-item" href="it_work.php">IT Work</a></li>
                        <li><a class="dropdown-item" href="ecommerce.php">E-commerce</a></li>
                        <li><hr class="dropdown-divider bg-secondary"></li>
                        <li><h6 class="dropdown-header text-info small fw-bold">TRADES</h6></li>
                        <li><a class="dropdown-item" href="handyman.php">Handyman</a></li>
                        <li><a class="dropdown-item" href="property_maintenance.php">Property Maintenance</a></li>
                        <li><a class="dropdown-item" href="home_improvements.php">Home Improvements</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</header>