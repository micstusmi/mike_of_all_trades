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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
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

<?php include __DIR__ . '/sidebar.php'; ?>