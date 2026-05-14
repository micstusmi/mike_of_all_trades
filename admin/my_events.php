<?php
require '../includes/auth_admin.php';
require_once '../includes/db.php';

$stmt = $pdo->query("
    SELECT *
    FROM calendar_events
    WHERE is_buffer = 0
    AND end_datetime >= NOW()
    ORDER BY start_datetime ASC
");

$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$events) {
    echo "<p class='mb-0 text-muted'>No bookings or blockouts yet.</p>";
    exit;
}
?>

<table class="table table-striped table-hover mb-0">
    <thead>
        <tr>
            <th>Date</th>
            <th>Time</th>
            <th>Type</th>
            <th>Title</th>
            <th>Notes</th>
            <th style="width:180px;">Actions</th>
        </tr>
    </thead>

    <tbody>
        <?php foreach ($events as $event): ?>
            <?php
                $start = new DateTime($event['start_datetime']);
                $end = new DateTime($event['end_datetime']);
            ?>

            <tr>
                <td><?= htmlspecialchars($start->format('d/m/Y')) ?></td>
                <td>
                    <?= htmlspecialchars($start->format('g:i A')) ?>
                    -
                    <?= htmlspecialchars($end->format('g:i A')) ?>
                </td>
                <td><?= htmlspecialchars($event['event_type']) ?></td>
                <td><?= htmlspecialchars($event['title']) ?></td>
                <td><?= htmlspecialchars($event['notes'] ?? '') ?></td>
                <td>
                    <button
                        class="btn btn-sm btn-outline-primary"
                        onclick="editAdminEvent(<?= (int)$event['id'] ?>)"
                    >
                        Edit
                    </button>

                    <button
                        class="btn btn-sm btn-danger"
                        onclick="deleteAdminEvent(<?= (int)$event['id'] ?>)"
                    >
                        Delete
                    </button>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>