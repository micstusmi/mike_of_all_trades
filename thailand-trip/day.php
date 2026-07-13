<?php

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/trip_auth.php';

requireTripLogin();

$tripId = (int) $_SESSION['trip_id'];
$dayId  = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$dayId) {
    http_response_code(400);
    exit('Invalid itinerary day.');
}

$stmt = $pdo->prepare("
    SELECT td.*
    FROM trip_days td
    WHERE td.id = ?
      AND td.trip_id = ?
    LIMIT 1
");

$stmt->execute([$dayId, $tripId]);
$day = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$day) {
    http_response_code(404);
    exit('Itinerary day not found.');
}

$stmt = $pdo->prepare("
    SELECT *
    FROM trip_items
    WHERE trip_day_id = ?
    ORDER BY
        CASE WHEN start_time IS NULL THEN 1 ELSE 0 END,
        start_time,
        sort_order,
        id
");

$stmt->execute([$dayId]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

function tripItemIcon(string $type): string
{
    return match ($type) {
        'accommodation' => '🏨',
        'flight'        => '✈️',
        'motorbike'     => '🏍️',
        'transport'     => '🚐',
        'meal'          => '🍜',
        'booking'       => '🎟️',
        'note'          => '📝',
        default         => '📍'
    };
}

function formatTripTime(?string $time): string
{
    if (!$time) {
        return '';
    }

    return date('g:i A', strtotime($time));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <meta name="robots" content="noindex, nofollow">

    <title><?= htmlspecialchars($day['title']) ?></title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link rel="stylesheet" href="assets/trip.css">
</head>

<body class="trip-dashboard">

<nav class="navbar navbar-dark trip-navbar">
    <div class="container">

        <a class="navbar-brand" href="dashboard.php">
            ← Full itinerary
        </a>

        <a class="btn btn-outline-light btn-sm" href="logout.php">
            Log out
        </a>

    </div>
</nav>

<header class="trip-day-hero">
    <div class="container">

        <div class="trip-day-date">
            <?= date(
                'l, j F Y',
                strtotime($day['trip_date'])
            ) ?>
        </div>

        <h1><?= htmlspecialchars($day['title']) ?></h1>

        <?php if ($day['origin'] || $day['destination']): ?>
            <p class="trip-destination">
                <?= htmlspecialchars($day['origin'] ?? '') ?>

                <?php if ($day['origin'] && $day['destination']): ?>
                    →
                <?php endif; ?>

                <?= htmlspecialchars($day['destination'] ?? '') ?>
            </p>
        <?php endif; ?>

    </div>
</header>

<main class="container py-4">

    <section class="trip-summary-grid mb-5">

        <div class="trip-stat-card">
            <span class="trip-stat-label">Distance</span>

            <strong>
                <?= $day['distance_km']
                    ? number_format((float) $day['distance_km'], 0) . ' km'
                    : 'TBC'
                ?>
            </strong>
        </div>

        <div class="trip-stat-card">
            <span class="trip-stat-label">Riding time</span>

            <strong>
                <?php
                if ($day['drive_minutes']) {
                    $hours = intdiv((int) $day['drive_minutes'], 60);
                    $minutes = (int) $day['drive_minutes'] % 60;

                    echo $hours . ' hr ' . $minutes . ' min';
                } else {
                    echo 'TBC';
                }
                ?>
            </strong>
        </div>

        <div class="trip-stat-card">
            <span class="trip-stat-label">Estimated cost</span>

            <strong>
                <?= $day['estimated_cost_per_person']
                    ? '฿' . number_format(
                        (float) $day['estimated_cost_per_person'],
                        0
                    )
                    : 'TBC'
                ?>
            </strong>

            <small>per person</small>
        </div>

        <div class="trip-stat-card">
            <span class="trip-stat-label">Stops</span>
            <strong><?= count($items) ?></strong>
        </div>

    </section>

    <?php if ($day['summary']): ?>
        <section class="trip-info-panel mb-4">
            <h2>Day overview</h2>

            <p>
                <?= nl2br(htmlspecialchars($day['summary'])) ?>
            </p>
        </section>
    <?php endif; ?>

    <?php if ($day['map_url']): ?>
        <div class="mb-4">
            <a
                href="<?= htmlspecialchars($day['map_url']) ?>"
                target="_blank"
                rel="noopener noreferrer"
                class="btn btn-outline-primary"
            >
                Open full route in Google Maps
            </a>
        </div>
    <?php endif; ?>

    <section>
        <p class="trip-section-label">TODAY’S PLAN</p>
        <h2>Schedule</h2>

        <?php if (!$items): ?>

            <div class="alert alert-info">
                No activities have been added for this day.
            </div>

        <?php else: ?>

            <div class="trip-item-list">

                <?php foreach ($items as $item): ?>

                    <article class="trip-item-card">

                        <div class="trip-item-time">
                            <?= $item['start_time']
                                ? htmlspecialchars(
                                    formatTripTime($item['start_time'])
                                )
                                : 'Any time'
                            ?>
                        </div>

                        <div class="trip-item-icon">
                            <?= tripItemIcon($item['item_type']) ?>
                        </div>

                        <div class="trip-item-body">

                            <div class="trip-item-title-row">
                                <h3>
                                    <?= htmlspecialchars($item['title']) ?>
                                </h3>

                                <?php if ($item['cost_amount']): ?>
                                    <span class="trip-cost-badge">
                                        <?= htmlspecialchars(
                                            $item['cost_currency']
                                        ) ?>
                                        <?= number_format(
                                            (float) $item['cost_amount'],
                                            0
                                        ) ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <?php if ($item['location_name']): ?>
                                <p class="trip-item-location">
                                    📍
                                    <?= htmlspecialchars(
                                        $item['location_name']
                                    ) ?>
                                </p>
                            <?php endif; ?>

                            <?php if ($item['details']): ?>
                                <p>
                                    <?= nl2br(
                                        htmlspecialchars($item['details'])
                                    ) ?>
                                </p>
                            <?php endif; ?>

                            <?php if ($item['booking_reference']): ?>
                                <p>
                                    <strong>Booking reference:</strong>
                                    <?= htmlspecialchars(
                                        $item['booking_reference']
                                    ) ?>
                                </p>
                            <?php endif; ?>

                            <div class="trip-item-links">

                                <?php if ($item['map_url']): ?>
                                    <a
                                        href="<?=
                                            htmlspecialchars(
                                                $item['map_url']
                                            )
                                        ?>"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        Open map
                                    </a>
                                <?php endif; ?>

                                <?php if ($item['website_url']): ?>
                                    <a
                                        href="<?=
                                            htmlspecialchars(
                                                $item['website_url']
                                            )
                                        ?>"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        Visit website
                                    </a>
                                <?php endif; ?>

                            </div>

                        </div>

                    </article>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </section>

</main>

</body>
</html>