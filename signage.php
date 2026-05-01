<?php 
    // Pulls the consistent Navy Navbar and CSS
    include_once __DIR__ . '/includes/header.php'; 

    // Pulls your master trade list
    include_once __DIR__ . '/includes/sidebar.php'; 
?>

<main>
    <div class="hero-card">
        <h1 class="display-4 fw-bold mb-2">
        <span style="color: var(--mike-orange);">Signage</span>
        </h1>
        <p class="fs-5 opacity-90">
            A brief professional summary of this specific service.
        </p>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm p-5 text-center text-muted" style="border: 2px dashed #eee !important;">
                <i class="bi bi-folder2-open fs-1"></i>
                <p class="mt-2 fw-bold">Project Gallery & Case Studies Coming Soon</p>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm p-4 h-100">
                <h5 class="fw-bold mb-3 border-bottom pb-2">Core Expertise</h5>
                <ul class="list-unstyled text-muted">
                    <li class="mb-2"><i class="bi bi-check2-circle text-warning me-2"></i> Skill One</li>
                    <li class="mb-2"><i class="bi bi-check2-circle text-warning me-2"></i> Skill Two</li>
                    <li class="mb-2"><i class="bi bi-check2-circle text-warning me-2"></i> Skill Three</li>
                </ul>
            </div>
        </div>
    </div>
</main>

<?php 
    // Pulls the copyright bar and scripts
    include_once __DIR__ . '/includes/footer.php'; 
?>