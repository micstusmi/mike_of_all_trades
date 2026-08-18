<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Terms & Conditions | Mike Of All Trades</title>
<meta name="description" content="Terms and conditions for handyman, property maintenance, quoting and booking services supplied by Mike Of All Trades in Victoria, Australia.">
<link rel="icon" type="image/png" href="/assets/favicon.png?v=2">
<link rel="shortcut icon" type="image/png" href="/assets/favicon.png?v=2">
<style>
*{box-sizing:border-box}
body{margin:0;background:#111;color:#eee;font-family:Arial,Helvetica,sans-serif;line-height:1.65}
.topbar{position:sticky;top:0;z-index:1000;background:#121212;border-bottom:1px solid #2a2a2a}
.topbar-inner{max-width:1180px;margin:auto;padding:14px 22px;display:flex;justify-content:space-between;align-items:center;gap:18px;flex-wrap:wrap}
.logo{color:#fff;text-decoration:none;font-weight:700;letter-spacing:1px}
.nav{display:flex;gap:18px;flex-wrap:wrap}
.nav a{color:#bbb;text-decoration:none;font-size:14px}.nav a:hover{color:#fff}
.hero{max-width:980px;margin:auto;padding:44px 22px 18px}
.eyebrow{color:#ffc107;font-size:13px;font-weight:700;letter-spacing:1.2px;text-transform:uppercase;margin-bottom:8px}
h1{margin:0 0 12px;font-size:38px;line-height:1.15}.intro{color:#bbb;max-width:850px}
.notice{margin-top:22px;padding:16px 18px;border:1px solid #4a3d08;background:#25200d;border-radius:12px;color:#eee3b0}
.terms{max-width:980px;margin:0 auto 60px;padding:0 22px}
.toc{background:#1e1e1e;border:1px solid #303030;border-radius:14px;padding:18px 20px;margin:20px 0 28px;color:#ccc}
.toc strong{color:#ffc107}
section{background:#1c1c1c;border:1px solid #2c2c2c;border-radius:14px;padding:22px;margin:0 0 16px}
h2{margin:0 0 12px;color:#fff;font-size:23px}h3{margin:18px 0 8px;font-size:17px;color:#f1f1f1}
p,li{color:#d0d0d0}ul{padding-left:22px}.important{border-left:4px solid #ffc107;padding-left:14px}.small{font-size:13px;color:#999}
footer{border-top:1px solid #282828;padding:28px 22px 50px;text-align:center;color:#777;font-size:13px}
@media(max-width:700px){h1{font-size:31px}.hero{padding-top:30px}section{padding:18px}.topbar-inner{align-items:flex-start}}
</style>
</head>
<body>
<header class="topbar">
<div class="topbar-inner">
<a class="logo" href="/">MIKE OF ALL TRADES</a>
<nav class="nav">
<a href="/">Home</a>
<a href="/quotes_bookings.php">Quotes / Bookings</a>
<a href="/ai_helper.php?new=1">AI Quote Assistant</a>
<?php if (!empty($_SESSION['user_id'])): ?>
<a href="/customer/dashboard.php">My Dashboard</a>
<?php else: ?>
<a href="/login.php">Login</a>
<?php endif; ?>
</nav>
</div>
</header>

<div class="hero">
<div class="eyebrow">Mike Of All Trades</div>
<h1>Terms &amp; Conditions</h1>
<p class="intro">These Terms &amp; Conditions apply to quotations, bookings, handyman services, property maintenance and related work supplied by Mike Of All Trades in Victoria, Australia, unless a separate written contract states otherwise.</p>
<div class="notice"><strong>Important:</strong> These terms are intended to clearly define the scope of work, assumptions, customer responsibilities and how unexpected conditions are handled. Nothing in these terms excludes, restricts or modifies rights or remedies that cannot lawfully be excluded under the Australian Consumer Law or other applicable legislation.</div>
</div>

<main class="terms">
<div class="toc"><strong>Contents:</strong> Acceptance · Quotes and scope · Estimates · Variations · Access and working time · Materials · Existing conditions · Excavation and underground services · Customer-supplied information · Licensed/regulated work · Safety · Painting · Plaster · Fixings · Water damage · Demolition · Cleaning · Waste · Parking/travel · Delays · Cancellations · Payment · Authority · Occupants/pets · Photos and records · Customer-supplied goods · Hidden defects · Liability · Consumer law · Disputes · Domestic building work · Severability · Updates.</div>

<section><h2>1. Acceptance of these Terms</h2>
<p>By accepting a quotation, approving work, making a booking, instructing Mike Of All Trades to commence work, paying a deposit or otherwise proceeding with a service, the customer agrees to the quotation, any written scope supplied with it, and these Terms &amp; Conditions.</p>
<p>If a quotation contains a term that conflicts with these general Terms &amp; Conditions, the more specific written term in the quotation applies to that job, subject always to applicable law.</p></section>

<section><h2>2. Quotations and Scope of Work</h2>
<p>A quotation includes only the labour, services, materials and tasks expressly described in that quotation. Items not expressly stated are not included merely because they might ordinarily be associated with, adjacent to, or useful for the quoted work.</p>
<p>Unless expressly stated otherwise, a quotation does not automatically include rectification of pre-existing defects, concealed damage, structural problems, licensed electrical work, licensed plumbing or gas work, engineering, certification, permits, professional reports, hazardous-material removal, specialist access equipment, major waste disposal, after-hours work, or work made necessary by conditions that could not reasonably be identified when the quotation was prepared.</p>
<p>Any customer expectation, preference or assumption that is not stated in the written scope should be raised before acceptance. If a result, finish, location, colour, quantity, product, tolerance or method is important to the customer, it should be stated and agreed before work starts.</p></section>

<section><h2>3. Estimates, Indicative Prices and Time Allowances</h2>
<p>An estimate, AI-generated estimate, budget indication, approximate timeframe or preliminary price is a guide only unless expressly described as a fixed quotation.</p>
<p>Estimated labour time may include reasonable preparation, loading, collection of job-specific materials, parking, locating the site/contact person, gaining access, site assessment, setup, protection of nearby surfaces, the work itself, checking/testing, cleanup and pack-up. Actual time may vary due to access, site conditions, customer changes, hidden defects, material availability or other circumstances.</p>
<p>Where a fixed price has been quoted, that price applies to the stated scope and assumptions. It does not make additional or changed work part of the fixed price.</p></section>

<section><h2>4. Variations and Additional Work</h2>
<p>A variation includes any customer-requested change, additional task, changed specification, changed location, changed finish, additional quantity, unforeseen rectification work, or work reasonably required because actual site conditions differ from the assumptions on which the quote was based.</p>
<p>Where practical, Mike Of All Trades will discuss material variations before proceeding. Additional work may be charged at an agreed price, under an updated quote, or at a reasonable labour/material charge where the customer authorises it.</p></section>

<section><h2>5. Site Access, Setup and Working Time</h2>
<p>The customer must provide safe, timely and reasonable access to the work area. Time reasonably spent obtaining access, locating the customer or site representative, completing required sign-in procedures, moving through a complex site, unloading/loading, setting up and packing away may form part of job time where relevant.</p>
<p>The customer should advise in advance of restricted access, difficult parking, stairs, lift bookings, loading-dock requirements, building-manager rules, inductions, security procedures, restricted work hours, fragile surfaces or other requirements that may affect the work.</p></section>

<section><h2>6. Materials, Consumables and Purchases</h2>
<p>Unless the quote states that materials are included, materials, parts, fixings, consumables, delivery charges, disposal fees and hire equipment may be additional.</p>
<p>If Mike Of All Trades is asked to purchase or collect materials, reasonable time associated with sourcing, collecting, loading and transporting those items may be chargeable where the arrangement is time-based or where that cost is otherwise included in the quotation.</p>
<p>Product availability, colour matching, batch differences and supplier lead times are outside Mike Of All Trades' control. Equivalent products may be proposed if an item is unavailable, subject to customer approval where the difference is material.</p></section>

<section><h2>7. Existing Conditions and Pre-existing Damage</h2>
<p>Work is performed against the existing condition of the property. Mike Of All Trades is not responsible for defects, deterioration or damage that existed before work commenced, including rot, corrosion, moisture damage, movement, cracking, loose or friable substrates, inadequate previous repairs, poor workmanship by others, hidden cavities or defective materials, except to the extent rectification is expressly included in the quotation.</p>
<p>Where existing surfaces or materials are fragile, aged, brittle or poorly adhered, reasonable work may expose an existing weakness. If additional rectification becomes necessary, it will be discussed as a variation where practical.</p></section>

<section><h2>8. Excavation, Digging and Underground Services</h2>
<div class="important"><p>Digging, drilling, driving stakes/posts, excavation and ground penetration can encounter underground electricity, gas, water, telecommunications, irrigation, drainage, sewer, private cabling and other services that may not be visible from the surface.</p></div>
<p>The customer must disclose all known or suspected underground services, private services, irrigation, drainage, conduits, tanks, pipes, cables and infrastructure within or near the proposed work area, and provide any plans, markings, records or information in their possession before work commences.</p>
<p>Where the customer nominates or marks a position for digging or ground penetration, the customer represents that they have taken reasonable steps to identify services and that the information they provide is accurate to the best of their knowledge.</p>
<p>Mike Of All Trades may require a Before You Dig Australia enquiry, service-locating contractor, non-destructive investigation, hand-digging, potholing or other precautions before proceeding. Any such requirement may affect price and timing.</p>
<p>To the extent permitted by law, the customer is responsible for additional cost, delay or loss arising from underground services that were not disclosed, were incorrectly located by information supplied by the customer or third parties, or could not reasonably have been identified before the work commenced. This clause does not exclude liability that cannot lawfully be excluded, including liability arising from a failure by Mike Of All Trades to exercise legally required due care and skill.</p>
<p>Mike Of All Trades may refuse or stop excavation if the location of services is uncertain or the work appears unsafe.</p></section>

<section><h2>9. Customer Information, Measurements, Photos and Plans</h2>
<p>Quotes may be based on measurements, photographs, plans, drawings, descriptions or assumptions supplied by the customer or a third party. The customer is responsible for ensuring supplied information is materially accurate.</p>
<p>Photos may not reliably show scale, depth, substrate condition, concealed damage or access. Plans may contain nominal dimensions or require site verification. Mike Of All Trades may reassess the work on arrival and may propose a variation where actual conditions differ materially from the information used to quote.</p></section>

<section><h2>10. Licensed, Registered and Regulated Work</h2>
<p>Mike Of All Trades does not undertake work that the law requires to be performed or certified by a licensed or registered practitioner unless the relevant work is performed by an appropriately licensed/registered person engaged for that purpose.</p>
<p>Electrical, gas, plumbing, structural, building-permit and other regulated components may need to be referred to or coordinated with an appropriately qualified practitioner. Unless expressly included, the cost of such third-party work is not included in the handyman quotation.</p></section>

<section><h2>11. Safety and Right to Stop Work</h2>
<p>Mike Of All Trades may decline, suspend or stop work if a site, task, product, structure, access method or customer instruction appears unsafe, unlawful or outside the agreed scope.</p>
<p>Work may also be suspended if asbestos, suspected asbestos, hazardous substances, unsafe electrical conditions, unstable structures, aggressive animals, threatening conduct or other hazards are encountered.</p></section>

<section><h2>12. Painting, Coatings and Colour Matching</h2>
<p>Unless expressly included, painting quotes do not include unlimited preparation or complete correction of underlying substrate defects. Preparation is limited to what is stated or what is reasonably required for the quoted finish.</p>
<p>Existing paint may react differently to new coatings. Colour changes may require additional coats, and old enamel, moisture, contamination, silicone, grease, tannins, rust or previous incompatible coatings may require additional preparation or specialised primers.</p>
<p>Touch-up painting may remain visible due to age, fading, sheen, texture or batch differences. A perfect colour or sheen match cannot be guaranteed unless a larger area is repainted and that work is included in the scope.</p></section>

<section><h2>13. Plaster, Patching and Surface Repairs</h2>
<p>Plaster repairs may require more than one visit because compounds, sealers, primers and paints require drying time. The quoted scope should state whether sanding, priming and painting are included.</p>
<p>Hidden framing, previous repairs, loose plasterboard, moisture, movement or damage larger than visible at the surface may require additional work.</p></section>

<section><h2>14. Wall Fixings, Mounted Items and Substrates</h2>
<p>Mounting work depends on the condition and construction of the wall, ceiling or substrate. Stud locations, pipes, cables, steel framing, masonry condition, hollow walls and concealed services may affect the fixing method.</p>
<p>Where the customer supplies an item for mounting, the customer is responsible for ensuring the item is suitable, complete and free from manufacturing defects. Additional work caused by missing brackets, fixings or incompatible mounting systems may be chargeable.</p></section>

<section><h2>15. Water Damage, Leaks and Moisture</h2>
<p>Cosmetic repairs to water-damaged surfaces do not include diagnosing or repairing the source of a leak unless expressly stated. Mike Of All Trades may decline to close, patch or repaint an area that remains wet or where the underlying cause has not been addressed.</p></section>

<section><h2>16. Demolition, Removal and Unknown Construction</h2>
<p>Minor removal or demolition may expose hidden fixings, services, damage, asbestos-containing materials, non-compliant construction or conditions not visible beforehand. Such conditions may require work to stop and may result in a variation.</p></section>

<section><h2>17. Cleaning and Finish Standard</h2>
<p>Mike Of All Trades will take reasonable care to leave the immediate work area tidy. Unless specifically quoted, this does not include professional cleaning, whole-room cleaning, window cleaning, carpet cleaning or restoration of unrelated areas.</p></section>

<section><h2>18. Waste, Rubbish and Disposal</h2>
<p>Removal of waste is included only where stated. Large, heavy, hazardous or regulated waste, tip fees, skips and specialist disposal may incur additional charges.</p></section>

<section><h2>19. Parking, Tolls, Travel and Site Logistics</h2>
<p>Quoted pricing may assume ordinary vehicle access and parking. Paid parking, loading-zone restrictions, tolls, long carrying distances, multiple trips, difficult access or unusual site logistics may affect price where not reasonably known at quotation time.</p></section>

<section><h2>20. Delays and Rescheduling</h2>
<p>Dates and arrival windows are estimates unless expressly guaranteed. Delays may arise from weather, traffic, illness, supplier delays, preceding jobs, site-access issues, hidden conditions, drying/curing time or events outside reasonable control.</p>
<p>Mike Of All Trades will use reasonable efforts to communicate material delays and arrange an alternative time where necessary.</p></section>

<section><h2>21. Cancellation and Aborted Attendance</h2>
<p>If the customer cancels, postpones or prevents access after Mike Of All Trades has reasonably committed time, purchased non-returnable materials or travelled to the site, reasonable costs actually incurred may be payable, subject to applicable law and any cancellation policy stated in the quotation or booking confirmation.</p></section>

<section><h2>22. Invoices, Payment and Deposits</h2>
<p>Payment terms are those stated on the quotation or invoice. The customer must raise disputed invoice items promptly and pay any undisputed amount by the due date.</p>
<p>Materials specially purchased for a customer may require prepayment or a deposit. Any statutory limits applying to deposits or progress payments take precedence over these general terms.</p></section>

<section><h2>23. Authority to Authorise Work</h2>
<p>The person requesting the work represents that they own the property or have sufficient authority from the owner, landlord, property manager, occupier or other relevant party to authorise the work and access to the property.</p></section>

<section><h2>24. Occupants, Children and Pets</h2>
<p>The customer must keep children, pets and other occupants safely away from tools, ladders, work areas, wet coatings, adhesives, sharp objects, dust and other hazards while work is underway.</p></section>

<section><h2>25. Photos, Records and Job Documentation</h2>
<p>Mike Of All Trades may take reasonable photographs of the work area before, during and after the job for estimating, job records, quality control, dispute resolution and internal business records. Images containing personal information will be handled in accordance with applicable law.</p>
<p>Marketing use of identifiable customer information or private areas should not occur without appropriate permission.</p></section>

<section><h2>26. Customer-Supplied Products and Materials</h2>
<p>Mike Of All Trades is not the manufacturer or seller of customer-supplied products and does not warrant their quality, fitness, completeness or compatibility. Labour required because a customer-supplied item is defective, incomplete, incorrectly sized or incompatible may be chargeable.</p>
<p>This does not affect responsibility for damage caused by installation work not carried out with the legally required level of care and skill.</p></section>

<section><h2>27. Hidden Defects and Unforeseen Conditions</h2>
<p>Quotes are normally based on conditions reasonably observable before work begins. Concealed rot, termites, corrosion, mould, water damage, asbestos, inadequate framing, damaged wiring, hidden plumbing, failed waterproofing, poor previous workmanship and similar hidden conditions are not included unless specifically stated.</p>
<p>If such conditions are discovered, Mike Of All Trades may pause work and discuss the appropriate next step, including a variation, specialist contractor or revised method.</p></section>

<section><h2>28. Damage, Liability and Consequential Loss</h2>
<p>Mike Of All Trades will exercise reasonable care and skill when performing services. The customer must also take reasonable steps to protect valuables and disclose known hazards, concealed services and fragile conditions relevant to the work.</p>
<p>To the extent permitted by law, Mike Of All Trades is not responsible for loss caused by inaccurate or incomplete information supplied by the customer or third parties, pre-existing defects, concealed conditions that could not reasonably have been identified, failure of customer-supplied products, or events outside reasonable control.</p>
<p>Nothing in these terms excludes or restricts liability where doing so would be unlawful.</p></section>

<section><h2>29. Australian Consumer Law and Non-Excludable Rights</h2>
<p>These Terms &amp; Conditions are subject to the Australian Consumer Law and other applicable laws. Services supplied to consumers may come with statutory guarantees, including guarantees that services will be provided with due care and skill and, where applicable, will be fit for a disclosed purpose and supplied within a reasonable time.</p>
<p>Nothing in these Terms &amp; Conditions is intended to exclude, restrict or modify any consumer guarantee, implied warranty, right or remedy that cannot lawfully be excluded, restricted or modified.</p></section>

<section><h2>30. Questions, Complaints and Disputes</h2>
<p>If the customer believes the work does not match the agreed scope or has a defect, they should notify Mike Of All Trades promptly and provide reasonable details and access so the issue can be inspected and, where appropriate, remedied.</p>
<p>Both parties should first attempt to resolve concerns directly and reasonably before escalating the matter, without limiting either party's legal rights.</p></section>

<section><h2>31. Domestic Building Work and Separate Statutory Requirements</h2>
<p>Some residential work in Victoria is regulated as domestic building work and may require a specific written building contract, practitioner registration, Home Warranty insurance, permits, certificates or other statutory documents depending on the nature and value of the work.</p>
<p>These website Terms &amp; Conditions do not replace any contract, insurance, disclosure, registration, permit or certificate required by law. If a mandatory statutory contract applies, that contract and the legislation take precedence to the extent of any inconsistency.</p></section>

<section><h2>32. Severability and Interpretation</h2>
<p>If any part of these terms is found to be invalid, unlawful or unenforceable, it should be read down to the extent necessary and, if that is not possible, severed without affecting the remainder.</p>
<p>Headings are for convenience only. “Customer” includes the person or entity requesting or authorising the work. “Mike Of All Trades” refers to the business supplying the quoted service.</p></section>

<section><h2>33. Changes to these Terms</h2>
<p>These Terms &amp; Conditions may be updated from time to time. The version that applies to a job is ordinarily the version made available to the customer when the quote or booking is accepted, unless the parties agree otherwise or the law requires a different result.</p>
<p class="small">Suggested public URL: https://www.mikeofalltrades.com.au/terms</p></section>

</main>
<footer>&copy; <?= date('Y') ?> Mike Of All Trades · Victoria, Australia</footer>
</body>
</html>
