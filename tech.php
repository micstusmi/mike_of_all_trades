<?php include 'includes/header.php'; ?>

<style>
    .tech-card {
        border-radius: 20px !important;
        overflow: hidden;
        transition: transform 0.3s ease;
        border: 1px solid rgba(255,255,255,0.1);
    }
    
    .tech-card:hover {
        transform: translateY(-5px);
    }

    .tech-card img {
        height: 400px;
        object-fit: cover;
    }

    .card-img-overlay {
        background: linear-gradient(to top, 
            rgba(0,0,0,0.95) 0%, 
            rgba(0,0,0,0.6) 40%, 
            rgba(0,0,0,0) 100%);
        display: flex;
        flex-direction: column;
        justify-content: flex-end;
        border-radius: 20px;
    }

    .overlay-title {
        color: #0dcaf0 !important;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 1px;
        text-shadow: 2px 2px 4px #000, -1px -1px 0 #000, 1px -1px 0 #000, -1px 1px 0 #000, 1px 1px 0 #000;
    }
</style>

<section class="py-5 bg-dark text-light">
    <div class="container mt-5">
        <h1 class="display-4 border-bottom border-primary pb-3 mb-5">Tech Infrastructure</h1>
        <p class="lead mb-5">Full-stack web development, secure server administration, and specialized IT solutions.</p>

        <div class="row g-4">
            
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border-0 shadow tech-card">
                    <img src="images/services/web_dev.png" class="card-img" alt="Web Development">
                    <div class="card-img-overlay p-4">
                        <h5 class="card-title fw-bold overlay-title">Full-Stack Development</h5>
                        <p class="card-text mb-3" style="color: #ffffff !important; text-shadow: 1px 1px 3px rgba(0,0,0,1); font-weight: 400;">
                            Custom web solutions built with modern stacks like Node.js and JavaScript.</p>
                        <button class="btn btn-primary btn-sm w-50" data-bs-toggle="modal" data-bs-target="#quoteModal">Inquire</button>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border-0 shadow tech-card">
                    <img src="images/services/server_admin.png" class="card-img" alt="Server Admin">
                    <div class="card-img-overlay p-4">
                        <h5 class="card-title fw-bold overlay-title">Cloud Infrastructure</h5>
                        <p class="card-text mb-3" style="color: #ffffff !important; text-shadow: 1px 1px 3px rgba(0,0,0,1); font-weight: 400;">
                            Secure AWS hosting, SSL certification, and Apache server management.</p>
                        <button class="btn btn-primary btn-sm w-50" data-bs-toggle="modal" data-bs-target="#quoteModal">Inquire</button>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border-0 shadow tech-card">
                    <img src="images/services/e-commerce.png" class="card-img" alt="Digital Solutions">
                    <div class="card-img-overlay p-4">
                        <h5 class="card-title fw-bold overlay-title">Digital Commerce</h5>
                        <p class="card-text mb-3" style="color: #ffffff !important; text-shadow: 1px 1px 3px rgba(0,0,0,1); font-weight: 400;">
                            E-commerce integration and high-performance digital platforms for business.</p>
                        <button class="btn btn-primary btn-sm w-50" data-bs-toggle="modal" data-bs-target="#quoteModal">Inquire</button>
                    </div>
                </div>
            </div>

                        <div class="col-md-6 col-lg-4">
                <div class="card h-100 border-0 shadow tech-card">
                    <img src="images/services/it_professional.png" class="card-img" alt="Digital Solutions">
                    <div class="card-img-overlay p-4">
                        <h5 class="card-title fw-bold overlay-title">IT Support</h5>
                        <p class="card-text mb-3" style="color: #ffffff !important; text-shadow: 1px 1px 3px rgba(0,0,0,1); font-weight: 400;">
                            IT Administration and technical support for all your business needs.</p>
                        <button class="btn btn-primary btn-sm w-50" data-bs-toggle="modal" data-bs-target="#quoteModal">Inquire</button>
                    </div>
                </div>
            </div>

        </div> 
    </div>
</section>

<?php include 'includes/footer.php'; ?>