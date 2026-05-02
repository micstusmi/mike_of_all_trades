<?php 
    // Pulls the consistent Navy Navbar and CSS
    include_once __DIR__ . '/includes/header.php'; 

    // Pulls your master trade list
    include_once __DIR__ . '/includes/sidebar.php'; 
?>

<main>
    <div class="hero-card">
        <h1 class="display-4 fw-bold mb-2">
        <span style="color: var(--mike-orange);">Property Maintenance</span>
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
                    <li class="mb-2"><i class="bi bi-check2-circle text-warning me-2"></i> Heavy-duty bollard installation (surface mount and core-drilled)</li>
                    <li class="mb-2"><i class="bi bi-check2-circle text-warning me-2"></i> Rubber and concrete wheel stop installation and anchoring</li>
                    <li class="mb-2"><i class="bi bi-check2-circle text-warning me-2"></i> Speed hump installation and traffic calming solutions</li>
                    <li class="mb-2"><i class="bi bi-check2-circle text-warning me-2"></i> Height clearance bar installation for undercover parking</li>
                    <li class="mb-2"><i class="bi bi-check2-circle text-warning me-2"></i> Convex safety mirror mounting for blind corners</li>
                    <li class="mb-2"><i class="bi bi-check2-circle text-warning me-2"></i> Corner guard and loading dock bumper protection</li>
                    <li class="mb-2"><i class="bi bi-check2-circle text-warning me-2"></i> Statutory and compliance signage (Fire, Exit, Accessible)</li>
                    <li class="mb-2"><i class="bi bi-check2-circle text-warning me-2"></i> Car park bay numbering and directional wayfinding signs</li>
                    <li class="mb-2"><i class="bi bi-check2-circle text-warning me-2"></i> Shopfront decal and promotional signage application</li>
                    <li class="mb-2"><i class="bi bi-check2-circle text-warning me-2"></i> Mounting of A-frame permanent fixtures and poster frames</li>
                    <li class="mb-2"><i class="bi bi-check2-circle text-warning me-2"></i> Lightbox maintenance and non-electrical sign repairs</li>
                    <li class="mb-2"><i class="bi bi-check2-circle text-warning me-2"></i> Commercial door closer adjustment and replacement</li>
                    <li class="mb-2"><i class="bi bi-check2-circle text-warning me-2"></i> Public restroom hardware maintenance (partitions, dispensers)</li>
                    <li class="mb-2"><i class="bi bi-check2-circle text-warning me-2"></i> High-traffic floor transition strip repairs and installation</li>
                    <li class="mb-2"><i class="bi bi-check2-circle text-warning me-2"></i> Ceiling tile replacement and grid repairs in retail spaces</li>
                    <li class="mb-2"><i class="bi bi-check2-circle text-warning me-2"></i> Graffiti removal and preventative anti-graffiti coatings</li>
                    <li class="mb-2"><i class="bi bi-check2-circle text-warning me-2"></i> Bulk high-pressure cleaning for walkways and loading bays</li>
                    <li class="mb-2"><i class="bi bi-check2-circle text-warning me-2"></i> Pre-sale property detailing and minor cosmetic upgrades</li>
                    <li class="mb-2"><i class="bi bi-check2-circle text-warning me-2"></i> End-of-lease repair checklists for rental properties</li>
                    <li class="mb-2"><i class="bi bi-check2-circle text-warning me-2"></i> Deck oiling, staining, and timber board replacement</li>
                    <li class="mb-2"><i class="bi bi-check2-circle text-warning me-2"></i> Pergola roof sheet replacement and leak sealing</li>
                    <li class="mb-2"><i class="bi bi-check2-circle text-warning me-2"></i> Fence and gate repairs including hinge and latch alignment</li>
                </ul>
            </div>
        </div>
    </div>
</main>

<div class="container my-5">
    <div class="p-5 text-white rounded-4 shadow-lg" style="background: linear-gradient(135deg, #1a2a3a 0%, #0d1a26 100%); border-left: 5px solid var(--mike-orange);">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <h2 class="fw-bold mb-3">Professional Facility Audits</h2>
                <p class="lead opacity-75">Proactive maintenance for shopping centres and commercial hubs. Serving Metro & Greater Melbourne Regions ONLY.</p>
            </div>
            <div class="col-lg-4 text-lg-end mt-4 mt-lg-0">
                <button type="button" class="btn btn-warning btn-lg rounded-pill px-5 py-3 fw-bold text-white border-0 shadow" data-bs-toggle="modal" data-bs-target="#auditModal" style="background-color: var(--mike-orange);">
                    Book Audit
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="auditModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-dark text-white">
        <h5 class="modal-title fw-bold">Melbourne Facility Audit Request</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4">
        <form action="process_audit.php" method="POST">
          
          <div style="display:none;">
            <input type="text" name="website_verification_field" value="">
          </div>

          <div class="mb-3">
            <label class="form-label small fw-bold">Contact Name</label>
            <input type="text" name="name" class="form-control" required placeholder="Your name">
          </div>
          <div class="mb-3">
            <label class="form-label small fw-bold">Shopping Centre / Facility Name</label>
            <input type="text" name="facility" class="form-control" required placeholder="e.g. Pakenham Central">
          </div>
          <div class="mb-3">
            <label class="form-label small fw-bold">Email Address</label>
            <input type="email" name="email" class="form-control" required placeholder="manager@centre.com.au">
          </div>
          <div class="mb-3">
            <label class="form-label small fw-bold">Brief Details</label>
            <textarea name="message" class="form-control" rows="3" placeholder="e.g. Bollard repairs and car park signage audit..."></textarea>
          </div>
          
          <div class="alert alert-secondary py-2 small">
            <i class="bi bi-geo-alt-fill text-danger me-2"></i> Note: Audits are strictly limited to the <strong>Melbourne Metro Area</strong>.
          </div>

          <button type="submit" class="btn btn-dark w-100 py-2 fw-bold">Send Audit Request</button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php 
    // Pulls the copyright bar and scripts
    include_once __DIR__ . '/includes/footer.php'; 
?>