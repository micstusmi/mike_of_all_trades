<?php
include 'includes/header.php';
require_once 'includes/db.php';

$isLoggedIn = !empty($_SESSION['user_id']);

$customerPricing = [
    'pricing_mode' => 'standard',
    'hourly_rate' => null,
    'minimum_hours' => 4,
    'service_zone' => 'standard'
];

$loggedInCustomer = [
    'name' => '',
    'email' => '',
    'phone' => '',
    'address' => ''
];

if (!empty($_SESSION['user_id'])) {

    $stmt = $pdo->prepare("
        SELECT
    name,
    email,
    phone,
    address,
    discount_percent,
    pricing_mode,
    hourly_rate,
    minimum_hours,
    service_zone
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
        $customerPricing['pricing_mode'] = $userData['pricing_mode'] ?? 'standard';
        $customerPricing['hourly_rate'] = $userData['hourly_rate'] ?? null;
        $customerPricing['minimum_hours'] = $userData['minimum_hours'] ?? 4;
        $customerPricing['service_zone'] = $userData['service_zone'] ?? 'standard';
    }
}
?>

<main class="py-5 bg-dark">
<div class="container mt-5">
    <div id="aiHelperWrapper" class="mb-4">

    <div class="card bg-dark text-light border border-info rounded-4 shadow-lg">

        <div class="card-body">

            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">

                <div>
                    <h3 class="text-info mb-1">
                        AI Helper Assistant
                    </h3>

                    <p class="mb-0 text-light small">
                        Describe what you need done and the AI can help guide your quote or booking.
                    </p>
                </div>

                <div class="d-flex gap-2 flex-wrap">

                    <a
                        href="ai_helper.php?new=1"
                        class="btn btn-info rounded-pill fw-bold"
                    >
                        Start with AI Helper
                    </a>

                    <button
                        class="btn btn-outline-light rounded-pill"
                        type="button"
                        onclick="disableAiHelper()"
                    >
                        Continue without AI
                    </button>

                </div>

            </div>

        </div>

    </div>

</div>

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
<option value="Tradie Type Services / Handyman">General Trades</option>
<option value="Technical Infrastructure">IT / Web Dev / Mobile Apps</option>
<option value="Creative Media">Creative Media incl. Graphic Design, Photography, Videography, Editing</option>
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
    <label class="form-label fw-bold d-block">Booking Type</label>

    <div class="btn-group mb-3" role="group">
        <input type="radio" class="btn-check" name="bookingMode" id="modeHours" value="hours" checked>
        <label class="btn btn-outline-info" for="modeHours">Hours</label>

        <input type="radio" class="btn-check" name="bookingMode" id="modeDays" value="days">
        <label class="btn btn-outline-info" for="modeDays">Days</label>
    </div>

    <label class="form-label fw-bold">
        Duration:
        <span id="hourDisplay" class="text-info">4</span>
        <span id="durationUnitLabel">hrs</span>
    </label>

    <input type="range" id="hourSlider" class="form-range" min="1" max="12" step="0.5" value="4">

    <small id="durationHelpText" class="text-light d-block">
        Hour mode books the exact number of hours selected.
    </small>
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
    <small id="dayModePreviewNotice" class="text-warning d-block mt-2 d-none">
    Day bookings may only preview the first day on the calendar. The full booking will be allocated automatically after you press Book Now.
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

    <button class="btn btn-primary w-100 rounded-pill fw-bold" type="button" onclick="continueBooking()">
    Continue booking
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

@media (max-width: 767px) {
    .fc-col-header-cell-cushion {
        font-size: 14px !important;
        line-height: 1.1 !important;
        white-space: normal !important;
    }

    .fc-toolbar-title {
        font-size: 26px !important;
    }
}

.travel-buffer-event {
    background-color:#eeeeee!important;
    background-image:repeating-linear-gradient(
        135deg,
        rgba(0,0,0,0.08) 0,
        rgba(0,0,0,0.08) 6px,
        rgba(255,255,255,0.65) 6px,
        rgba(255,255,255,0.65) 12px
    )!important;
    border-color:#cccccc!important;
    color:#333!important;
    font-size:11px!important;
    font-weight:700!important;
}

.travel-buffer-event .fc-event-time,
.travel-buffer-event .fc-event-title {
    display:none!important;
}

.travel-buffer-event .fc-event-main {
    color:#333!important;
}

