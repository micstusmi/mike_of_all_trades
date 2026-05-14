<?php
require '../includes/auth_user.php';
require_once '../includes/db.php';

$userId = (int)$_SESSION['user_id'];

function cleanBookingTitle($title) {
    return trim(str_replace('Customer Booking -', '', $title));
}

function renderBookingCards($bookings, $allowActions = false) {
    if (!$bookings) {
        echo "<p class='mb-0 text-muted'>No bookings found.</p>";
        return;
    }

    foreach ($bookings as $booking):
        $start = new DateTime($booking['start_datetime']);
        $end = new DateTime($booking['end_datetime']);
        $title = cleanBookingTitle($booking['title']);
        ?>
        
        <div class="booking-card">
            <div class="booking-card-title">
                <?= htmlspecialchars($title) ?>
            </div>

            <div class="booking-detail">
                <strong>Date:</strong>
                <?= htmlspecialchars($start->format('l, d/m/Y')) ?>
            </div>

            <div class="booking-detail">
                <strong>Time:</strong>
                <?= htmlspecialchars($start->format('g:i A')) ?>
                -
                <?= htmlspecialchars($end->format('g:i A')) ?>
            </div>

            <?php if (!empty($booking['notes'])): ?>
                <div class="booking-detail">
                    <strong>Notes:</strong>
                    <?= nl2br(htmlspecialchars($booking['notes'])) ?>
                </div>
            <?php endif; ?>

            <?php if ($allowActions): ?>
                <div class="booking-actions">
                    <button
                        class="btn btn-sm btn-outline-primary"
                        onclick="editBooking(<?= (int)$booking['id'] ?>)"
                    >
                        Edit
                    </button>

                    <button
                        class="btn btn-sm btn-danger"
                        onclick="deleteBooking(<?= (int)$booking['id'] ?>)"
                    >
                        Delete
                    </button>
                </div>
            <?php endif; ?>
        </div>

    <?php endforeach;
}

$upcomingStmt = $pdo->prepare("
    SELECT *
    FROM calendar_events
    WHERE customer_id = ?
    AND is_buffer = 0
    AND end_datetime >= NOW()
    ORDER BY start_datetime ASC
");

$upcomingStmt->execute([$userId]);
$upcomingBookings = $upcomingStmt->fetchAll(PDO::FETCH_ASSOC);

$pastStmt = $pdo->prepare("
    SELECT *
    FROM calendar_events
    WHERE customer_id = ?
    AND is_buffer = 0
    AND end_datetime < NOW()
    ORDER BY start_datetime DESC
");

$pastStmt->execute([$userId]);
$pastBookings = $pastStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
.booking-section-title {
    color:#111;
    font-weight:800;
    margin-bottom:15px;
}

.booking-card {
    background:#f8f9fa;
    border:1px solid #ddd;
    border-radius:14px;
    padding:16px;
    margin-bottom:14px;
    color:#111;
}

.booking-card-title {
    font-size:1.1rem;
    font-weight:800;
    margin-bottom:10px;
}

.booking-detail {
    margin-bottom:6px;
    line-height:1.45;
}

.booking-actions {
    display:flex;
    gap:8px;
    flex-wrap:wrap;
    margin-top:12px;
}

@media (max-width: 768px) {
    .booking-card {
        font-size:15px;
    }

    .booking-actions .btn {
        flex:1;
    }
}
</style>

<h5 class="booking-section-title">Upcoming Bookings</h5>
<?php renderBookingCards($upcomingBookings, true); ?>

<hr>

<h5 class="booking-section-title">Completed / Archived Bookings</h5>
<?php renderBookingCards($pastBookings, false); ?>