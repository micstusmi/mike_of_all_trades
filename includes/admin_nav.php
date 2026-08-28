<?php
declare(strict_types=1);

$base = ($_SERVER['HTTP_HOST'] ?? '') === 'localhost'
    ? '/mike_of_all_trades'
    : '';

$current = $_SERVER['REQUEST_URI'] ?? '';

function admin_nav_active(string $needle, string $current): string {
    return str_contains($current, $needle) ? ' active' : '';
}
?>

<style>
.admin-nav{
    background:#111827;
    border-bottom:1px solid #374151;
    padding:10px 14px;
    position:sticky;
    top:0;
    z-index:1000;
}

.admin-nav-inner{
    max-width:1200px;
    margin:0 auto;
    display:flex;
    flex-wrap:wrap;
    gap:8px;
    align-items:center;
}

.admin-nav a{
    color:#fff;
    text-decoration:none;
    padding:8px 12px;
    border-radius:8px;
    font-size:14px;
    background:#1f2937;
}

.admin-nav a:hover,
.admin-nav a.active{
    background:#f39200;
    color:#111;
}

.admin-nav-spacer{
    flex:1;
}

@media (max-width:700px){
    .admin-nav{
        position:static;
    }

    .admin-nav-inner{
        gap:6px;
    }

    .admin-nav a{
        flex:1 1 auto;
        text-align:center;
        font-size:13px;
        padding:8px;
    }

    .admin-nav-spacer{
        display:none;
    }
}
</style>

<nav class="admin-nav">
    <div class="admin-nav-inner">

        <a class="<?=admin_nav_active('/admin/dashboard.php', $current)?>"
           href="<?=$base?>/admin/dashboard.php">
            Calendar
        </a>

        <a class="<?=admin_nav_active('/admin/work/', $current)?>"
           href="<?=$base?>/admin/work/index.php">
            Work Tracker
        </a>

        <a href="<?=$base?>/admin/work/new.php">
            + New Job
        </a>

        <a class="<?=admin_nav_active('/admin/ai_estimates', $current)?>"
           href="<?=$base?>/admin/ai_estimates.php">
            AI Estimates
        </a>

        <a class="<?=admin_nav_active('/admin/special_customer_invite.php', $current)?>"
           href="<?=$base?>/admin/special_customer_invite.php">
            Customer Invite
        </a>

        <span class="admin-nav-spacer"></span>

        <a href="<?=$base?>/" target="_blank">
            Website
        </a>

        <a href="<?=$base?>/logout.php">
            Logout
        </a>

    </div>
</nav>