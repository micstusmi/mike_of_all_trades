<?php include 'includes/header.php'; ?>

<main class="py-5 bg-dark">
<div class="container mt-5">

<!-- STEP INDICATORS -->
<div class="row mb-5 text-center">
    <div class="col-4"><div id="step1-indicator" class="p-2 border-bottom border-primary text-primary fw-bold highlight-step">1. Options</div></div>
    <div class="col-4"><div id="step2-indicator" class="p-2 border-bottom border-secondary text-secondary">2. Availability</div></div>
    <div class="col-4"><div id="step3-indicator" class="p-2 border-bottom border-secondary text-secondary">3. Final Quote</div></div>
</div>

<!-- STEP 1 -->
<div id="step1" class="booking-step">

<div class="card bg-secondary text-light p-4 rounded-4 shadow-lg border-0" style="background:#2c3e50!important;">
<h3 class="mb-4 text-info">Project Details</h3>

<div class="row g-4">

<div class="col-md-6">
<label class="form-label fw-bold">Service</label>
<select id="serviceType" class="form-select bg-dark text-light border-secondary" onchange="checkValidation()">
<option value="" disabled selected>Select</option>
<option value="General Trades">General Trades</option>
<option value="Technical Infrastructure">Technical Infrastructure</option>
<option value="Creative Media">Creative Media</option>
</select>
</div>

<div class="col-md-6">
<label class="form-label fw-bold">Location</label>
<select id="locationZone" class="form-select bg-dark text-light border-secondary" onchange="checkValidation()">
<option value="" disabled selected>Select</option>
<option value="0">Free Zone</option>
<option value="50">Inner ($50)</option>
<option value="100">Outer ($100)</option>
</select>
</div>

</div>

<div class="mt-4">
<label class="form-label fw-bold">
Duration: <span id="hourDisplay" class="text-info">4</span> hrs
</label>
<input type="range" id="hourSlider" class="form-range" min="1" max="12" step="0.5" value="4">
</div>

<div class="text-end mt-4">
<button id="nextBtnStep1" class="btn btn-pill btn-locked" onclick="goToStep(2)">NEXT</button>
</div>

</div>
</div>

<!-- STEP 2 -->
<div id="step2" class="booking-step d-none">

<div class="card bg-secondary text-light p-4 rounded-4 shadow-lg border-0" style="background:#2c3e50!important;">
<h3 class="text-info mb-3">Select Start Time</h3>

<div id="calendar"></div>

<div class="d-flex justify-content-between mt-4">
<button class="btn btn-outline-light" onclick="goToStep(1)">Back</button>
<button class="btn btn-pill" onclick="goToStep(3)">Next</button>
</div>

</div>
</div>

<!-- STEP 3 -->
<div id="step3" class="booking-step d-none">

<div class="row g-4">

<div class="col-lg-7">
<div class="card bg-secondary text-light p-4 rounded-4 shadow-lg border-0 h-100">

<h3 class="text-info">Quote Summary</h3>
<div id="quoteSummary"></div>

</div>
</div>

<div class="col-lg-5">

<div class="card bg-dark text-light p-4 rounded-4 border-primary border-2">

<h2 id="totalDisplay">$0</h2>

<input id="custName" class="form-control mb-2" placeholder="Name">
<input id="custEmail" class="form-control mb-2" placeholder="Email">
<input id="custPhone" class="form-control mb-2" placeholder="Phone">
<textarea id="custAddress" class="form-control mb-2" placeholder="Address"></textarea>

<textarea id="custDescription" class="form-control mb-3" placeholder="Describe your job..."></textarea>

<button class="btn btn-pill w-100" onclick="sendToZoho()">Send Quote</button>

<div id="zohoStatus" class="mt-2 small"></div>

</div>
</div>

</div>
</div>

</div>
</main>

