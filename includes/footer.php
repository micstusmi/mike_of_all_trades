<footer class="footer-section py-5 mt-5 border-top border-secondary bg-dark">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-6 text-center text-md-start mb-4 mb-md-0">
                <h4 class="mb-1" style="color: #0dcaf0; font-weight: 800; letter-spacing: 0.5px;">
                    MIKE OF ALL TRADES
                </h4>
                <p class="mb-2 text-secondary small uppercase tracking-wider">
                    ABN: 92 707 598 477
                </p>
                <p class="mb-0 text-muted small">
                    &copy; 2026 | Victoria, Australia
                </p>
            </div>

            <div class="col-md-6 text-center text-md-end">
                <div class="mb-3">
                    <a href="javascript:void(0);" 
                       class="btn btn-outline-info btn-sm px-4 rounded-pill fw-bold" 
                       data-bs-toggle="modal" 
                       data-bs-target="#quoteModal">
                       <i class="bi bi-chat-dots me-2"></i>Contact Us / Get a Quote
                    </a>
                </div>
                
                <div class="fs-4">
                    <a href="#" class="text-secondary hover-info me-3 transition-all"><i class="bi bi-instagram"></i></a>
                    <a href="#" class="text-secondary hover-info me-3 transition-all"><i class="bi bi-linkedin"></i></a>
                    <a href="mailto:mike@mikeofalltrades.com.au" class="text-secondary hover-info transition-all"><i class="bi bi-envelope"></i></a>
                </div>
            </div>
        </div>
    </div>
</footer>

<div class="modal fade" id="quoteModal" tabindex="-1" aria-labelledby="quoteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-dark text-white rounded-top-4">
                <h5 class="modal-title fw-bold" id="quoteModalLabel">Get a Quote</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form action="process_quote.php" method="POST">
                    
                    <input type="text" name="website_url" style="display:none !important" tabindex="-1" autocomplete="off">

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary text-uppercase">Name</label>
                        <input type="text" name="name" class="form-control rounded-3" placeholder="Enter your name" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary text-uppercase">Email</label>
                        <input type="email" name="email" class="form-control rounded-3" placeholder="name@example.com" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary text-uppercase">Project Type</label>
                        <select name="service" class="form-select rounded-3">
                            <option value="General Inquiry">General Inquiry</option>
                            <option value="Creative/Media">Creative / Media Production</option>
                            <option value="Technical/IT">Technical / IT Work</option>
                            <option value="Trades/Handyman">Trades / Handyman Services</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary text-uppercase">What is 5 + 5? (Spam Check)</label>
                        <input type="number" name="math_answer" class="form-control rounded-3" placeholder="Your answer" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-bold text-secondary text-uppercase">How can I help?</label>
                        <textarea name="message" class="form-control rounded-3" rows="4" placeholder="Describe your project details..." required></textarea>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-info text-white fw-bold py-3 rounded-pill shadow-sm">
                            SEND REQUEST
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>