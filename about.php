<?php include 'includes/header.php'; ?>

<main>
    <div class="container-fluid">
        <div class="row justify-content-center">
            <div class="col-xxl-9 col-xl-10">
                
                <div class="hero-card shadow mt-3">
                    <h1 class="display-5 fw-bold">About <span class="highlight">Mike Of All Trades</span></h1>
                    <p class="lead">Three decades of multi-disciplinary expertise.<br>One dedicated point of contact.</p>
                    
                    <button class="btn btn-outline-info rounded-pill px-4 py-2 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#quoteModal">
                        GET A QUOTE
                    </button>
                </div>

        <div class="row justify-content-center">
            <div class="col-xxl-9 col-xl-10">
                
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
                                <li class="mb-2"><strong>ABN:</strong> <span class="text-primary fw-bold">92 707 598 477</span></li>
                                <li class="mb-2"><strong>Location:</strong> Pakenham, VIC (South East Melbourne & Surrounds)</li>
                                <li class="mb-2"><strong>Specialties:</strong> IT, Digital Marketing, & Specialised Trade</li>
                                <li class="mb-2"><strong>Insurance:</strong> Fully Insured (Residential & Commercial)</li>
                                <li class="mb-2"><i class="bi bi-check2-circle text-info me-2"></i>White Card Holder (Construction)</li>
                                <li class="mb-2"><i class="bi bi-check2-circle text-info me-2"></i>Working at Heights License</li>
                                <li><i class="bi bi-check2-circle text-info me-2"></i>WWCC Clearance Holder</li>
                            </ul>
                        </div>

                        <div class="card border-0 shadow-sm rounded-4 p-4 text-white" style="background: var(--mike-navy);">
                            <h4 class="fw-bold mb-3 text-info">Service Pillars</h4>
                            <div class="d-flex flex-wrap gap-2">
                                <span class="badge bg-info text-dark px-3 py-2">Creative</span>
                                <span class="badge bg-info text-dark px-3 py-2">Technical</span>
                                <span class="badge bg-info text-dark px-3 py-2">Trades</span>
                                <span class="badge border border-info px-3 py-2">E-commerce</span>
                                <span class="badge border border-info px-3 py-2">Maintenance</span>
                            </div>
                        </div>
                    </div>
                </div>

                <hr class="my-5 opacity-10">

                <section class="mb-5">
                    <div class="row align-items-center mb-4">
                        <div class="col-md-6 text-center text-md-start">
                            <h2 class="text-navy fw-bold mb-0">Client Reviews</h2>
                            <p class="text-muted small">Verified 5.0 Rating on Airtasker</p>
                        </div>
                        <div class="col-md-6 text-md-end text-center">
                            <div class="d-inline-block bg-white p-2 px-3 rounded-4 shadow-sm border">
                                <span class="fw-bold text-navy me-2">5.0</span>
                                <span class="text-warning"><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i></span>
                                <span class="ms-2 text-muted small fw-bold">65 REVIEWS</span>
                            </div>
                        </div>
                    </div>

                    <div class="row g-4">
                        <div class="col-lg-4">
                            <div class="card border-0 shadow-sm rounded-4 p-4 h-100" style="background: #f8f9fa;">
                                <h6 class="fw-bold text-navy mb-3 uppercase tracking-wider small">Service Standards</h6>
                                <?php 
                                    $stats = ['Communication' => 100, 'Punctuality' => 98, 'Eye for Detail' => 98, 'Efficiency' => 100];
                                    foreach($stats as $label => $val): 
                                ?>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between small fw-bold mb-1">
                                        <span><?php echo $label; ?></span><span><?php echo ($val/20); ?>.0</span>
                                    </div>
                                    <div class="progress" style="height: 4px;">
                                        <div class="progress-bar bg-info" style="width: <?php echo $val; ?>%"></div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="col-lg-8">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="bg-white p-4 rounded-4 shadow-sm border-start border-info border-4 h-100">
                                        <p class="small text-muted italic">"Michael was fantastic... punctual, professional, and communicated clearly."</p>
                                        <div class="small fw-bold text-navy border-top pt-2">Baht L. — <span class="text-muted fw-normal">Shower Mixer Install</span></div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="bg-white p-4 rounded-4 shadow-sm border-start border-info border-4 h-100">
                                        <p class="small text-muted italic">"Michael did excellent high end job - Highly recommended 👏👏👏👏"</p>
                                        <div class="small fw-bold text-navy border-top pt-2">M Shahzad A. — <span class="text-muted fw-normal">Art Hanging</span></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <div class="text-center py-5 rounded-4 mb-5" style="background: rgba(13, 202, 240, 0.05); border: 1px dashed rgba(13, 202, 240, 0.2);">
                    <h2 class="fw-bold text-navy">Ready to start your next project?</h2>
                    <p class="lead text-muted mb-4">Get the expertise you need without the overhead of multiple contractors.</p>
                    <button class="btn btn-info rounded-pill px-5 py-3 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#quoteModal">
                        REQUEST A QUOTE
                    </button>
                </div>

            </div>
        </div>
    </div>
</main>

<style>
    .text-navy { color: var(--mike-navy); }
    .highlight { color: var(--mike-orange); }
    .italic { font-style: italic; }
    .hero-card .btn-outline-light:hover { background-color: #fff; color: var(--mike-navy); }
</style>

<?php include 'includes/footer.php'; ?>