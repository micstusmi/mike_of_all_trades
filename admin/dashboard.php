<?php
require '../includes/auth_admin.php';
include '../includes/header.php';
?>

<style>
.admin-calendar-panel {
    background:#111;
    color:#fff;
    border-radius:12px;
}

#calendar {
    background:#fff;
    padding:20px;
    border-radius:12px;
}

.fc-toolbar-title { color:#000; }

.fc-event {
    cursor:pointer;
    font-size:12px;
    font-weight:700;
}

.buffer-event { opacity:.75; }

.fc .fc-highlight {
    background:var(--calendar-select-colour, rgba(243,146,0,.35)) !important;
}

.fc-timegrid-slot-label-cushion,
.fc-timegrid-axis-cushion {
    color:#222 !important;
    font-weight:600;
    font-size:12px;
}

.fc-col-header-cell-cushion {
    color:#0d6efd !important;
    font-weight:700;
    text-decoration:none !important;
}

.fc-theme-standard td,
.fc-theme-standard th {
    border-color:#dcdcdc !important;
}

.admin-table {
    background:#fff;
    color:#222;
    border-radius:12px;
    overflow:hidden;
}

.travel-buffer-event {
    background-image: repeating-linear-gradient(
        135deg,
        rgba(0,0,0,0.06) 0,
        rgba(0,0,0,0.06) 6px,
        rgba(255,255,255,0.25) 6px,
        rgba(255,255,255,0.25) 12px
    ) !important;
    font-size: 11px !important;
    font-weight: 700 !important;
    color: #333 !important;
    border: 1px solid #ccc !important;
}

.travel-buffer-event .fc-event-title {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

</style>

<main class="admin-calendar-page bg-dark text-white">

    <div class="admin-calendar-panel p-4">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">Admin Calendar</h2>
            <a href="../logout.php" class="btn btn-danger">Logout</a>
        </div>

        <div class="row g-2 mb-3">
            <div class="col-md-3">
                <input id="title" class="form-control" placeholder="Job / reason">
            </div>

            <div class="col-md-3">
                <textarea id="notes" class="form-control" placeholder="Notes"></textarea>
            </div>

            <div class="col-md-2">
                <select id="eventType" class="form-select">
                    <option value="work">Work</option>
                    <option value="personal">Personal</option>
                </select>
            </div>

            <div class="col-md-2">
                <select id="bufferMinutes" class="form-select">
                    <option value="30">30 min buffer</option>
                    <option value="60">60 min buffer</option>
                    <option value="0">No buffer</option>
                </select>
            </div>

            <div class="col-md-2 d-flex align-items-center">
                <small class="text-secondary">
                    Drag on calendar to create block
                </small>
            </div>
        </div>

        <div id="calendar" class="mb-4"></div>

        <h4 class="mt-4 mb-3">Admin Booking / Blockout List</h4>

        <div id="adminEventsTable" class="admin-table p-3">
            Loading events...
        </div>

    </div>

</main>

<div class="modal fade" id="editAdminEventModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-4">

            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title">Edit Calendar Block</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <input type="hidden" id="editEventId">

                <label class="form-label fw-bold">Title</label>
                <input type="text" id="editTitle" class="form-control mb-3">

                <label class="form-label fw-bold">Date</label>
                <input type="date" id="editDate" class="form-control mb-3">

                <label class="form-label fw-bold">Start Time</label>
                <input type="time" id="editStartTime" class="form-control mb-3" step="1800">

                <label class="form-label fw-bold">Duration</label>
                <select id="editDuration" class="form-select mb-3">
                    <option value="0.5">30 minutes</option>
                    <option value="1">1 hour</option>
                    <option value="1.5">1.5 hours</option>
                    <option value="2">2 hours</option>
                    <option value="2.5">2.5 hours</option>
                    <option value="3">3 hours</option>
                    <option value="3.5">3.5 hours</option>
                    <option value="4">4 hours</option>
                    <option value="4.5">4.5 hours</option>
                    <option value="5">5 hours</option>
                    <option value="6">6 hours</option>
                    <option value="8">8 hours</option>
                    <option value="10">10 hours</option>
                    <option value="12">12 hours</option>
                </select>

                <label class="form-label fw-bold">Type</label>
                <select id="editEventType" class="form-select mb-3">
                    <option value="work">Work</option>
                    <option value="personal">Personal</option>
                    <option value="customer_booking">Customer Booking</option>
                </select>

                <label class="form-label fw-bold">Buffer</label>
                <select id="editBufferMinutes" class="form-select mb-3">
                    <option value="0">No buffer</option>
                    <option value="30">30 min buffer</option>
                    <option value="60">60 min buffer</option>
                </select>

                <label class="form-label fw-bold">Notes</label>
                <textarea id="editNotes" class="form-control" rows="4"></textarea>
            </div>

            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" onclick="saveAdminEventEdit()">Save Changes</button>
            </div>

        </div>
    </div>
</div>

<script>
let calendar;
let editModal;

function mainColour(type){
    if(type === 'personal') return '#dc3545';
    if(type === 'customer_booking') return '#0d6efd';
    return '#f39200';
}

function updateSelectionColour(){
    const type = document.getElementById('eventType').value;
    const colour = type === 'personal'
        ? 'rgba(220,53,69,.45)'
        : 'rgba(243,146,0,.45)';

    document.documentElement.style.setProperty('--calendar-select-colour', colour);
}

async function postForm(url, data){
    const fd = new FormData();

    Object.keys(data).forEach(k => {
        fd.append(k, data[k]);
    });

    const r = await fetch(url, {
        method:'POST',
        body:fd
    });

    return await r.json();
}

function loadAdminEvents(){
    fetch('my_events.php')
    .then(r => r.text())
    .then(html => {
        document.getElementById('adminEventsTable').innerHTML = html;
    });
}

function editAdminEvent(id){
    postForm('get_event.php', {id:id})
    .then(res => {
        if(!res.success){
            alert(res.message || 'Could not load event.');
            return;
        }

        document.getElementById('editEventId').value = res.event.id;
        document.getElementById('editTitle').value = res.event.title;
        document.getElementById('editDate').value = res.event.date;
        document.getElementById('editStartTime').value = res.event.start_time;
        document.getElementById('editDuration').value = res.event.duration_hours;
        document.getElementById('editEventType').value = res.event.event_type;
        document.getElementById('editBufferMinutes').value = res.event.buffer_minutes;
        document.getElementById('editNotes').value = res.event.notes || '';

        editModal.show();
    });
}

function saveAdminEventEdit(){
    const data = {
        id: document.getElementById('editEventId').value,
        title: document.getElementById('editTitle').value,
        date: document.getElementById('editDate').value,
        start_time: document.getElementById('editStartTime').value,
        duration_hours: document.getElementById('editDuration').value,
        event_type: document.getElementById('editEventType').value,
        buffer_minutes: document.getElementById('editBufferMinutes').value,
        notes: document.getElementById('editNotes').value
    };

    postForm('update_event_simple.php', data)
    .then(res => {
        if(res.success){
            editModal.hide();
            calendar.refetchEvents();
            loadAdminEvents();
        } else {
            alert(res.message || 'Could not update event.');
        }
    });
}

function deleteAdminEvent(id){
    if(!confirm('Delete this block and its driving buffers?')){
        return;
    }

    postForm('delete_event.php', {id:id})
    .then(res => {
        if(res.success){
            calendar.refetchEvents();
            loadAdminEvents();
        } else {
            alert(res.message || 'Delete failed.');
        }
    });
}

document.addEventListener('DOMContentLoaded', function(){

    editModal = new bootstrap.Modal(document.getElementById('editAdminEventModal'));

    updateSelectionColour();
    document.getElementById('eventType').addEventListener('change', updateSelectionColour);

    calendar = new FullCalendar.Calendar(document.getElementById('calendar'), {
        initialView:'timeGridWeek',
        selectable:true,
        editable:false,
        allDaySlot:false,
        slotDuration:'00:30:00',
        slotMinTime:'06:00:00',
        slotMaxTime:'23:00:00',
        height:'auto',

        events:'load_events.php',

        eventContent:function(arg){
            const isBuffer = arg.event.extendedProps.is_buffer == 1;

            if(isBuffer){
                return {
                    html:`<div><strong>${arg.timeText}</strong><br>Driving / buffer time</div>`
                };
            }

            return {
                html:`<div><strong>${arg.timeText}</strong><br>${arg.event.title || 'Unavailable'}</div>`
            };
        },

        select: async function(info){
            const type = document.getElementById('eventType').value;
            const colour = mainColour(type);
            const titleInput = document.getElementById('title').value.trim();
            const title = titleInput ? 'Unavailable - ' + titleInput : 'Unavailable';
            const notes = document.getElementById('notes').value.trim();
            const buffer = parseInt(document.getElementById('bufferMinutes').value || 0);

            const tempEvent = calendar.addEvent({
                id:'temp-saving',
                title:title,
                start:info.start,
                end:info.end,
                backgroundColor:colour,
                borderColor:colour
            });

            const res = await postForm('save_event.php', {
                title:title,
                notes:notes,
                event_type:type,
                start:info.startStr,
                end:info.endStr,
                buffer_minutes:buffer
            });

            tempEvent.remove();

            if(res.success){
                calendar.refetchEvents();
                loadAdminEvents();
                calendar.unselect();
            } else {
                alert(res.message || 'Save failed');
            }
        }
    });

    calendar.render();
    loadAdminEvents();
});
</script>

<?php include '../includes/footer.php'; ?>