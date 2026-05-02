<?php include 'includes/header.php'; ?>

<style>
    .trade-card {
        border-radius: 20px !important;
        overflow: hidden;
        transition: transform 0.3s ease;
        border: 1px solid rgba(255,255,255,0.1);
    }
    
    .trade-card:hover {
        transform: translateY(-5px);
    }

    .trade-card img {
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
        <h1 class="display-4 border-bottom border-primary pb-3 mb-5">Trade Services</h1>
        <p class="lead mb-5">Quality workmanship and reliable technical labour, from property maintenance to specialised installations.</p>

        <div class="row g-4">
            
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border-0 shadow trade-card">
                    <img src="images/services/handyman.png" class="card-img" alt="Handyman Services">
                    <div class="card-img-overlay p-4">
                        <h5 class="card-title fw-bold overlay-title">General Handyman</h5>
                        <p class="card-text mb-3" style="color: #ffffff !important; text-shadow: 1px 1px 3px rgba(0,0,0,1); font-weight: 400;">
                            Expert repairs, home improvements, and general property maintenance.</p>
                        <button class="btn btn-primary btn-sm w-50" data-bs-toggle="modal" data-bs-target="#quoteModal">Inquire</button>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border-0 shadow trade-card">
                    <img src="images/services/signage_bollards.png" class="card-img" alt="Signage">
                    <div class="card-img-overlay p-4">
                        <h5 class="card-title fw-bold overlay-title">Signage & Bollards</h5>
                        <p class="card-text mb-3" style="color: #ffffff !important; text-shadow: 1px 1px 3px rgba(0,0,0,1); font-weight: 400;">
                            Professional installation of commercial signage, bollards, and safety fixtures.</p>
                        <button class="btn btn-primary btn-sm w-50" data-bs-toggle="modal" data-bs-target="#quoteModal">Inquire</button>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border-0 shadow trade-card">
                    <img src="images/services/cabinets.png" class="card-img" alt="Kitchen Cabinets">
                    <div class="card-img-overlay p-4">
                        <h5 class="card-title fw-bold overlay-title">Kitchen & Cabinets</h5>
                        <p class="card-text mb-3" style="color: #ffffff !important; text-shadow: 1px 1px 3px rgba(0,0,0,1); font-weight: 400;">
                            Cabinetry installs, flatpack assembly, and custom kitchen upgrades.</p>
                        <button class="btn btn-primary btn-sm w-50" data-bs-toggle="modal" data-bs-target="#quoteModal">Inquire</button>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border-0 shadow trade-card">
                    <img src="images/services/data_cabling.png" class="card-img" alt="Data Cabling">
                    <div class="card-img-overlay p-4">
                        <h5 class="card-title fw-bold overlay-title">Phone & Data</h5>
                        <p class="card-text mb-3" style="color: #ffffff !important; text-shadow: 1px 1px 3px rgba(0,0,0,1); font-weight: 400;">
                            Cabling solutions, data point installation, and home network wiring.</p>
                        <button class="btn btn-primary btn-sm w-50" data-bs-toggle="modal" data-bs-target="#quoteModal">Inquire</button>
                    </div>
                </div>
            </div>

        </div> 
    </div>
</section>

<?php include 'includes/footer.php'; ?>