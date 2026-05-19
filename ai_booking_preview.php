<?php
require_once __DIR__ . '/includes/db.php';

$token = trim($_GET['token'] ?? '');
$hours = trim($_GET['hours'] ?? '4');
$suburb = trim($_GET['suburb'] ?? '');
$service = trim($_GET['service'] ?? 'Handyman booking');

$conversation = null;

if ($token) {
    $stmt = $pdo->prepare("
        SELECT *
        FROM ai_conversations
        WHERE conversation_token = ?
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $conversation = $stmt->fetch(PDO::FETCH_ASSOC);
}

include __DIR__ . '/includes/header.php';
?>

<main class="py-5 bg-dark text-white">
<div class="container" style="max-width:900px;">

<h2 class="text-info mb-4">AI Booking Preview</h2>

<?php if (!$conversation): ?>

<div class="alert alert-warning">
    AI conversation not found.
</div>

<?php else: ?>

<div class="card bg-secondary text-white p-4 rounded-4 border-0 shadow-lg">

<h4 class="text-info">Booking Draft</h4>

<p class="text-warning small">
    This booking request is based on the AI chat. Mike may contact you if anything needs clarification.
</p>

<hr>

<label class="form-label fw-bold">Customer Name</label>
<input id="customerName" class="form-control mb-3" placeholder="Customer name">

<label class="form-label fw-bold">Customer Email</label>
<input id="customerEmail" class="form-control mb-3" placeholder="Customer email">

<label class="form-label fw-bold">Customer Phone</label>
<input id="customerPhone" class="form-control mb-3" placeholder="Customer phone">

<label class="form-label fw-bold">Suburb</label>
<input id="suburb" class="form-control mb-3" value="<?= htmlspecialchars($suburb) ?>">

<label class="form-label fw-bold">Estimated Duration</label>
<input id="estimatedHours" type="number" step="0.5" class="form-control mb-3" value="<?= htmlspecialchars($hours) ?>">

<label class="form-label fw-bold">Booking Notes</label>
<textarea id="bookingNotes" class="form-control mb-3" rows="12"><?= htmlspecialchars($conversation['conversation_text']) ?></textarea>

<input type="hidden" id="conversationToken" value="<?= htmlspecialchars($token) ?>">

<div class="d-grid gap-2">
    <button class="btn btn-warning rounded-pill fw-bold" type="button" onclick="continueToCalendar()">
        Continue to calendar with these booking details
    </button>

    <a class="btn btn-outline-light rounded-pill"
       href="view_ai_conversation.php?token=<?= urlencode($token) ?>"
       target="_blank">
        View full AI conversation
    </a>
</div>

</div>

<?php endif; ?>

</div>
</main>

<script>
function continueToCalendar(){
    const bookingData = {
        name: document.getElementById('customerName').value,
        email: document.getElementById('customerEmail').value,
        phone: document.getElementById('customerPhone').value,
        suburb: document.getElementById('suburb').value,
        hours: document.getElementById('estimatedHours').value,
        notes: document.getElementById('bookingNotes').value,
        conversation_token: document.getElementById('conversationToken').value
    };

    sessionStorage.setItem('aiBookingDraft', JSON.stringify(bookingData));
    localStorage.setItem('aiBookingDraft', JSON.stringify(bookingData));

    window.location.href = 'quotes_bookings.php?ai_booking=1&step=availability&view=week';
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>