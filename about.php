<?php include 'includes/header.php'; ?>

<main>
    <div class="container-fluid">
        <div class="hero-card shadow">
            <h1 class="display-4 fw-bold">About <span class="highlight">Mike Of All Trades</span></h1>
            <p class="lead opacity-75 mb-4">Three decades of multi-disciplinary expertise. One dedicated point of contact.</p>
            <button class="btn btn-outline-light rounded-pill px-4 fw-bold" data-bs-toggle="modal" data-bs-target="#quoteModal">
                GET QUOTE
            </button>
        </div>

        <div class="row g-5">
            <div class="col-lg-7">
                <section class="mb-5">
                    <h2 class="text-navy fw-bold mb-4 border-bottom border-info pb-2">Mike's Journey</h2>
                    <p class="text-muted">
                        Based in <strong>South East Melbourne, Victoria</strong>, 'Mike Of All Trades' is the culmination of my lifelong passion for learning as much as I can (about everything!) and then putting those lessons into practical use. From my early days where I grew up on a farm in South Gippsland where we had to make / repair most things for ourselves, plus also building upon my computer / IT enthusiasm to my extensive experience in digital marketing, videography, and property maintenance, I've always been driven by a desire to solve problems and create value across various physical and technical domains. 
                    </p>
                    <p class="text-muted">
                        You could say that the business 'Mike Of All Trades' was an inevitable evolutionary outcome. With my diverse range of skills, professional-grade tools & equipment, and a genuine passion for problem-solving, I realised that I can supply my services to modern businesses / homeowners so that they don't have to juggle a dozen different contractors for a single project. I provide a single, expert point of contact to get the job done right.
                    </p>
                    <p class="text-muted">
                        With over <strong>30 years of industry experience</strong>, I've cultivated a unique intersection of skills. Whether it's troubleshooting a complex <strong>Network Administration</strong> issue in the morning, shooting <strong>4K corporate videography</strong> in the afternoon, or installing <strong>custom cabinetry</strong> the following day, the standard remains the same: precision, reliability, and professional Australian standards.
                    </p>
                </section>

                <section class="mb-5">
                    <h3 class="fw-bold mb-3 text-info">My Philosophy: "One Contractor (Me, Mike). Many Solutions."</h3>
                    <p class="text-muted">
                        I believe that technical proficiency in one area breeds excellence in others. The same attention to detail required for <strong>SEO and Web Development</strong> is applied to <strong>Property Maintenance and Trades</strong>. I don't just "fix things"—I integrate solutions that work for your lifestyle or your business's bottom line.
                    </p>
                </section>
            </div>

            <div class="col-lg-5">
                <div class="card border-0 shadow-sm rounded-4 p-4 mb-4" style="background: #fff;">
                    <h4 class="fw-bold text-navy mb-3"><i class="bi bi-shield-check text-success me-2"></i>Business Credentials</h4>
                    <ul class="list-unstyled mb-0">
                        <li class="mb-3">
                            <strong>ABN:</strong> <span class="text-primary fw-bold">92 707 598 477</span>
                        </li>
                        <li class="mb-3">
                            <strong>Location:</strong> Pakenham, VIC 3810 (Serving South East Melbourne, Greater Melbourne and Surrounding Areas)
                        </li>
                        <li class="mb-3">
                            <strong>Specialties:</strong> IT Infrastructure, Digital Marketing, & Specialised Trade Labour
                        </li>
                        <li>
                            <strong>Insurance:</strong> Fully Insured for Residential & Commercial Work
                        </li>
                        <li>
                            <strong>Safety Induction:</strong> White Card Holder for Construction Work
                        </li>
                        <li>
                            <strong>Safety License:</strong> Working at Heights License Holder
                        </li>
                        <li>
                            <strong>Children's Safety:</strong> WWCC (Working With Children Check) Clearance Holder
                        </li>





                    </ul>
                </div>

                <div class="card border-0 shadow-sm rounded-4 p-4 text-white" style="background: var(--mike-navy);">
                    <h4 class="fw-bold mb-3 text-info">Service Pillars</h4>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge bg-info text-dark px-3 py-2">Creative</span>
                        <span class="badge bg-info text-dark px-3 py-2">Technical</span>
                        <span class="badge bg-info text-dark px-3 py-2">Trades</span>
                        <span class="badge bg-outline-info border border-info px-3 py-2">E-commerce</span>
                        <span class="badge bg-outline-info border border-info px-3 py-2">Property Maintenance</span>
                    </div>
                </div>
            </div>
        </div>

        <hr class="my-5 opacity-10">

        <div class="row text-center g-4 mb-5">
            <div class="col-md-4">
                <div class="p-4 bg-white rounded-4 shadow-sm h-100">
                    <i class="bi bi-palette fs-1 text-info mb-3"></i>
                    <h5 class="fw-bold text-navy">Creative Excellence</h5>
                    <p class="small text-muted">From high-end graphic design to cinematic video production using industry-leading Canon and DJI hardware.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-4 bg-white rounded-4 shadow-sm h-100">
                    <i class="bi bi-cpu fs-1 text-info mb-3"></i>
                    <h5 class="fw-bold text-navy">Technical Precision</h5>
                    <p class="small text-muted">Full-stack web development, SEO strategies, and robust IT network administration for modern businesses.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-4 bg-white rounded-4 shadow-sm h-100">
                    <i class="bi bi-tools fs-1 text-info mb-3"></i>
                    <h5 class="fw-bold text-navy">Trade Reliability</h5>
                    <p class="small text-muted">A comprehensive range of handyman and property maintenance services, from lock repairs to data cabling.</p>
                </div>
            </div>
        </div>

        <div class="text-center py-5 rounded-4 mb-5" style="background: rgba(13, 202, 240, 0.1);">
            <h2 class="fw-bold text-navy">Ready to start your next project?</h2>
            <p class="lead text-muted mb-4">Get the expertise you need without the overhead of multiple contractors.</p>
            <button class="btn btn-info rounded-pill px-5 py-3 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#quoteModal">
                REQUEST A QUOTE
            </button>
        </div>
    </div>
</main>

<style>
    .text-navy { color: var(--mike-navy); }
    .highlight { color: var(--mike-orange); }
    .hero-card .btn-outline-light:hover { background-color: #fff; color: var(--mike-navy); }
</style>

<?php include 'includes/footer.php'; ?>