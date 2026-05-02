<?php include 'includes/header.php'; ?>

<style>
    .service-card {
        border-radius: 20px !important;
        overflow: hidden;
        transition: transform 0.3s ease;
        border: 1px solid rgba(255,255,255,0.1);
    }
    .service-card:hover { transform: translateY(-5px); }
    .service-card img { height: 400px; object-fit: cover; }

    .card-img-overlay {
        background: linear-gradient(to top, rgba(0,0,0,0.95) 0%, rgba(0,0,0,0.6) 40%, rgba(0,0,0,0) 100%);
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
        <h1 class="display-4 border-bottom border-primary pb-3 mb-5">Design Services</h1>
        <p class="lead mb-5">Visual storytelling and brand identity crafted with over two decades of industry expertise.</p>

        <div class="row g-4">
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border-0 shadow service-card">
                    <img src="images/services/graphic_design.png" class="card-img" alt="Graphic Design">
                    <div class="card-img-overlay p-4">
                        <h5 class="card-title fw-bold overlay-title">Graphic Design</h5>
                        <p class="card-text mb-3" style="color: #ffffff !important; text-shadow: 1px 1px 3px rgba(0,0,0,1); font-weight: 400;">
                            20+ Years of experience creating compelling visuals for branding and marketing.</p>
                        <button class="btn btn-primary btn-sm w-50" data-bs-toggle="modal" data-bs-target="#quoteModal">Inquire</button>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border-0 shadow service-card">
                    <img src="images/services/storyboarding.png" class="card-img" alt="Storyboarding">
                    <div class="card-img-overlay p-4">
                        <h5 class="card-title fw-bold overlay-title">Creative Storyboarding</h5>
                        <p class="card-text mb-3" style="color: #ffffff !important; text-shadow: 1px 1px 3px rgba(0,0,0,1); font-weight: 400;">
                            Detailed (visual) planning for video creation for digital media marketing, etc.</p>
                        <button class="btn btn-primary btn-sm w-50" data-bs-toggle="modal" data-bs-target="#quoteModal">Inquire</button>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border-0 shadow service-card">
                    <img src="images/services/digital_assets.png" class="card-img" alt="Digital Assets">
                    <div class="card-img-overlay p-4">
                        <h5 class="card-title fw-bold overlay-title">Brand Identity</h5>
                        <p class="card-text mb-3" style="color: #ffffff !important; text-shadow: 1px 1px 3px rgba(0,0,0,1); font-weight: 400;">
                            Developing & maintaining colour themes / style guides for branding for modern businesses.</p>
                        <button class="btn btn-primary btn-sm w-50" data-bs-toggle="modal" data-bs-target="#quoteModal">Inquire</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>