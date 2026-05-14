<?php
include 'includes/header.php';
require_once 'includes/db.php';

$isLoggedIn = !empty($_SESSION['user_id']);

$customerDiscount = 0;

$loggedInCustomer = [
    'name' => '',
    'email' => '',
    'phone' => '',
    'address' => ''
];

if (!empty($_SESSION['user_id'])) {

    $stmt = $pdo->prepare("
        SELECT name, email, phone, address, discount_percent
        FROM users
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([$_SESSION['user_id']]);

    $userData = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($userData) {
        $loggedInCustomer['name'] = $userData['name'] ?? '';
        $loggedInCustomer['email'] = $userData['email'] ?? '';
        $loggedInCustomer['phone'] = $userData['phone'] ?? '';
        $loggedInCustomer['address'] = $userData['address'] ?? '';
        $customerDiscount = (float)($userData['discount_percent'] ?? 0);
    }
}
?>

<main class="py-5 bg-dark">
<div class="container mt-5">

<div class="row mb-5 text-center">
    <div class="col-4">
        <div id="step1-indicator" class="p-2 border-bottom border-primary text-primary fw-bold highlight-step">1. Options</div>
    </div>
    <div class="col-4">
        <div id="step2-indicator" class="p-2 border-bottom border-secondary text-secondary">2. Availability</div>
    </div>
    <div class="col-4">
        <div id="step3-indicator" class="p-2 border-bottom border-secondary text-secondary">3. Final Quote</div>
    </div>
</div>

<div id="step1" class="booking-step">
<div class="card bg-secondary text-light p-4 rounded-4 shadow-lg border-0" style="background:#2c3e50!important;">
<h3 class="mb-4 text-info">Project Details</h3>

<div class="row g-4">
<div class="col-md-6">
<label class="form-label fw-bold">Service</label>
<select id="serviceType" class="form-select bg-dark text-light border-secondary">
<option value="" disabled selected>Select</option>
<option value="General Trades">General Trades</option>
<option value="Technical Infrastructure">Technical Infrastructure</option>
<option value="Creative Media">Creative Media</option>
</select>
</div>

<div class="col-md-6">
<label class="form-label fw-bold">Call-out fee depending on zone</label>
<select id="locationZone" class="form-select bg-dark text-light border-secondary">
<option value="" disabled selected>Select</option>
<option value="0">South Eastern suburb / South of the Yarra River - $0</option>
<option value="50">Melbourne Western / Northern suburb - $50</option>
<option value="100">Melbourne Inner CBD or Outer Western / Northern Suburb outside M80 - $100</option>
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
<button id="nextBtnStep1" class="btn btn-pill btn-locked" type="button" onclick="validateAndGoStep2()">NEXT</button>
</div>

</div>
</div>

<div id="step2" class="booking-step d-none">
<div class="card bg-secondary text-light p-4 rounded-4 shadow-lg border-0" style="background:#2c3e50!important;">
<h3 class="text-info mb-3">Select Start Time</h3>
<p class="text-light small">
    Grey blocks are unavailable. Choose a date and start time below, or select a time directly on the calendar.
</p>

<div class="bg-dark p-3 rounded-3 border border-secondary mb-3">
    <label class="fw-bold text-light mb-2 d-block">
        Choose a date and start time
    </label>

    <div class="row g-2">
        <div class="col-md-4">
            <input
                type="date"
                id="manualBookingDate"
                class="form-control"
                onchange="jumpToManualDate()"
            >
        </div>

        <div class="col-md-4">
            <input
                type="time"
                id="manualBookingTime"
                class="form-control"
                step="1800"
            >
        </div>

        <div class="col-md-4">
            <button
                type="button"
                class="btn btn-primary w-100 fw-bold"
                onclick="useManualBookingTime()"
            >
                Use This Time
            </button>
        </div>
    </div>

    <small class="text-secondary d-block mt-2">
        The calendar below shows unavailable times in grey.
    </small>
</div>

<div id="calendar"></div>

<div class="d-flex justify-content-between mt-4">
<button class="btn btn-outline-light" type="button" onclick="goToStep(1)">Back</button>
<button class="btn btn-pill" type="button" onclick="validateAndGoStep3()">Next</button>
</div>
</div>
</div>

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

<input
    id="custName"
    class="form-control mb-2"
    placeholder="Name"
    value="<?= htmlspecialchars($loggedInCustomer['name'] ?? '') ?>"
>

<input
    id="custEmail"
    class="form-control mb-2"
    placeholder="Email"
    value="<?= htmlspecialchars($loggedInCustomer['email'] ?? '') ?>"
>

<input
    id="custPhone"
    class="form-control mb-2"
    placeholder="Phone"
    value="<?= htmlspecialchars($loggedInCustomer['phone'] ?? '') ?>"
>

<textarea
    id="custAddress"
    class="form-control mb-2"
    placeholder="Address"
><?= htmlspecialchars($loggedInCustomer['address'] ?? '') ?></textarea>

<textarea id="custDescription" class="form-control mb-3" placeholder="Describe your job..."></textarea>

<div class="d-grid gap-2">
    <button class="btn btn-pill w-100" type="button" onclick="sendToZoho()">
        Send Quote
    </button>

    <button class="btn btn-primary w-100 rounded-pill fw-bold" type="button" onclick="bookNow()">
        Book Now
    </button>

    <button
        class="btn btn-outline-info w-100 rounded-pill fw-bold"
        type="button"
        onclick="emailMikeForPriceCheck()"
    >
        Something doesn't look right — email Mike
    </button>
</div>

<div id="zohoStatus" class="mt-2 small"></div>

</div>
</div>

</div>
</div>

</div>
</main>

<style>
.btn-pill{background:#f39200;color:#fff;border-radius:40px}
.btn-locked{opacity:.4;pointer-events:none;background:#6c757d!important}
.highlight-step{color:#f39200!important;border-bottom:2px solid #f39200!important}
#calendar{background:#fff;padding:20px;border-radius:12px}
.fc-event{cursor:pointer;font-size:12px;font-weight:700}
.fc-toolbar-title{color:#000}
.fc-timegrid-slot-label-cushion,
.fc-timegrid-axis-cushion{color:#222!important;font-weight:600;font-size:12px}
.fc-col-header-cell-cushion{color:#0d6efd!important;font-weight:700;text-decoration:none!important}

@media (max-width: 768px) {
    #calendar {
        padding:10px;
    }

    .fc-toolbar {
        flex-direction:column;
        gap:8px;
    }

    .fc-toolbar-title {
        font-size:1.1rem!important;
    }
}
</style>

<div class="modal fade" id="discountLoginModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
<div class="modal-dialog modal-dialog-centered">
<div class="modal-content rounded-4 border-0 shadow-lg">

<div class="modal-header bg-dark text-white">
<h5 class="modal-title">Existing Customer Discount?</h5>
</div>

<div class="modal-body">
<p>
If you are an existing or approved repeat customer, please log in before continuing so your discounted rate can be applied.
</p>

<p class="small text-muted mb-0">
New discount requests may take up to 24 hours to review.
</p>
</div>

<div class="modal-footer d-grid gap-2">
<button type="button" class="btn btn-secondary rounded-pill" data-bs-dismiss="modal">
Ignore / Continue
</button>

<a href="login.php" class="btn btn-warning rounded-pill fw-bold">
I am already setup with a discount — Login
</a>

<a href="register.php?discount_request=1" class="btn btn-primary rounded-pill fw-bold">
I want a discount — Register
</a>
</div>

</div>
</div>
</div>

<script>
let calendar;
let selectedDuration = 4;
let selectedEvent = null;
let customerDiscount = <?= json_encode($customerDiscount) ?>;
let isLoggedIn = <?= json_encode($isLoggedIn) ?>;
let discountPopupShown = false;

function formatLocalDateTime(date){
    const pad = n => String(n).padStart(2, '0');

    return date.getFullYear() + '-' +
        pad(date.getMonth() + 1) + '-' +
        pad(date.getDate()) + ' ' +
        pad(date.getHours()) + ':' +
        pad(date.getMinutes()) + ':00';
}

function checkValidation(){
    const service = document.getElementById('serviceType').value;
    const location = document.getElementById('locationZone').value;
    const btn = document.getElementById('nextBtnStep1');

    if(service && location !== ''){
        btn.classList.remove('btn-locked');
    } else {
        btn.classList.add('btn-locked');
    }
}

function validateAndGoStep2(){
    const service = document.getElementById('serviceType').value;
    const location = document.getElementById('locationZone').value;

    if(!service || location === ''){
        alert('Please select a service and location first.');
        return;
    }

    goToStep(2);
}

function validateAndGoStep3(){
    if(!selectedEvent){
        alert('Please choose a booking time first.');
        return;
    }

    goToStep(3);
}

document.addEventListener('DOMContentLoaded', function(){
    document.getElementById('serviceType').addEventListener('change', checkValidation);
    document.getElementById('locationZone').addEventListener('change', checkValidation);

    document.getElementById('hourSlider').addEventListener('input', function(){
        selectedDuration = parseFloat(this.value);
        document.getElementById('hourDisplay').innerText = this.value;

        if(selectedEvent){
            createPreviewBooking(selectedEvent.start);
        }
    });

    checkValidation();
});

function removePreviewBlocks(){
    if(!calendar){
        return;
    }

    ['customer-selection-buffer-before', 'customer-selection', 'customer-selection-buffer-after'].forEach(id => {
        const old = calendar.getEventById(id);
        if(old) old.remove();
    });
}

function createPreviewBooking(start){
    if(!calendar){
        return;
    }

    removePreviewBlocks();

    const end = new Date(start.getTime() + selectedDuration * 60 * 60 * 1000);
    const bufferMinutes = 30;

    const bufferBeforeStart = new Date(start.getTime() - bufferMinutes * 60000);
    const bufferAfterEnd = new Date(end.getTime() + bufferMinutes * 60000);

    calendar.addEvent({
        id:'customer-selection-buffer-before',
        title:'Driving / buffer time',
        start:bufferBeforeStart,
        end:start,
        backgroundColor:'#999999',
        borderColor:'#999999',
        editable:false
    });

    selectedEvent = calendar.addEvent({
        id:'customer-selection',
        title:'Your requested time',
        start:start,
        end:end,
        backgroundColor:'#0d6efd',
        borderColor:'#0d6efd',
        editable:false
    });

    calendar.addEvent({
        id:'customer-selection-buffer-after',
        title:'Driving / buffer time',
        start:end,
        end:bufferAfterEnd,
        backgroundColor:'#999999',
        borderColor:'#999999',
        editable:false
    });

    calendar.gotoDate(start);
    calculateFromEvent(selectedEvent);
}

function jumpToManualDate(){
    const date = document.getElementById('manualBookingDate').value;

    if(date && calendar){
        calendar.gotoDate(date);
    }
}

function useManualBookingTime(){
    const date = document.getElementById('manualBookingDate').value;
    const time = document.getElementById('manualBookingTime').value;

    if(!date || !time){
        alert('Please choose a date and start time.');
        return;
    }

    const start = new Date(date + 'T' + time + ':00');

    if(start < new Date()){
        alert('Please choose a future time.');
        return;
    }

    createPreviewBooking(start);
}

function initCalendar(){

    calendar = new FullCalendar.Calendar(document.getElementById('calendar'), {

        initialView: window.innerWidth < 768 ? 'timeGridDay' : 'timeGridWeek',

        headerToolbar:{
            left:'prev,next today',
            center:'title',
            right: window.innerWidth < 768 ? '' : 'timeGridWeek,timeGridDay'
        },

        selectable:true,
        editable:false,

        longPressDelay:100,
        selectLongPressDelay:100,
        eventLongPressDelay:100,

        selectAllow:function(info){
            return info.start >= new Date();
        },

        selectOverlap:false,
        eventOverlap:false,
        allDaySlot:false,
        slotDuration:'00:30:00',
        snapDuration:'00:30:00',
        slotMinTime:'06:00:00',
        slotMaxTime:'23:00:00',
        height:'auto',

        events:'public_calendar_events.php',

        select:function(info){
            createPreviewBooking(info.start);
            calendar.unselect();
        }
    });

    calendar.render();
}

function updateEventDuration(event, hours){
    createPreviewBooking(event.start);
}

function calculateFromEvent(event){
    const start = event.start;
    const end = event.end;
    const hours = (end - start) / 1000 / 60 / 60;

    const zone = parseInt(document.getElementById('locationZone').value || 0);
    const service = document.getElementById('serviceType').value;

    let base = hours <= 1 ? 300 : hours <= 2 ? 350 : 400 + (hours - 2) * 100;

    let subtotal = base + zone;
    let discountAmount = subtotal * (customerDiscount / 100);
    let total = subtotal - discountAmount;

    document.getElementById('totalDisplay').innerText = '$' + Math.round(total);

    document.getElementById('quoteSummary').innerHTML = `
        <div class="p-3 bg-dark rounded">
            <b>${service}</b><br>
            ${hours.toFixed(1)} hours requested<br>
            <hr>
            Base: $${Math.round(base)}<br>
            Location: $${zone}<br>
            ${customerDiscount > 0 ? `<span class="text-success">Customer Discount: -${customerDiscount}% ($${Math.round(discountAmount)})</span><br>` : ''}
            <strong>Total: $${Math.round(total)}</strong><br>
            <hr>
            Requested start: ${start.toLocaleString()}<br>
            Requested finish: ${end.toLocaleString()}
        </div>
    `;
}

function goToStep(step){
    document.querySelectorAll('.booking-step').forEach(e=>e.classList.add('d-none'));
    document.getElementById('step'+step).classList.remove('d-none');

    for(let i=1;i<=3;i++){
        document.getElementById('step'+i+'-indicator').classList.toggle('highlight-step', i === step);
    }

    if(step === 2 && !calendar){
        setTimeout(initCalendar, 100);
    }

    if(step === 3 && selectedEvent){
        calculateFromEvent(selectedEvent);
    }

    if(step === 3 && !isLoggedIn && !discountPopupShown){
        discountPopupShown = true;

        const discountModal = new bootstrap.Modal(
            document.getElementById('discountLoginModal')
        );

        discountModal.show();
    }
}

function sendToZoho(){
    const fd = new FormData();

    fd.append('name', document.getElementById('custName').value);
    fd.append('email', document.getElementById('custEmail').value);
    fd.append('phone', document.getElementById('custPhone').value);
    fd.append('address', document.getElementById('custAddress').value);
    fd.append('description', document.getElementById('custDescription').value);
    fd.append('service', document.getElementById('serviceType').value);
    fd.append('total', document.getElementById('totalDisplay').innerText.replace('$',''));

    if(selectedEvent){
        fd.append('requested_start', formatLocalDateTime(selectedEvent.start));
        fd.append('requested_end', formatLocalDateTime(selectedEvent.end));
    }

    fetch('process_quote.php',{method:'POST',body:fd})
    .then(r=>r.json())
    .then(res=>{
        document.getElementById('zohoStatus').innerText =
            res.success ? 'Sent successfully' : res.message;
    });
}

function bookNow(){

    if(!selectedEvent){
        alert('Please select a booking time first.');
        return;
    }

    const fd = new FormData();

    fd.append('service', document.getElementById('serviceType').value);
    fd.append('description', document.getElementById('custDescription').value);
    fd.append('requested_start', formatLocalDateTime(selectedEvent.start));
    fd.append('requested_end', formatLocalDateTime(selectedEvent.end));

    fetch('create_booking.php', {
        method:'POST',
        body:fd
    })
    .then(r => r.json())
    .then(res => {
        document.getElementById('zohoStatus').innerText =
            res.success ? 'Booking saved successfully.' : res.message;

        if(res.success){
            alert('Booking saved. You can now view it under My Bookings.');
            window.location.href = 'customer/dashboard.php';
        }
    });
}

function emailMikeForPriceCheck(){
    const service = document.getElementById('serviceType').value || 'Not selected';
    const total = document.getElementById('totalDisplay').innerText || '$0';
    const name = document.getElementById('custName').value || '';
    const email = document.getElementById('custEmail').value || '';
    const phone = document.getElementById('custPhone').value || '';

    let start = '';
    let end = '';

    if(selectedEvent){
        start = selectedEvent.start.toLocaleString();
        end = selectedEvent.end.toLocaleString();
    }

    const subject = encodeURIComponent('Quote / discount check request');

    const body = encodeURIComponent(
`Hi Mike,

I was using the quote / booking beta tool and something may not look right.

Name: ${name}
Email: ${email}
Phone: ${phone}

Service: ${service}
Displayed price: ${total}

Requested start: ${start}
Requested finish: ${end}

Could you please check whether my pricing or customer discount is correct?

Thanks.`
    );

    window.location.href = `mailto:mike@mikeofalltrades.com.au?subject=${subject}&body=${body}`;
}
</script>

<?php include 'includes/footer.php'; ?>