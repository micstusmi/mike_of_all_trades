<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mike Of All Trades | Portfolio</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root { --mike-orange: #f39200; --mike-navy: #1a252f; }
        body { margin: 0; display: flex; flex-direction: column; min-height: 100vh; background-color: #f4f7f6; font-family: 'Segoe UI', Tahoma, sans-serif; }
        .navbar { background-color: var(--mike-navy) !important; z-index: 1030; }
        .sidebar { position: fixed; top: 0; bottom: 0; left: 0; width: 260px; background: #fff; border-right: 1px solid #eee; padding-top: 70px; z-index: 1000; box-shadow: 2px 0 5px rgba(0,0,0,0.02); overflow-y: auto; }
        .nav-link { color: #444; font-weight: 500; padding: 12px 25px; }
        .nav-link:hover, .nav-link.active { background: #f8f9fa; color: var(--mike-orange); }
        .nav-link i { margin-right: 10px; color: #888; }
        main { margin-left: 260px; padding: 40px; flex: 1; }
        .footer-section { margin-left: 260px; background: #fff; border-top: 1px solid #eee; padding: 30px 40px; }
        .hero-card { background: linear-gradient(135deg, var(--mike-navy), #2c3e50); color: white; border-radius: 20px; padding: 4rem; margin-bottom: 2rem; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        .skill-card { border: none; border-radius: 15px; transition: transform 0.3s; background: white; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .skill-card:hover { transform: translateY(-5px); }
        @media (max-width: 992px) { .sidebar { position: static; width: 100%; padding-top: 20px; border-right: none; border-bottom: 1px solid #eee; } main, .footer-section { margin-left: 0; padding: 20px; } }
    </style>
</head>
<body>
<header class="navbar navbar-dark sticky-top px-4 py-2 shadow">
    <a class="navbar-brand fw-bold fs-4" href="index.php">
        <img src="assets/logos/mike_of_all_trades_logo.jpg" height="35" class="me-2 rounded shadow-sm" onerror="this.style.display='none'">
        Mike Of All Trades
    </a>
    <input class="form-control w-50 rounded-pill border-0 bg-dark text-white px-4" type="text" id="skillSearch" placeholder="Search my 50+ skills...">
</header>