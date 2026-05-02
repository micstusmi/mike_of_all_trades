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
    
    body { 
        margin: 0; 
        display: flex; 
        flex-direction: column; 
        min-height: 100vh; 
        background-color: #f4f7f6; 
        font-family: 'Segoe UI', sans-serif; 
        font-size: 1.1rem; 
    }

    /* --- 1. Navbar Fixes --- */
    .navbar { background-color: var(--mike-navy) !important; z-index: 1031; height: 90px; padding: 0 !important; }
    
    .navbar .container-header {
        max-width: 96% !important; 
        margin: 0 auto;
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: space-between;
        position: relative;
        padding: 0 30px;
    }

    .navbar-brand img { height: 60px !important; width: auto; }
    
    .navbar-center-text {
        position: absolute;
        left: 50%;
        top: 50%;
        transform: translate(-50%, -50%);
        color: #ffffff;
        font-weight: 800;
        font-size: 1.6rem !important; 
        white-space: nowrap;
        z-index: 1030;
        letter-spacing: 1px;
    }

    .header-nav-link { 
        color: #ffffff !important; 
        font-weight: 700; 
        text-transform: uppercase; 
        font-size: 1.1rem !important; 
        padding: 0 20px !important; 
    }
    .header-nav-link:hover { color: var(--mike-orange) !important; }

    /* --- 2. THE HERO CARD (Restoring the "Pill" section) --- */
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
    .hero-card h1 { color: #ffffff !important; font-weight: 800; }
    .hero-card .highlight { color: var(--mike-orange) !important; }

    /* Restoration of the "GET QUOTE" Pill Button */
    .btn-pill {
        background-color: var(--mike-orange) !important;
        border: none !important;
        color: #fff !important;
        padding: 12px 30px !important;
        border-radius: 50px !important; /* Forces the "pill" shape */
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1px;
        transition: 0.3s ease;
    }
    .btn-pill:hover {
        transform: scale(1.05);
        background-color: #d48100 !important;
    }

/* --- 3. Sidebar Refinements (Slimmer & Cleaner) --- */
    .sidebar { 
        position: fixed; 
        top: 90px; 
        bottom: 0; 
        left: 0; 
        width: 280px; /* Kept slightly wider for overall layout balance */
        background: #fff; 
        border-right: 1px solid #eee; 
        z-index: 1000; 
        overflow-y: auto; 
        padding-top: 15px; /* Reduced top padding */
    }

    .sidebar .nav-link { 
        color: #444; 
        font-weight: 500; /* Reverted to a lighter, cleaner font weight */
        padding: 6px 40px; /* Reduced vertical padding from 12px to 6px */
        font-size: 0.95rem; 
        transition: all 0.2s ease;
    }

    .sidebar .nav-link:hover, .sidebar .nav-link.active { 
        background: #f8f9fa; 
        color: var(--mike-orange); 
        border-left: 4px solid var(--mike-orange); 
        padding-left: 36px; /* Adjusting padding to account for the border-left highlight */
    }

    .sidebar-heading { 
        padding: 12px 40px 4px; /* Tightened top/bottom spacing for categories */
        font-size: 0.7rem; 
        font-weight: 800; 
        color: #bbb; 
        text-transform: uppercase; 
        letter-spacing: 1.5px; 
    }

    /* --- 4. Content Spacing --- */
    @media (min-width: 992px) {
        main, .footer-section { 
            margin-left: 300px; 
            padding: 40px 80px; /* More side-padding for the main content */
        }
        .container { max-width: 95% !important; }
    }

    /* --- 5. Mobile Adjustments --- */
    @media (max-width: 991.98px) {
        .navbar-brand img { height: 50px !important; }
        .sidebar { display: none !important; } 
        main, .footer-section { margin-left: 0 !important; padding: 20px !important; }
    }
</style>
</head>
<body>

<header class="navbar navbar-expand-lg navbar-dark sticky-top shadow">
    <div class="container-header">
        <a class="navbar-brand p-0 m-0" href="index.php">
            <img src="<?php echo (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false) ? '' : '/'; ?>assets/logos/mike_of_all_trades_logo.jpg" class="rounded shadow-sm">
        </a>

        <div class="navbar-center-text">Mike Of All Trades</div>

        <div class="d-flex align-items-center">
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
                            <li><h6 class="dropdown-header">CREATIVE</h6></li>
                            <li><a class="dropdown-item" href="graphic_design.php">Graphic Design</a></li>
                            <li><a class="dropdown-item" href="photography.php">Photography</a></li>
                            <li><a class="dropdown-item" href="videography.php">Videography</a></li>
                            <li><hr class="dropdown-divider bg-secondary"></li>
                            <li><h6 class="dropdown-header">TECHNICAL</h6></li>
                            <li><a class="dropdown-item" href="web_design.php">Web Design</a></li>
                            <li><a class="dropdown-item" href="mobile_phone_applications.php">Mobile Apps</a></li>
                            <li><a class="dropdown-item" href="it_work.php">IT Work</a></li>
                            <li><a class="dropdown-item" href="ecommerce.php">E-commerce</a></li>
                            <li><hr class="dropdown-divider bg-secondary"></li>
                            <li><h6 class="dropdown-header">TRADES</h6></li>
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
    </div>
</header>

<div class="sidebar shadow-sm">
    <div class="sidebar-heading">Overview</div>
    <nav class="nav flex-column mb-3">
        <a class="nav-link" href="index.php"><i class="bi bi-grid-fill me-2"></i> Overview</a>
    </nav>

    <div class="sidebar-heading">Creative</div>
    <nav class="nav flex-column mb-3">
        <a class="nav-link" href="graphic_design.php"><i class="bi bi-palette me-2"></i> Graphic Design</a>
        <a class="nav-link" href="photography.php"><i class="bi bi-camera me-2"></i> Photography</a>
        <a class="nav-link" href="videography.php"><i class="bi bi-film me-2"></i> Videography</a>
    </nav>

    <div class="sidebar-heading">Technical</div>
    <nav class="nav flex-column mb-3">
        <a class="nav-link" href="web_design.php"><i class="bi bi-code-slash me-2"></i> Web Design</a>
        <a class="nav-link" href="mobile_phone_applications.php"><i class="bi bi-phone me-2"></i> Mobile Apps</a>
        <a class="nav-link" href="it_work.php"><i class="bi bi-cpu me-2"></i> IT Work</a>
        <a class="nav-link" href="ecommerce.php"><i class="bi bi-cart me-2"></i> E-commerce</a>
    </nav>

    <div class="sidebar-heading">Trades</div>
    <nav class="nav flex-column mb-4">
        <a class="nav-link" href="handyman.php"><i class="bi bi-tools me-2"></i> Handyman</a>
        <a class="nav-link" href="property_maintenance.php"><i class="bi bi-house me-2"></i> Maintenance</a>
        <a class="nav-link" href="home_improvements.php"><i class="bi bi-hammer me-2"></i> Home Improvements</a>
        <a class="nav-link" href="signage.php"><i class="bi bi-megaphone me-2"></i> Signage</a>
        <a class="nav-link" href="kitchen_and_bathroom_cabinets.php"><i class="bi bi-layout-sidebar me-2"></i> Kitchen & Cabinets</a>
        <a class="nav-link" href="flatpacks.php"><i class="bi bi-box me-2"></i> Flatpacks</a>
        <a class="nav-link" href="phone_and_data_cabling.php"><i class="bi bi-reception-4 me-2"></i> Phone & Data</a>
    </nav>
</div>