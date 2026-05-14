<?php
require '../includes/auth_user.php';
require_once '../includes/db.php';

$userId = (int)$_SESSION['user_id'];

function renderBookingTable($bookings, $allowActions = false) {
    if (!$bookings) {
        echo "<p class='mb-0 text-muted'>No bookings found.</p>";
        return;
    }
    ?>

    <table class="table table-striped table-hover mb-4">
        <thead>
            <tr>
                <th>Date</th>
                <th>Time</th>
                <th>Booking</th>
                <th>Notes</th>
                <?php if ($allowActions): ?>
                    <th style="width:180px;">Actions</th>
                <?php endif; ?>
            </tr>
        </thead>

        <tbody>
            <?php foreach ($bookings as $booking): ?>
                <?php
                    $start = new DateTime($booking['start_datetime']);
                    $end = new DateTime($booking['end_datetime']);
                ?>

                <tr>
                    <td><?= htmlspecialchars($start->format('d/m/Y')) ?></td>
                    <td>
                        <?= htmlspecialchars($start->format('g:i A')) ?>
                        -
                        <?= htmlspecialchars($end->format('g:i A')) ?>
                    </td>
                    <td><?= htmlspecialchars($booking['title']) ?></td>
                    <td><?= htmlspecialchars($booking['notes'] ?? '') ?></td>

                    <?php if ($allowActions): ?>
                        <td>
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
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php
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

<h5 class="mb-3">Upcoming Bookings</h5>
<?php renderBookingTable($upcomingBookings, true); ?>

<hr>

<h5 class="mb-3">Completed / Archived Bookings</h5>
<?php renderBookingTable($pastBookings, false); ?>