<style>
.btn-pill{background:#f39200;color:#fff;border-radius:40px}
.btn-locked{opacity:.4;pointer-events:none}
.highlight-step{color:#f39200;border-bottom:2px solid #f39200}
.fc-event{cursor:move}
</style>

<script>

let calendar;
let selectedDuration = 4;
let selectedEvent = null;

/* -------------------------
   SLIDER = FIXED DURATION
--------------------------*/
document.getElementById('hourSlider').addEventListener('input', function(){
    selectedDuration = parseFloat(this.value);
    document.getElementById('hourDisplay').innerText = this.value;

    if(selectedEvent){
        updateEventDuration(selectedEvent, selectedDuration);
    }
});

/* -------------------------
   CALENDAR INIT
--------------------------*/
function initCalendar(){

    calendar = new FullCalendar.Calendar(document.getElementById('calendar'), {

        initialView:'timeGridWeek',
        selectable:true,
        editable:true,
        allDaySlot:false,
        slotDuration:'00:30:00',
        slotMinTime:'06:00:00',
        slotMaxTime:'23:00:00',

        select: function(info){

            if(selectedEvent){
                selectedEvent.remove();
            }

            const end = new Date(info.start.getTime() + selectedDuration * 60 * 60 * 1000);

            selectedEvent = calendar.addEvent({
                id:'job',
                title:'JOB',
                start:info.start,
                end:end,
                backgroundColor:'#f39200',
                borderColor:'#f39200'
            });

            calculateFromEvent(selectedEvent);
        },

        eventDrop: function(info){
            calculateFromEvent(info.event);
        }

    });

    calendar.render();
}

/* -------------------------
   KEEP DURATION LOCKED
--------------------------*/
function updateEventDuration(event, hours){

    const start = event.start;
    const end = new Date(start.getTime() + hours * 60 * 60 * 1000);

    event.setEnd(end);

    calculateFromEvent(event);
}

/* -------------------------
   PRICE FROM CALENDAR ONLY
--------------------------*/
function calculateFromEvent(event){

    const start = event.start;
    const end = event.end;

    const hours = (end - start) / 1000 / 60 / 60;

    const zone = parseInt(document.getElementById('locationZone').value || 0);
    const service = document.getElementById('serviceType').value;

    let base = hours <= 1 ? 150 : hours <= 2 ? 175 : 200 + (hours - 2) * 50;

    let total = base + zone;

    document.getElementById('totalDisplay').innerText = "$" + Math.round(total);

    document.getElementById('quoteSummary').innerHTML = `
        <div class="p-3 bg-dark rounded">
            <b>${service}</b><br>
            ${hours.toFixed(1)} hours<br>
            Base: $${Math.round(base)}<br>
            Location: $${zone}
        </div>
    `;
}

/* -------------------------
   NAVIGATION
--------------------------*/
function goToStep(step){

    document.querySelectorAll('.booking-step').forEach(e=>e.classList.add('d-none'));
    document.getElementById('step'+step).classList.remove('d-none');

    if(step===2 && !calendar){
        setTimeout(initCalendar, 100);
    }

    if(step===3 && selectedEvent){
        calculateFromEvent(selectedEvent);
    }
}

/* -------------------------
   VALIDATION
--------------------------*/
function checkValidation(){
    const ok = document.getElementById('serviceType').value &&
               document.getElementById('locationZone').value;

    document.getElementById('nextBtnStep1').classList.toggle('btn-locked', !ok);
}

/* -------------------------
   SEND TO ZOHO
--------------------------*/
function sendToZoho(){

    const fd = new FormData();

    fd.append('name', document.getElementById('custName').value);
    fd.append('email', document.getElementById('custEmail').value);
    fd.append('phone', document.getElementById('custPhone').value);
    fd.append('address', document.getElementById('custAddress').value);
    fd.append('description', document.getElementById('custDescription').value);

    fd.append('service', document.getElementById('serviceType').value);
    fd.append('total', document.getElementById('totalDisplay').innerText.replace('$',''));

    fetch('process_quote.php',{method:'POST',body:fd})
    .then(r=>r.json())
    .then(res=>{
        document.getElementById('zohoStatus').innerText =
            res.success ? "Sent successfully" : res.message;
    });

}

</script>

<?php include 'includes/footer.php'; ?>