<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isLocalhost = strpos($_SERVER['HTTP_HOST'], 'localhost') !== false;
$baseUrl = $isLocalhost ? '/mike_of_all_trades/' : '/';
?>

<div class="sidebar shadow-sm">
    <div class="sidebar-heading">Overview</div>
    <nav class="nav flex-column mb-3">
        <a class="nav-link" href="<?= $baseUrl ?>index.php"><i class="bi bi-grid-fill me-2"></i> Overview</a>
    </nav>

    <div class="sidebar-heading">Creative</div>
    <nav class="nav flex-column mb-3">
        <a class="nav-link" href="<?= $baseUrl ?>graphic_design.php"><i class="bi bi-palette me-2"></i> Graphic Design</a>
        <a class="nav-link" href="<?= $baseUrl ?>photography.php"><i class="bi bi-camera me-2"></i> Photography</a>
        <a class="nav-link" href="<?= $baseUrl ?>videography.php"><i class="bi bi-film me-2"></i> Videography</a>
    </nav>

    <div class="sidebar-heading">Technical</div>
    <nav class="nav flex-column mb-3">
        <a class="nav-link" href="<?= $baseUrl ?>web_design.php"><i class="bi bi-code-slash me-2"></i> Web Design</a>
        <a class="nav-link" href="<?= $baseUrl ?>mobile_phone_applications.php"><i class="bi bi-phone me-2"></i> Mobile Apps</a>
        <a class="nav-link" href="<?= $baseUrl ?>it_work.php"><i class="bi bi-cpu me-2"></i> IT Work</a>
        <a class="nav-link" href="<?= $baseUrl ?>ecommerce.php"><i class="bi bi-cart me-2"></i> E-commerce</a>
    </nav>

    <div class="sidebar-heading">Trades</div>
    <nav class="nav flex-column mb-3">
        <a class="nav-link" href="<?= $baseUrl ?>handyman.php"><i class="bi bi-tools me-2"></i> Handyman</a>
        <a class="nav-link" href="<?= $baseUrl ?>property_maintenance.php"><i class="bi bi-house-gear me-2"></i> Maintenance</a>
        <a class="nav-link" href="<?= $baseUrl ?>home_improvements.php"><i class="bi bi-hammer me-2"></i> Home Improvements</a>
        <a class="nav-link" href="<?= $baseUrl ?>signage.php"><i class="bi bi-megaphone me-2"></i> Signage</a>
        <a class="nav-link" href="<?= $baseUrl ?>kitchen_and_bathroom_cabinets.php"><i class="bi bi-layout-sidebar me-2"></i> Kitchen & Cabinets</a>
        <a class="nav-link" href="<?= $baseUrl ?>flatpacks.php"><i class="bi bi-box me-2"></i> Flatpacks</a>
        <a class="nav-link" href="<?= $baseUrl ?>phone_and_data_cabling.php"><i class="bi bi-reception-4 me-2"></i> Phone & Data</a>
    </nav>

    <div class="sidebar-heading">Business Tools</div>
    <nav class="nav flex-column mb-4">
        <a class="nav-link fw-bold text-primary" href="<?= $baseUrl ?>quotes_bookings.php">
            <i class="bi bi-calendar-check-fill me-2"></i>
            Bookings & Quotes
            <small class="text-warning" style="font-size:0.6rem;">(BETA)</small>
        </a>

        <?php if (!empty($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
            <a class="nav-link fw-bold text-warning" href="<?= $baseUrl ?>admin/dashboard.php">
                <i class="bi bi-calendar-week-fill me-2"></i>
                Admin Calendar
            </a>

            <a class="nav-link fw-bold text-info" href="<?php echo $baseUrl; ?>admin_ai_chats.php">
    <i class="bi bi-chat-dots-fill me-2"></i>
    AI Chat Submissions
</a>
<?php endif; ?>

        <?php if (!empty($_SESSION['user_id']) && ($_SESSION['user_role'] ?? '') !== 'admin'): ?>
            <a class="nav-link fw-bold text-info" href="<?= $baseUrl ?>customer/dashboard.php">
                <i class="bi bi-person-circle me-2"></i>
                My Bookings
            </a>
        <?php endif; ?>
    </nav>
</div>