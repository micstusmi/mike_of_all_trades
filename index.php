<?php 
    include_once __DIR__ . '/includes/header.php'; 
    include_once __DIR__ . '/includes/sidebar.php'; 
?>

<main>
    <div class="hero-card">
        <h1 class="display-3 fw-bold mb-3">One Mike. <span style="color: var(--mike-orange);">Every Solution.</span></h1>
        <p class="fs-4 opacity-75">Digital creation, technical infrastructure, and hands-on trade expertise.</p>
        <div class="mt-4 d-grid d-md-block gap-2">
    <button class="btn btn-warning rounded-pill px-4 py-2 fw-bold text-white border-0 shadow-sm" style="background-color: var(--mike-orange);">View Work</button>
    <button class="btn btn-outline-light rounded-pill px-4 py-2">Get Quote</button>
        </div>
    </div>

    <div class="row g-4 text-center">
        <div class="col-md-3">
            <div class="card p-4 h-100 skill-card border-0">
                <i class="bi bi-camera-reels fs-1 mb-3" style="color: var(--mike-orange);"></i>
                <h5 class="fw-bold">Media</h5>
                <p class="text-muted small">Photography & Video</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card p-4 h-100 skill-card border-0">
                <i class="bi bi-palette fs-1 text-danger mb-3"></i>
                <h5 class="fw-bold">Design</h5>
                <p class="text-muted small">Branding & Graphics</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card p-4 h-100 skill-card border-0">
                <i class="bi bi-laptop fs-1 text-primary mb-3"></i>
                <h5 class="fw-bold">Tech</h5>
                <p class="text-muted small">Web & IT Support</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card p-4 h-100 skill-card border-0">
                <i class="bi bi-tools fs-1 text-success mb-3"></i>
                <h5 class="fw-bold">Trades</h5>
                <p class="text-muted small">Signage & Handyman</p>
            </div>
        </div>
    </div>
</main>

<?php include_once __DIR__ . '/includes/footer.php'; ?>