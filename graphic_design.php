<?php 
    // 1. Pull in the "Head" and "Navbar" from your includes folder
    include_once __DIR__ . '/includes/header.php'; 

    // 2. Pull in the "Sidebar" (The master list of 14+ trades)
    include_once __DIR__ . '/includes/sidebar.php'; 
?>

<main>
    <div class="hero-card" style="background: linear-gradient(135deg, #2c3e50, #000000);">
        <h1 class="display-4 fw-bold mb-2">Graphic <span style="color: var(--mike-orange);">Design</span></h1>
        <p class="fs-5 opacity-90">Visual identity, branding, and professional layout services.</p>
    </div>

    <div class="row g-4">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm p-4 h-100">
                <h5 class="fw-bold"><i class="bi bi-intersect me-2 text-danger"></i> Branding & Logos</h5>
                <p class="text-muted">Developing unique visual identities that resonate with your target audience.</p>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-0 shadow-sm p-4 h-100">
                <h5 class="fw-bold"><i class="bi bi-file-earmark-pdf me-2 text-danger"></i> Print & Marketing</h5>
                <p class="text-muted">Brochures, business cards, and large-format signage layouts ready for production.</p>
            </div>
        </div>
    </div>
</main>

<?php 
    // 3. Pull in the "Footer"
    include_once __DIR__ . '/includes/footer.php'; 
?>