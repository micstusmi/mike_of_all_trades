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

    /* --- 1. Top Navbar --- */
    .navbar { background-color: var(--mike-navy) !important; z-index: 1031; height: 75px; }
    
    .header-nav-link { 
        color: #ffffff !important; 
        font-weight: 600; 
        text-transform: uppercase; 
        letter-spacing: 1.5px; 
        font-size: 0.85rem; 
        padding: 0 20px !important; 
    }

    /* --- 2. MOBILE FIXES: Centering, Symmetry & Logo Size --- */
    @media (max-width: 991.98px) {
        .navbar .container-fluid {
            position: relative; /* Base for centering */
            display: flex;
            align-items: center;
            padding-left: 20px !important; /* Equalized edge distance */
            padding-right: 20px !important; /* Equalized edge distance */
        }

        .navbar-brand {
            /* This absolute centering locks the logo+text to the dead center */
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            margin: 0 !important;
            padding: 0 !important;
            display: flex !important;
            align-items: center;
            white-space: nowrap;
        }

        .navbar-brand img {
            height: 42px !important; /* Bumped up ~20% from 35px */
        }

        .navbar-brand span {
            font-size: 1.1rem; /* Slightly smaller text to ensure it fits mobile widths */
        }

        .navbar-toggler {
            margin-left: auto !important; /* Pushes burger to the right edge */
            border: 2px solid var(--mike-cyan) !important;
            box-shadow: 0 0 10px rgba(13, 202, 240, 0.4);
            z-index: 1032; /* Ensures burger stays on top */
        }

        /* Redundant Sidebar Hidden */
        .sidebar { display: none !important; }
        main, .footer-section { margin-left: 0 !important; padding: 20px !important; }

        /* Menu Content Styling */
        .navbar-collapse {
            background-color: var(--mike-navy);
            margin-top: 12px;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        .navbar-nav .nav-item {
            padding: 15px 0;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            text-align: center;
        }
    }

    /* --- 3. Desktop & Shared Styles --- */
    .hero-card { background: linear-gradient(135deg, var(--mike-navy), #2c3e50); color: white; border-radius: 20px; padding: 3.5rem; margin-bottom: 2rem; box-shadow: 0 10px 30px rgba(0,0,0,0.15); }
    .sidebar { position: fixed; top: 75px; bottom: 0; left: 0; width: 260px; background: #fff; border-right: 1px solid #eee; z-index: 1000; overflow-y: auto; padding-top: 20px; }
    main { margin-left: 260px; padding: 40px; flex: 1 0 auto; }
    .footer-section { margin-left: 260px; background: #fff; border-top: 1px solid #eee; padding: 30px 40px; }
    .dropdown-menu-dark { background-color: var(--mike-navy); max-height: 80vh; overflow-y: auto; }
</style>

<header class="navbar navbar-expand-lg navbar-dark sticky-top shadow">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold d-flex align-items-center" href="index.php">
            <img src="<?php echo (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false) ? '' : '/'; ?>assets/logos/mike_of_all_trades_logo.jpg" 
            height="35" 
            class="me-2 rounded shadow-sm">
            <span>Mike Of All Trades</span>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#topNavbar">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="topNavbar">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link header-nav-link" href="index.php">HOME</a></li>
                <li class="nav-item"><a class="nav-link header-nav-link" href="about.php">ABOUT</a></li>
                <li class="nav-item dropdown">
                    <a class="nav-link header-nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">SERVICES</a>
                    <ul class="dropdown-menu dropdown-menu-end dropdown-menu-dark shadow">
                        <li><h6 class="dropdown-header text-info">CREATIVE</h6></li>
                        <li><a class="dropdown-item" href="graphic_design.php">Graphic Design</a></li>
                        <li><a class="dropdown-item" href="photography.php">Photography</a></li>
                        <li><a class="dropdown-item" href="videography.php">Videography</a></li>
                        <li><h6 class="dropdown-header text-info">TECHNICAL</h6></li>
                        <li><a class="dropdown-item" href="web_design.php">Web Design</a></li>
                        <li><a class="dropdown-item" href="mobile_phone_applications.php">Mobile Apps</a></li>
                        <li><a class="dropdown-item" href="it_work.php">IT Work</a></li>
                        <li><a class="dropdown-item" href="ecommerce.php">E-commerce</a></li>
                        <li><h6 class="dropdown-header text-info">TRADES</h6></li>
                        <li><a class="dropdown-item" href="handyman.php">Handyman</a></li>
                        <li><a class="dropdown-item" href="property_maintenance.php">Property Maintenance</a></li>
                        <li><a class="dropdown-item" href="home_improvements.php">Home Improvements</a></li>
                        <li><a class="dropdown-item" href="signage.php">Signage</a></li>
                        <li><a class="dropdown-item" href="kitchen_and_bathroom_cabinets.php">Kitchen & Cabinets</a></li>
                        <li><a class="dropdown-item" href="flatpacks.php">Flatpacks</a></li>
                        <li><a class="dropdown-item" href="phone_and_data_cabling.php">Phone & Data</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</header>