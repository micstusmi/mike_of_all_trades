<?php 
    // This pulls in the standard high-end navbar and CSS
    include_once __DIR__ . '/includes/header.php'; 

    // This pulls in your master list of 14+ trades
    include_once __DIR__ . '/includes/sidebar.php'; 
?>

<main>
    <div class="hero-card" style="background: linear-gradient(135deg, var(--mike-orange), #d48100);">
        <h1 class="display-4 fw-bold mb-2">Photography</h1>
        <p class="fs-5 opacity-90">Capturing professional imagery for commercial and personal projects.</p>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm p-5 text-center text-muted h-100 d-flex align-items-center justify-content-center" style="border: 2px dashed #ccc !important; background: #fff;">
                <div>
                    <i class="bi bi-images fs-1"></i>
                    <p class="mt-2 fw-bold">Portfolio Gallery Coming Soon</p>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm p-4 h-100">
                <h5 class="fw-bold mb-3 border-bottom pb-2">Skills Include:</h5>
                <ul class="list-unstyled">
                    <li class="mb-3 text-muted">
                        <i class="bi bi-check2-circle text-warning me-2"></i>
                        <strong>Event Coverage</strong> <small>(Intermediate)</small>
                    </li>
                    <li class="mb-3 text-muted">
                        <i class="bi bi-check2-circle text-warning me-2"></i>
                        <strong>Portrait Photography</strong> <small>(Experienced)</small>
                    </li>
                    <li class="mb-3 text-muted">
                        <i class="bi bi-check2-circle text-warning me-2"></i>
                        <strong>Commercial Product</strong> <small>(Still learning)</small>
                    </li>
                    <li class="mb-3 text-muted">
                        <i class="bi bi-check2-circle text-warning me-2"></i>
                        <strong>Real Estate / Airbnb</strong> <small>(Still learning)</small>
                    </li>
                    <li class="mb-3 text-muted">
                        <i class="bi bi-check2-circle text-warning me-2"></i>
                        <strong>Weddings</strong> <small>(Still learning)</small>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</main>

<?php 
    // This pulls in the standard footer
    include_once __DIR__ . '/includes/footer.php'; 
?>