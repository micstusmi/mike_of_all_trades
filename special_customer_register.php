<?php
session_start();

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '../includes/header.php';
?>

<main class="py-5 bg-dark text-white">
    <div class="container" style="max-width:700px;">

        <h2 class="text-info mb-4">Special Customer Invite</h2>

        <div class="card bg-secondary p-4 rounded-4 border-0">

            <label class="form-label fw-bold">Contact / Company Name</label>
            <input id="contactName" class="form-control mb-3">

            <label class="form-label fw-bold">First Name</label>
            <input id="firstName" class="form-control mb-3">

            <label class="form-label fw-bold">Last Name</label>
            <input id="lastName" class="form-control mb-3">

            <label class="form-label fw-bold">Email</label>
            <input id="email" type="email" class="form-control mb-3">

            <label class="form-label fw-bold">Phone</label>
            <input id="phone" class="form-control mb-3">

            <label class="form-label fw-bold">Billing Address</label>
            <textarea id="billingAddress" class="form-control mb-3"></textarea>

            <label class="form-label fw-bold">Hourly Rate</label>
            <input id="hourlyRate" type="number" step="0.01" value="50" class="form-control mb-3">

            <label class="form-label fw-bold">Minimum Hours</label>
            <input id="minimumHours" type="number" step="0.5" value="4" class="form-control mb-3">

            <label class="form-label fw-bold">Service Zone</label>
            <select id="serviceZone" class="form-select mb-4">
                <option value="south_east">South East Melbourne</option>
                <option value="standard">Standard</option>
            </select>

            <button class="btn btn-warning fw-bold rounded-pill" onclick="sendInvite()">
                Send Special Customer Invite
            </button>

            <div id="inviteStatus" class="mt-3"></div>

        </div>
    </div>
</main>

<script>
function sendInvite(){
    const fd = new FormData();

    fd.append('contact_name', document.getElementById('contactName').value);
    fd.append('first_name', document.getElementById('firstName').value);
    fd.append('last_name', document.getElementById('lastName').value);
    fd.append('email', document.getElementById('email').value);
    fd.append('phone', document.getElementById('phone').value);
    fd.append('billing_address', document.getElementById('billingAddress').value);
    fd.append('hourly_rate', document.getElementById('hourlyRate').value);
    fd.append('minimum_hours', document.getElementById('minimumHours').value);
    fd.append('service_zone', document.getElementById('serviceZone').value);

    fetch('../send_special_customer_invite.php', {
        method:'POST',
        body:fd
    })
    .then(r => r.json())
    .then(res => {
        document.getElementById('inviteStatus').innerText =
            res.message || 'Done.';
    })
    .catch(err => {
        document.getElementById('inviteStatus').innerText =
            'Error: ' + err.message;
    });
}
</script>

<?php include '../includes/footer.php'; ?>