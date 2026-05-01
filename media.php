<?php include 'includes/header.php'; ?>

<style>
    .media-card {
        border-radius: 20px !important; /* Smoother rounded corners */
        overflow: hidden;
        transition: transform 0.3s ease;
        border: 1px solid rgba(255,255,255,0.1); /* Subtle edge highlight */
    }
    
    .media-card:hover {
        transform: translateY(-5px);
    }

    .media-card img {
        height: 400px; /* Slightly taller to give the text more room */
        object-fit: cover;
    }

    .card-img-overlay {
        /* Deepened the gradient for a stronger "scrim" at the bottom */
        background: linear-gradient(to top, 
            rgba(0,0,0,0.95) 0%, 
            rgba(0,0,0,0.6) 40%, 
            rgba(0,0,0,0) 100%);
        display: flex;
        flex-direction: column;
        justify-content: flex-end;
        border-radius: 20px;
    }

    /* High-Contrast Text Styling */
    .overlay-title {
        color: #0dcaf0 !important; /* Cyan for contrast */
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 1px;
        /* Triple-layered shadow for a crisp outline */
        text-shadow: 2px 2px 4px #000, -1px -1px 0 #000, 1px -1px 0 #000, -1px 1px 0 #000, 1px 1px 0 #000;
    }

    .overlay-blurb {
        color: #ffffff !important; /* Force pure white for the paragraph */
        font-weight: 500;
        line-height: 1.4;
        /* Deep drop shadow for the paragraph */
        text-shadow: 1px 1px 3px rgba(0,0,0,1), 0 0 10px rgba(0,0,0,0.8);
    }
</style>

<section class="py-5 bg-dark text-light">
    <div class="container mt-5">
        <h1 class="display-4 border-bottom border-primary pb-3 mb-5">Media Services</h1>
        <p class="lead mb-5">High-impact digital content, from photography to video production to 4K drone footage and more!</p>

        <div class="row g-4">
            
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border-0 shadow media-card">
                    <img src="images/services/photography.jpg" class="card-img" alt="Photography">
                    <div class="card-img-overlay p-4">
                        <h5 class="card-title fw-bold text-info overlay-text">Photography</h5>
                        <p class="card-text mb-3" style="color: #ffffff !important; text-shadow: 1px 1px 3px rgba(0,0,0,1); font-weight: 400; opacity: 1 !important;">
                            Capturing moments that tell your story with high-quality, professional imagery.</p>
                        <button class="btn btn-primary btn-sm w-50" data-bs-toggle="modal" data-bs-target="#quoteModal">Inquire</button>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border-0 shadow media-card">
                    <img src="images/services/videography.png" class="card-img" alt="Video Production">
                    <div class="card-img-overlay p-4">
                        <h5 class="card-title fw-bold text-info overlay-text">Video Production & Editing</h5>
                        <p class="card-text mb-3" style="color: #ffffff !important; text-shadow: 1px 1px 3px rgba(0,0,0,1); font-weight: 400; opacity: 1 !important;">
                        Specialising in events, promotional content, and corporate communications.</p>
                        <button class="btn btn-primary btn-sm w-50" data-bs-toggle="modal" data-bs-target="#quoteModal">Inquire</button>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border-0 shadow media-card">
                    <img src="images/services/drone.png" class="card-img" alt="Drone Footage">
                    <div class="card-img-overlay p-4">
                        <h5 class="card-title fw-bold text-info overlay-text">Drone Footage</h5>
                        <p class="card-text mb-3" style="color: #ffffff !important; text-shadow: 1px 1px 3px rgba(0,0,0,1); font-weight: 400; opacity: 1 !important;">
                            4K aerial footage for real estate, construction, and event coverage.</p>
                        <button class="btn btn-primary btn-sm w-50" data-bs-toggle="modal" data-bs-target="#quoteModal">Inquire</button>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border-0 shadow media-card">
                    <img src="images/services/ai_generated_media.jpg" class="card-img" alt="Digital Assets">
                    <div class="card-img-overlay p-4">
                        <h5 class="card-title fw-bold text-info overlay-text">AI Virtual Content Creation</h5>
                        <p class="card-text mb-3" style="color: #ffffff !important; text-shadow: 1px 1px 3px rgba(0,0,0,1); font-weight: 400; opacity: 1 !important;">
                            Custom AI-generated assets and digital imagery tailored for business marketing.</p>
                        <button class="btn btn-primary btn-sm w-50" data-bs-toggle="modal" data-bs-target="#quoteModal">Inquire</button>
                    </div>
                </div>
            </div>

        </div> </div>
</section>

<?php include 'includes/footer.php'; ?>