.travel-buffer-label {
    width:100%;
    height:100%;
    min-height:16px;
    display:flex;
    align-items:center;
    justify-content:flex-start;
    gap:2px;
    color:#333!important;
    font-weight:800;
    font-size:11px;
    line-height:1;
    white-space:nowrap;
    overflow:visible;
    padding-left:1px;
}

.travel-car {
    flex:0 0 auto;
}

.travel-word {
    flex:0 0 auto;
}

.unavailable-vertical {
    writing-mode:vertical-rl;
    text-orientation:mixed;
    font-weight:700;
}

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

<a href="login.php?return=quotes_bookings.php" class="btn btn-warning rounded-pill fw-bold" onclick="saveQuoteProgress()">I am already setup with a discount — Login
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
let bookingMode = 'hours';
let billableHours = 4;

function updateDurationMode(){
    bookingMode = document.querySelector('input[name="bookingMode"]:checked').value;

    const slider = document.getElementById('hourSlider');
    const unitLabel = document.getElementById('durationUnitLabel');
    const helpText = document.getElementById('durationHelpText');
    const dayNotice = document.getElementById('dayModePreviewNotice');

if(dayNotice){
    dayNotice.classList.toggle('d-none', bookingMode !== 'days');
}

    if(bookingMode === 'days'){
        slider.min = 1;
        slider.max = 10;
        slider.step = 1;
        slider.value = Math.max(1, Math.round(slider.value));

        unitLabel.innerText = slider.value == 1 ? 'day' : 'days';
        helpText.innerText = 'Day mode assumes 8 billable hours per work day, usually between 8am and 5pm.';
    } else {
        slider.min = 1;
        slider.max = 12;
        slider.step = 0.5;

        unitLabel.innerText = 'hrs';
        helpText.innerText = 'Hour mode books the exact number of hours selected.';
    }

    selectedDuration = parseFloat(slider.value);
    billableHours = bookingMode === 'days' ? selectedDuration * 8 : selectedDuration;

    document.getElementById('hourDisplay').innerText = slider.value;

    if(selectedEvent){
        createPreviewBooking(selectedEvent.start);
    }
}
let selectedEvent = null;
let customerDiscount = <?= json_encode($customerDiscount) ?>;
let isLoggedIn = <?= json_encode($isLoggedIn) ?>;
let customerPricing = <?= json_encode($customerPricing) ?>;
let discountPopupShown = false;

function formatLocalDateTime(date){
    const pad = n => String(n).padStart(2, '0');

    return date.getFullYear() + '-' +
        pad(date.getMonth() + 1) + '-' +
        pad(date.getDate()) + ' ' +
        pad(date.getHours()) + ':' +
        pad(date.getMinutes()) + ':00';
}


function isPreviewEventId(id){
    return [
        'customer-selection-buffer-before',
        'customer-selection',
        'customer-selection-buffer-after'
    ].includes(id);
}

function getRequestedRangeWithBuffers(start){
    let bookingStart = new Date(start);
    let bookingEnd;

    if(bookingMode === 'days'){
        bookingStart.setHours(8, 0, 0, 0);
        bookingEnd = new Date(bookingStart);
        bookingEnd.setHours(17, 0, 0, 0);
    } else {
        bookingEnd = new Date(bookingStart.getTime() + selectedDuration * 60 * 60 * 1000);
    }

    const bufferMinutes = 30;

    return {
        bookingStart: bookingStart,
        bookingEnd: bookingEnd,
        bufferStart: new Date(bookingStart.getTime() - bufferMinutes * 60000),
        bufferEnd: new Date(bookingEnd.getTime() + bufferMinutes * 60000)
    };
}

function rangesOverlap(startA, endA, startB, endB){
    return startA < endB && endA > startB;
}

function bookingRangeHasConflict(start, end){
    if(!calendar){
        return false;
    }

    return calendar.getEvents().some(function(event){
        if(isPreviewEventId(event.id)){
            return false;
        }

        if(!event.start || !event.end){
            return false;
        }

        return rangesOverlap(start, end, event.start, event.end);
    });
}

