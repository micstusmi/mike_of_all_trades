<?php 
    include_once __DIR__ . '/includes/header.php'; 
?>

<main>
    <div class="hero-card">
        <h1 class="display-3 fw-bold mb-3">One Mike. <span style="color: var(--mike-orange);">Many Talents.</span></h1>
        <p class="fs-4 opacity-75">Digital creation, technical infrastructure, and hands-on trade expertise.</p>
        <div class="mt-4 d-grid d-md-block gap-2">
    <button class="btn btn-warning rounded-pill px-4 py-2 fw-bold text-white border-0 shadow-sm" style="background-color: var(--mike-orange);">View Work</button>
    <button type="button" class="btn btn-outline-light rounded-pill px-4 py-2" data-bs-toggle="modal" data-bs-target="#quoteModal">
    Get Quote
    </button>
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

<div class="modal fade" id="quoteModal" tabindex="-1" aria-labelledby="quoteModalLabel" aria-hidden="true" style="color: #333;">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="quoteModalLabel">Request a Quote</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form action="process-quote.php" method="POST">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Name</label>
            <input type="text" name="name" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Service Required</label>
            <select name="service" class="form-select">
              <option value="Property Maintenance">Property Maintenance</option>
              <option value="Signage">Signage / Bollards</option>
              <option value="Audio Visual">Audio Visual / IT</option>
              <option value="General Trade">Other Trade Services</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Brief Description</label>
            <textarea name="message" class="form-control" rows="3" required></textarea>
          </div>

          <div style="display:none;">
            <input type="text" name="website_url" value="">
          </div>

          <div class="bg-light p-3 rounded border">
            <label class="form-label"><strong>Human Check:</strong> What is 6 + 4?</label>
            <input type="number" name="math_answer" class="form-control" placeholder="Answer" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-primary w-100">Send Quote Request</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php 
    include_once __DIR__ . '/includes/sidebar.php'; 
    include_once __DIR__ . '/includes/footer.php';
?>