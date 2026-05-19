<?php
require '../includes/auth_user.php';
include '../includes/header.php';
?>

<style>
#customerCalendar {
    background:#fff;
    padding:20px;
    border-radius:12px;
}

.fc-toolbar-title { color:#000; }

.fc-event {
    font-size:12px;
    font-weight:700;
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

.customer-dashboard-panel {
    background:#111;
    color:#fff;
    border-radius:12px;
}

.booking-table {
    background:#fff;
    color:#222;
    border-radius:12px;
    overflow:hidden;
}
</style>

<main class="bg-dark text-white">

    <div class="customer-dashboard-panel p-4">

        <h2>My Bookings</h2>

        <p class="text-secondary mb-4">
            Blue blocks are your bookings. Grey blocks are unavailable times.
        </p>

        <div id="customerCalendar" class="mb-4"></div>

        <h4 class="mt-4 mb-3">My Booking List</h4>

        <div id="myBookingsTable" class="booking-table p-3">
            Loading bookings...
        </div>

    </div>

</main>

<div class="modal fade" id="editBookingModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-4">

            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title">Edit Booking</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <input type="hidden" id="editBookingId">

                <label class="form-label fw-bold">Date</label>
                <input type="date" id="editDate" class="form-control mb-3">

                <label class="form-label fw-bold">Start Time</label>
                <input type="time" id="editStartTime" class="form-control mb-3" step="1800">

                <label class="form-label fw-bold">Duration</label>
                <select id="editDuration" class="form-select mb-3">
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
                </select>

                <label class="form-label fw-bold">Notes</label>
                <textarea id="editNotes" class="form-control" rows="4"></textarea>
            </div>

            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" onclick="saveBookingEdit()">Save Changes</button>
            </div>

        </div>
    </div>
</div>

<script>
let calendar;
let editModal;

function loadMyBookings(){
    fetch('my_bookings.php')
    .then(r => r.text())
    .then(html => {
        document.getElementById('myBookingsTable').innerHTML = html;
    });
}

function deleteBooking(id){
    if(!confirm('Delete this booking? This will also remove its driving buffers.')){
        return;
    }

    const fd = new FormData();
    fd.append('id', id);

    fetch('delete_booking.php', {
        method:'POST',
        body:fd
    })
    .then(r => r.json())
    .then(res => {
        if(res.success){
            calendar.refetchEvents();
            loadMyBookings();
        } else {
            alert(res.message || 'Delete failed.');
        }
    });
}

function editBooking(id){
    const fd = new FormData();
    fd.append('id', id);

    fetch('get_booking.php', {
        method:'POST',
        body:fd
    })
    .then(r => r.json())
    .then(res => {
        if(!res.success){
            alert(res.message || 'Could not load booking.');
            return;
        }

        document.getElementById('editBookingId').value = res.booking.id;
        document.getElementById('editDate').value = res.booking.date;
        document.getElementById('editStartTime').value = res.booking.start_time;
        document.getElementById('editDuration').value = res.booking.duration_hours;
        document.getElementById('editNotes').value = res.booking.notes || '';

        editModal.show();
    });
}

function saveBookingEdit(){
    const fd = new FormData();

    fd.append('id', document.getElementById('editBookingId').value);
    fd.append('date', document.getElementById('editDate').value);
    fd.append('start_time', document.getElementById('editStartTime').value);
    fd.append('duration_hours', document.getElementById('editDuration').value);
    fd.append('notes', document.getElementById('editNotes').value);

    fetch('update_booking_simple.php', {
        method:'POST',
        body:fd
    })
    .then(r => r.json())
    .then(res => {
        if(res.success){
            editModal.hide();
            calendar.refetchEvents();
            loadMyBookings();
        } else {
            alert(res.message || 'Could not update booking.');
        }
    });
}

document.addEventListener('DOMContentLoaded', function(){

    editModal = new bootstrap.Modal(document.getElementById('editBookingModal'));

    calendar = new FullCalendar.Calendar(
        document.getElementById('customerCalendar'),
        {
            initialView:'timeGridWeek',
            allDaySlot:false,
            slotDuration:'00:30:00',
            slotMinTime:'06:00:00',
            slotMaxTime:'23:00:00',
            height:'auto',
            editable:false,
            selectable:false,
            events:'load_events.php'
        }
    );

    calendar.render();
    loadMyBookings();
});

</script>

<?php
$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM ai_conversations
    WHERE user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$savedChatCount = (int) $stmt->fetchColumn();
?>

<div class="card mt-4 p-4 border-0 rounded-4">
    <h3>Saved AI Chats</h3>

    <p class="text-muted mb-3">
        You have <?= $savedChatCount ?> saved AI chat<?= $savedChatCount === 1 ? '' : 's' ?>.
    </p>

    <a href="ai_chats.php" class="btn btn-info rounded-pill fw-bold">
        View My Saved AI Chats
    </a>
</div>

<?php include '../includes/footer.php'; ?>