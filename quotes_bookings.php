<?php include 'includes/header.php'; ?>

<main class="py-5 bg-dark">
    <div class="container mt-5">
        <div class="row mb-5 text-center">
            <div class="col-4"><div id="step1-indicator" class="p-2 border-bottom border-primary text-primary fw-bold highlight-step">1. Options</div></div>
            <div class="col-4"><div id="step2-indicator" class="p-2 border-bottom border-secondary text-secondary">2. Availability</div></div>
            <div class="col-4"><div id="step3-indicator" class="p-2 border-bottom border-secondary text-secondary">3. Final Quote</div></div>
        </div>

        <div id="step1" class="booking-step">
            <div class="card bg-secondary text-light p-4 rounded-4 shadow-lg border-0" style="background-color: #2c3e50 !important;">
                <h3 class="mb-4 text-info"><i class="bi bi-gear-wide-connected me-2"></i> Project Details</h3>
                <div class="row g-4">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Service Type</label>
                        <select id="serviceType" class="form-select bg-dark text-light border-secondary" onchange="checkValidation()">
                            <option value="" disabled selected>--- SELECT SERVICE ---</option>
                            <option value="General Trades">General Trades</option>
                            <option value="Technical Infrastructure">Technical Infrastructure</option>
                            <option value="Creative Media">Creative / Media</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Project Location</label>
                        <select id="locationZone" class="form-select bg-dark text-light border-secondary" onchange="checkValidation()">
                            <option value="" disabled selected>--- SELECT LOCATION ---</option>
                            <option value="0">South of Yarra / East (Free)</option>
                            <option value="50">Inner North / West ($50)</option>
                            <option value="100">Outer North / West ($100)</option>
                            <option value="100_cbd">CBD ($50 + Parking)</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold">Estimated Duration: <span id="hourDisplay" class="text-info">4</span> Hours</label>
                        <input type="range" class="form-range custom-slider" min="1" max="12" step="0.5" id="hourSlider" value="4">
                    </div>
                </div>
                <div class="text-end mt-5">
                    <button id="nextBtnStep1" class="btn btn-pill px-5 btn-locked" onclick="validateAndProceed(2)">
                        NEXT <i class="bi bi-arrow-right ms-2"></i>
                    </button>
                </div>
            </div>
        </div>

        <div id="step2" class="booking-step d-none">
            <div class="card bg-secondary text-light p-4 rounded-4 shadow-lg border-0" style="background-color: #2c3e50 !important;">
                <h3 class="text-info mb-4"><i class="bi bi-calendar3 me-2"></i> Select Your Window</h3>
                <div id='calendar-container' class="bg-white rounded-3 p-2 shadow-inner" style="color: #333;"><div id='calendar'></div></div>
                <div class="d-flex justify-content-between mt-4">
                    <button class="btn btn-outline-secondary rounded-pill px-4" onclick="goToStep(1)">BACK</button>
                    <button class="btn btn-pill px-5" onclick="goToStep(3)">NEXT</button>
                </div>
            </div>
        </div>

        <div id="step3" class="booking-step d-none">
            <div class="row g-4">
                <div class="col-lg-7">
                    <div class="card bg-secondary text-light p-4 rounded-4 shadow-lg border-0 h-100" style="background-color: #2c3e50 !important;">
                        <h3 class="mb-4 text-info"><i class="bi bi-file-earmark-text me-2"></i> Quote Summary</h3>
                        <div id="quoteSummary" class="lh-lg text-white mb-4"></div>
                    </div>
                </div>
                <div class="col-lg-5 text-center">
                    <div class="card bg-dark text-light border-primary border-2 p-4 rounded-4 shadow-lg h-100">
                        <span class="text-uppercase small fw-bold text-secondary">Estimated Total</span>
                        <h1 id="totalDisplay" class="display-3 fw-bold text-primary mb-4">$0</h1>
                        
                        <div class="px-3 mb-4 text-start">
                            <label class="form-label small fw-bold text-secondary">FULL NAME</label>
                            <input type="text" id="custName" class="form-control bg-dark text-white border-secondary mb-3" placeholder="Full Name">
                            
                            <label class="form-label small fw-bold text-secondary">EMAIL ADDRESS</label>
                            <input type="email" id="custEmail" class="form-control bg-dark text-white border-secondary mb-3" placeholder="Email Address">
                            
                            <label class="form-label small fw-bold text-secondary">PHONE NUMBER</label>
                            <input type="tel" id="custPhone" class="form-control bg-dark text-white border-secondary mb-3" placeholder="Mobile Number">
                            
                            <label class="form-label small fw-bold text-secondary">JOB ADDRESS</label>
                            <textarea id="custAddress" class="form-control bg-dark text-white border-secondary mb-3" rows="2" placeholder="Street, Suburb, Postcode"></textarea>

                            <label class="form-label small fw-bold text-info mt-2">ACCOUNT PREFERENCE</label>
                            <div class="bg-dark p-3 rounded border border-secondary">
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="radio" name="customerType" id="typeOnceOff" value="once" checked>
                                    <label class="form-check-label small text-white" for="typeOnceOff">Once-off Sale (Guest)</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="customerType" id="typeRepeat" value="repeat">
                                    <label class="form-check-label small text-white" for="typeRepeat">Repeat Customer (Save Me)</label>
                                </div>
                            </div>
                        </div>

                        <div class="d-grid gap-3 px-3">
                            <button id="zohoBtn" class="btn btn-pill shadow py-3" onclick="sendToZoho()">Email PDF Quote</button>
                        </div>
                        <div id="zohoStatus" class="mt-3 small px-3"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<style>
    .highlight-step { border-bottom: 3px solid #f39200 !important; color: #f39200 !important; }
    .btn-pill { background-color: #f39200; color: white; border-radius: 50px; font-weight: bold; border: none; transition: 0.3s; }
    .btn-locked { opacity: 0.5 !important; cursor: not-allowed !important; pointer-events: none; background-color: #6c757d !important; }
    .btn-active { opacity: 1 !important; cursor: pointer !important; pointer-events: auto !important; background-color: #f39200 !important; }
</style>

<script>
let mikeCalendar;
let sHrsGlobal = 4;
let pHrsGlobal = 0;

function checkValidation() {
    const s = document.getElementById('serviceType').value;
    const l = document.getElementById('locationZone').value;
    const b = document.getElementById('nextBtnStep1');
    if (s !== "" && l !== "") { b.classList.add('btn-active'); b.classList.remove('btn-locked'); }
    else { b.classList.add('btn-locked'); b.classList.remove('btn-active'); }
}

function validateAndProceed(step) {
    if (document.getElementById('serviceType').value === "" || document.getElementById('locationZone').value === "") {
        alert("⚠️ Please select Service and Location.");
    } else { goToStep(step); }
}

function goToStep(step) {
    document.querySelectorAll('.booking-step').forEach(d => d.classList.add('d-none'));
    document.getElementById('step' + step).classList.remove('d-none');
    for(let i=1; i<=3; i++) {
        const el = document.getElementById('step'+i+'-indicator');
        i == step ? el.classList.add('highlight-step') : el.classList.remove('highlight-step');
    }
    if (step === 2) { setTimeout(() => { if (!mikeCalendar) initCalendar(); else mikeCalendar.updateSize(); }, 150); }
    if (step === 3) calculateFinal(sHrsGlobal, pHrsGlobal);
}

function initCalendar() {
    const el = document.getElementById('calendar');
    mikeCalendar = new FullCalendar.Calendar(el, {
        initialView: 'timeGridWeek', slotMinTime: '06:00:00', slotMaxTime: '24:00:00', allDaySlot: false,
        selectable: true, selectMirror: true, unselectAuto: false,
        select: function(info) {
            let old = mikeCalendar.getEventById('sel'); if (old) old.remove();
            mikeCalendar.addEvent({id:'sel', title:'SELECTED', start:info.start, end:info.end, backgroundColor:'#f39200', borderColor:'#f39200'});
            let s = 0; let p = 0; let t = new Date(info.start);
            while (t < info.end) {
                let h = t.getHours(); (h < 7 || h >= 18) ? p += 0.5 : s += 0.5;
                t.setMinutes(t.getMinutes() + 30);
            }
            sHrsGlobal = s; pHrsGlobal = p;
            document.getElementById('hourSlider').value = s + p;
            document.getElementById('hourDisplay').innerText = (s + p);
            calculateFinal(s, p);
        }
    });
    mikeCalendar.render();
}

function calculateFinal(sVal, pVal) {
    const s = sVal !== null ? sVal : parseFloat(document.getElementById('hourSlider').value) || 0;
    const p = pVal || 0;
    const total = s + p;
    const zone = parseInt(document.getElementById('locationZone').value) || 0;
    const svc = document.getElementById('serviceType').value;
    let base = total <= 1 ? 150 : (total <= 2 ? 175 : (total <= 4 ? 200 : 200 + ((total - 4) * 50)));
    let premium = total > 0 ? p * ((base / total) * 0.5) : 0;
    const grandTotal = base + premium + zone;
    document.getElementById('totalDisplay').innerText = `$${Math.round(grandTotal)}`;
    document.getElementById('quoteSummary').innerHTML = `
        <div class="p-3 bg-dark rounded border border-secondary shadow">
            <p class="mb-1 text-info fw-bold">${svc.toUpperCase()}</p>
            <p class="small text-secondary">${total} Hours Estimated</p>
            <hr class="border-secondary">
            <p class="small mb-1">Standard Rate: $${Math.round(base)}</p>
            ${premium > 0 ? `<p class="small text-warning">After Hours: +$${Math.round(premium)}</p>` : ''}
            <p class="small mb-0">Location Fee: $${zone}</p>
        </div>`;
}

function sendToZoho() {
    const n = document.getElementById('custName').value;
    const e = document.getElementById('custEmail').value;
    const ph = document.getElementById('custPhone').value;
    const ad = document.getElementById('custAddress').value;
    const ty = document.querySelector('input[name="customerType"]:checked').value;

    if (!n || !e || !ph || !ad) { alert("⚠️ All details required."); return; }

    const b = document.getElementById('zohoBtn');
    const status = document.getElementById('zohoStatus');
    b.disabled = true; b.innerText = "Processing...";

    const fd = new FormData();
    fd.append('name', n); fd.append('email', e); fd.append('phone', ph); fd.append('address', ad);
    fd.append('customer_type', ty);
    fd.append('service', document.getElementById('serviceType').value);
    fd.append('total', document.getElementById('totalDisplay').innerText.replace('$', ''));

    fetch('process_quote.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
        if(data.success) { 
            status.innerHTML = "<span class='text-success fw-bold'>✅ PDF Sent! Check your inbox.</span>"; 
            b.innerText = "Sent!"; 
        } else { 
            status.innerHTML = "<span class='text-danger fw-bold'>❌ Error: " + data.message + "</span>"; 
            b.disabled = false; b.innerText = "Email PDF Quote";
        }
    })
    .catch(err => { 
        status.innerHTML = "<span class='text-danger'>❌ System Error: Check Console</span>"; 
        console.error(err);
        b.disabled = false; b.innerText = "Email PDF Quote";
    });
}

document.getElementById('hourSlider').addEventListener('input', function() {
    document.getElementById('hourDisplay').innerText = this.value;
    sHrsGlobal = parseFloat(this.value); pHrsGlobal = 0;
    calculateFinal(sHrsGlobal, 0);
});
</script>
<?php include 'includes/footer.php'; ?>