function canUseBookingStart(start, showAlert){
    if(start < new Date()){
        if(showAlert){
            alert('Please choose a future time.');
        }
        return false;
    }

    const range = getRequestedRangeWithBuffers(start);

    if(bookingRangeHasConflict(range.bufferStart, range.bufferEnd)){
        if(showAlert){
            alert(
                'That time overlaps with an unavailable booking or travel buffer.\n\n' +
                'Please choose a different start time.'
            );
        }
        return false;
    }

    return true;
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
    
    document.getElementById('hourSlider').addEventListener('input', updateDurationMode);
    document.getElementById('modeHours').addEventListener('change', updateDurationMode);
    document.getElementById('modeDays').addEventListener('change', updateDurationMode);

    updateDurationMode();
    checkValidation();

    const params = new URLSearchParams(window.location.search);

    if(params.get('restore') === '1'){
        setTimeout(restoreQuoteProgress, 300);
    }

if(params.get('step') === 'availability'){
    setTimeout(function(){
        goToStep(2);

        setTimeout(function(){
            if(calendar){
                const view = params.get('view');

                if(view === 'day'){
                    calendar.changeView('timeGridDay');
                }

                if(view === 'week'){
                    calendar.changeView('timeGridWeek');
                }

                if(view === 'month'){
                    calendar.changeView('dayGridMonth');
                }
            }
        }, 300);
    }, 300);
}

if(params.get('ai_booking') === '1'){
    setTimeout(function(){
        restoreAiBookingDraft();
    }, 400);
}

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

    if(!canUseBookingStart(start, true)){
        removePreviewBlocks();
        selectedEvent = null;
        return;
    }

    removePreviewBlocks();

    const range = getRequestedRangeWithBuffers(start);
    start = range.bookingStart;
    const end = range.bookingEnd;
    const bufferBeforeStart = range.bufferStart;
    const bufferAfterEnd = range.bufferEnd;

    calendar.addEvent({
        id:'customer-selection-buffer-before',
        title:'🚗 travel',
        start:bufferBeforeStart,
        end:start,
        backgroundColor:'#d9d9d9',
        borderColor:'#cccccc',
        textColor:'#333333',
        classNames:['travel-buffer-event'],
        extendedProps:{ is_buffer:1 },
        editable:false
    });

    selectedEvent = calendar.addEvent({
        id:'customer-selection',
        title:'Your requested start time',
        start:start,
        end:end,
        backgroundColor:'#0d6efd',
        borderColor:'#0d6efd',
        editable:false
    });

    calendar.addEvent({
        id:'customer-selection-buffer-after',
        title:'🚗 travel',
        start:end,
        end:bufferAfterEnd,
        backgroundColor:'#d9d9d9',
        borderColor:'#cccccc',
        textColor:'#333333',
        classNames:['travel-buffer-event'],
        extendedProps:{ is_buffer:1 },
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

        dayHeaderContent:function(arg){
    const isMobile = window.innerWidth < 768;
    const d = arg.date;
    const day = d.getDate();
    const month = d.getMonth() + 1;

    if(isMobile){
        const letters = ['S','M','T','W','T','F','S'];
        return {
            html:
                '<div style="font-size:12px;font-weight:700;">' + letters[d.getDay()] + '</div>' +
                '<div style="font-size:11px;">' + day + '/' + month + '</div>'
        };
    }

    return { html:'<div style="font-weight:700;">' + arg.text + '</div>' };
},

        selectable:true,
        editable:false,

        longPressDelay:100,
        selectLongPressDelay:100,
        eventLongPressDelay:100,

        selectAllow:function(info){
            return canUseBookingStart(info.start, false);
        },

        selectOverlap:false,
        eventOverlap:false,
        allDaySlot:false,
        slotDuration:'00:30:00',
        snapDuration:'00:30:00',
        slotMinTime:'06:00:00',
        slotMaxTime:'23:00:00',
        height: window.innerWidth < 768 ? 700 : 'auto',
            contentHeight: window.innerWidth < 768 ? 700 : 'auto',
            expandRows: true,

        events:'public_calendar_events.php',

        displayEventTime:false,
        displayEventEnd:false,

eventContent:function(arg){
    const isMobile = window.innerWidth < 768;
    const title = (arg.event.title || '').toLowerCase();
    const classes = arg.event.classNames || [];

    const isBuffer =
        arg.event.extendedProps.is_buffer == 1 ||
        arg.event.extendedProps.is_buffer === true ||
        classes.includes('travel-buffer-event') ||
        title.includes('travel') ||
        title.includes('buffer') ||
        title.includes('driving');

    if(isBuffer){
        return {
html:`<div class="travel-buffer-label"><span class="travel-car">🚗</span><span class="travel-word">travel</span></div>`
        };
    }

    if(isMobile && arg.event.title === 'Unavailable'){
        return {
            html:`<div class="unavailable-vertical">Unavailable</div>`
        };
    }

    return {
        html:`<div><strong>${arg.timeText}</strong><br>${arg.event.title || 'Unavailable'}</div>`
    };
},

select:function(info){

            if(canUseBookingStart(info.start, true)){
                createPreviewBooking(info.start);
            }

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
    const hours = bookingMode === 'days' ? selectedDuration * 8 : (end - start) / 1000 / 60 / 60;
    const zone = parseInt(document.getElementById('locationZone').value || 0);
    const service = document.getElementById('serviceType').value;

    let base;
let pricingNote = '';

if(
    isLoggedIn &&
    customerPricing.pricing_mode === 'flat_hourly' &&
    customerPricing.hourly_rate
){
    const minimumHours = parseFloat(customerPricing.minimum_hours || 4);
    const hourlyRate = parseFloat(customerPricing.hourly_rate);

    billableHours = Math.max(hours, minimumHours);
    base = billableHours * hourlyRate;

    pricingNote =
        `Flat hourly customer rate: $${hourlyRate}/hr, minimum ${minimumHours} hours<br>`;
}else{
    billableHours = hours;
    base = hours <= 1 ? 300 : hours <= 2 ? 350 : 400 + (hours - 2) * 100;
}

let subtotal = base + zone;
let discountAmount = subtotal * (customerDiscount / 100);
let total = subtotal - discountAmount;

    document.getElementById('totalDisplay').innerText = '$' + Math.round(total);

    document.getElementById('quoteSummary').innerHTML = `
        <div class="p-3 bg-dark rounded">
            <b>${service}</b><br>
            ${bookingMode === 'days' ? selectedDuration + ' work day(s)' : hours.toFixed(1) + ' hours'} requested<br>
Billable hours: ${billableHours.toFixed(1)}<br>
${pricingNote}
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
        if(bookingMode === 'days'){
    alert(
        selectedDuration + ' work day(s) selected.\n\n' +
        'The blue block shows the first available work day only.\n\n' +
        'When you press Book Now, the system will automatically allocate the remaining available weekday time slots between 8am and 5pm.'
    );
}
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
    fd.append('booking_mode', bookingMode);
fd.append('duration_units', selectedDuration);
fd.append('billable_hours', bookingMode === 'days' ? selectedDuration * 8 : selectedDuration);

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

function continueBooking(){

    if(!isLoggedIn){
        saveQuoteProgress();

        alert(
            'Almost done — please log in or create an account so your booking details can be saved securely.'
        );

        const returnUrl = encodeURIComponent(
            window.location.pathname + window.location.search
        );

        window.location.href =
            'login.php?return=' + returnUrl;

        return;
    }

    bookNow();
}

function bookNow(){

    if(!selectedEvent){
        alert('Please select a booking time first.');
        return;
    }

    const finalRange = getRequestedRangeWithBuffers(selectedEvent.start);

    if(bookingRangeHasConflict(finalRange.bufferStart, finalRange.bufferEnd)){
        alert(
            'This time has become unavailable or overlaps with another booking/travel buffer.\n\n' +
            'Please choose a different start time.'
        );
        removePreviewBlocks();
        selectedEvent = null;
        goToStep(2);
        return;
    }

    const fd = new FormData();

    fd.append('service', document.getElementById('serviceType').value);
    fd.append('description', document.getElementById('custDescription').value);
    fd.append('total', document.getElementById('totalDisplay').innerText.replace('$',''));
    fd.append('booking_mode', bookingMode);
fd.append('duration_units', selectedDuration);
fd.append('billable_hours', bookingMode === 'days' ? selectedDuration * 8 : selectedDuration);
    fd.append('requested_start', formatLocalDateTime(selectedEvent.start));
    fd.append('requested_end', formatLocalDateTime(selectedEvent.end));

    const status = document.getElementById('zohoStatus');
    status.innerText = 'Saving booking...';

    fetch('create_booking.php', {
        method:'POST',
        body:fd
    })
    .then(async r => {
        const text = await r.text();

        try {
            return JSON.parse(text);
        } catch(e) {
            throw new Error(text.substring(0, 500) || 'Invalid server response.');
        }
    })
    .then(res => {
        if(res.success){
            status.innerText = 'Booking saved successfully.';
            alert('Booking saved. You can now view it under My Bookings.');
            window.location.href = 'customer/dashboard.php';
        } else {
            status.innerText = res.message || 'Could not save booking.';
            alert(res.message || 'Could not save booking.');
        }
    })
    .catch(err => {
        status.innerText = 'Booking failed: ' + err.message;
        alert('Booking failed: ' + err.message);
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

function saveQuoteProgress(){
    const data = {
        service: document.getElementById('serviceType').value,
        location: document.getElementById('locationZone').value,
        duration: document.getElementById('hourSlider').value,
        bookingMode: bookingMode,
        description: document.getElementById('custDescription')?.value || '',
        selectedStart: selectedEvent ? formatLocalDateTime(selectedEvent.start) : '',
        selectedEnd: selectedEvent ? formatLocalDateTime(selectedEvent.end) : ''
    };

    sessionStorage.setItem('quoteProgress', JSON.stringify(data));
    localStorage.setItem('quoteProgress', JSON.stringify(data));
}

function restoreQuoteProgress(){
    const saved =
        sessionStorage.getItem('quoteProgress') ||
        localStorage.getItem('quoteProgress');

    if(!saved){
        return;
    }

    const data = JSON.parse(saved);

    if(data.service){
        document.getElementById('serviceType').value = data.service;
    }

    if(data.location !== ''){
        document.getElementById('locationZone').value = data.location;
    }

    if(data.bookingMode === 'days'){
        document.getElementById('modeDays').checked = true;
    }else{
        document.getElementById('modeHours').checked = true;
    }

    updateDurationMode();

    if(data.duration){
        selectedDuration = parseFloat(data.duration);
        document.getElementById('hourSlider').value = data.duration;
        document.getElementById('hourDisplay').innerText = data.duration;
    }

    if(data.description){
        document.getElementById('custDescription').value = data.description;
    }

    checkValidation();

    if(data.selectedStart){
        goToStep(2);

        setTimeout(function(){
            const start = new Date(data.selectedStart.replace(' ', 'T'));
            createPreviewBooking(start);
            goToStep(3);
            sessionStorage.removeItem('quoteProgress');
            localStorage.removeItem('quoteProgress');
        }, 400);
    }
}

function restoreAiIntake(){
    const saved = sessionStorage.getItem('aiJobIntake');

    if(!saved){
        return;
    }

    const data = JSON.parse(saved);

    let description = '';

    if(data.understood_job){
        description += data.understood_job + "\n\n";
    }

    if(data.original_job){
        description += "AI chat details:\n" + data.original_job + "\n\n";
    }

    if(data.conversation_token){
        description += "AI conversation link:\n";
        description += window.location.origin + "/view_ai_conversation.php?token=" + data.conversation_token;
    }

    document.getElementById('custDescription').value = description.trim();

    if(
        description.toLowerCase().includes('paint') ||
        description.toLowerCase().includes('door') ||
        description.toLowerCase().includes('frame')
    ){
        document.getElementById('serviceType').value = 'General Trades';
    }

    checkValidation();
}

function restoreAiBookingDraft(){
    const saved =
        sessionStorage.getItem('aiBookingDraft') ||
        localStorage.getItem('aiBookingDraft');

    if(!saved){
        return;
    }

    const data = JSON.parse(saved);

    document.getElementById('custName').value = data.name || '';
    document.getElementById('custEmail').value = data.email || '';
    document.getElementById('custPhone').value = data.phone || '';
    document.getElementById('custDescription').value =
    'AI booking details:\n\n' +
    'Suburb: ' + (data.suburb || '') + '\n\n' +
    (data.notes || '');

    if(data.booking_mode === 'days'){
    document.getElementById('modeDays').checked = true;
}else{
    document.getElementById('modeHours').checked = true;
}

updateDurationMode();

    if(data.hours){
        document.getElementById('hourSlider').value = data.hours;
        selectedDuration = parseFloat(data.hours);
        billableHours = selectedDuration;
        document.getElementById('hourDisplay').innerText = data.hours;
    }

    document.getElementById('serviceType').value = 'General Trades';
    if(data.suburb){
    const suburb = data.suburb.toLowerCase();

    if(
        suburb.includes('pakenham') ||
        suburb.includes('officer') ||
        suburb.includes('cranbourne')
    ){
        document.getElementById('locationZone').value = '100';
    }else{
        document.getElementById('locationZone').value = '0';
    }
}

checkValidation();

goToStep(2);

    alert('Your AI booking details have been added. Please choose a date and time on the calendar.');
}

function disableAiHelper(){
    localStorage.setItem('useAiHelper', '0');

document.getElementById('aiHelperWrapper').style.display = 'none';

alert(
    'AI Helper disabled.\n\n' +
    'You can continue using the standard quote and booking form below.'
);
}

</script>

<?php include 'includes/footer.php'; ?>