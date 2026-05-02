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

        /* Top Navbar */
        .navbar { background-color: var(--mike-navy) !important; z-index: 1031; height: 60px; }
        
        .header-nav-link { 
            color: #ffffff !important; 
            font-weight: 600; 
            text-transform: uppercase; 
            letter-spacing: 1.5px; 
            font-size: 0.85rem; 
            padding: 0 20px !important; 
        }
        .header-nav-link:hover { color: var(--mike-orange) !important; }

        /* Hero Card - Restored Rounded Rectangle */
        .hero-card { 
            background: linear-gradient(135deg, var(--mike-navy), #2c3e50); 
            color: white; 
            border-radius: 20px; 
            padding: 3.5rem; 
            margin-bottom: 2rem; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.15); 
            border: 1px solid rgba(255,255,255,0.05);
        }

        /* Sidebar Styling */
        .sidebar { position: fixed; top: 60px; bottom: 0; left: 0; width: 260px; background: #fff; border-right: 1px solid #eee; z-index: 1000; overflow-y: auto; padding-top: 20px; }
        .sidebar .nav-link { color: #444; font-weight: 500; padding: 12px 25px; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { background: #f8f9fa; color: var(--mike-orange); }

        /* Content & Footer Spacing */
        main { margin-left: 260px; padding: 40px; flex: 1 0 auto; }
        .footer-section { margin-left: 260px; background: #fff; border-top: 1px solid #eee; padding: 30px 40px; flex-shrink: 0; }

        /* Dropdown Customization */
        .dropdown-menu-dark { 
            background-color: var(--mike-navy); 
            border: 1px solid rgba(255,255,255,0.1); 
            max-height: 80vh; /* Prevents overflow on smaller screens */
            overflow-y: auto;
        }
        .dropdown-header { color: var(--mike-orange) !important; font-weight: 800; letter-spacing: 1px; font-size: 0.7rem; padding-top: 15px; }
        .dropdown-item { font-size: 0.85rem; padding: 8px 20px; }

        @media (max-width: 992px) { 
            .sidebar { position: static; width: 100%; padding-top: 20px; } 
            main, .footer-section { margin-left: 0; padding: 20px; } 
        }
    </style>
</head>
<body>

<header class="navbar navbar-expand-lg navbar-dark sticky-top px-4 shadow">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold fs-4 d-flex align-items-center" href="index.php">
            <img src="assets/logos/mike_of_all_trades_logo.jpg" height="30" class="me-2 rounded shadow-sm" onerror="this.style.display='none'">
            <span>Mike Of All Trades</span>
        </a>

        <div class="collapse navbar-collapse" id="topNavbar">
            <ul class="navbar-nav ms-auto mb-0">
                <li class="nav-item"><a class="nav-link header-nav-link" href="index.php">HOME</a></li>
                <li class="nav-item"><a class="nav-link header-nav-link" href="about.php">ABOUT</a></li>
                
                <li class="nav-item dropdown">
                    <a class="nav-link header-nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        SERVICES
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end dropdown-menu-dark shadow">
                        <li><h6 class="dropdown-header">CREATIVE</h6></li>
                        <li><a class="dropdown-item" href="graphic_design.php">Graphic Design</a></li>
                        <li><a class="dropdown-item" href="photography.php">Photography</a></li>
                        <li><a class="dropdown-item" href="videography.php">Videography</a></li>
                        
                        <li><h6 class="dropdown-header">TECHNICAL</h6></li>
                        <li><a class="dropdown-item" href="web_design.php">Web Design</a></li>
                        <li><a class="dropdown-item" href="mobile_phone_applications.php">Mobile Apps</a></li>
                        <li><a class="dropdown-item" href="it_work.php">IT Work</a></li>
                        <li><a class="dropdown-item" href="ecommerce.php">E-commerce</a></li>
                        
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
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#topNavbar">
            <span class="navbar-toggler-icon"></span>
        </button>
    </div>
</header>