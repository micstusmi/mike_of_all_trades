<?php

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/trip_auth.php';
require_once __DIR__ . '/includes/exchange_rate.php';

requireTripLogin();

$exchangeRateData = getAudToThbRate();
$audToThbRate = (float) ($exchangeRateData['rate'] ?? 22.50);

if ($audToThbRate <= 0) {
    $audToThbRate = 22.50;
}

$exchangeRateDate =
    $exchangeRateData['date'] ?? date('Y-m-d');

$tripId = (int) $_SESSION['trip_id'];

$stmt = $pdo->prepare("
    SELECT *
    FROM trips
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$tripId]);
$trip = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$trip) {
    http_response_code(404);
    exit('Trip not found.');
}

$stmt = $pdo->prepare("
    SELECT
        td.*,
        COUNT(ti.id) AS item_count
    FROM trip_days td
    LEFT JOIN trip_items ti
        ON ti.trip_day_id = td.id
    WHERE td.trip_id = ?
    GROUP BY td.id
    ORDER BY td.trip_date, td.sort_order
");

$stmt->execute([$tripId]);
$days = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalDistance = 0;
$totalEstimatedCostAud = 0.0;
$totalEstimatedCostThb = 0.0;

foreach ($days as $day) {
    $totalDistance += (float) ($day['distance_km'] ?? 0);

    $dayCost =
        (float) ($day['estimated_cost_per_person'] ?? 0);

    $dayCurrency =
        $day['cost_currency'] ?? 'AUD';

    if ($dayCost <= 0) {
        continue;
    }

    if ($dayCurrency === 'THB') {
        $totalEstimatedCostThb += $dayCost;
        $totalEstimatedCostAud +=
            $dayCost / $audToThbRate;
    } else {
        $totalEstimatedCostAud += $dayCost;
        $totalEstimatedCostThb +=
            $dayCost * $audToThbRate;
    }
}

function formatDrivingTime(?int $minutes): string
{
    if (!$minutes) {
        return 'Not entered';
    }

    $hours = intdiv($minutes, 60);
    $remainingMinutes = $minutes % 60;

    if ($hours === 0) {
        return $remainingMinutes . ' min';
    }

    return $hours . ' hr ' . $remainingMinutes . ' min';
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

    <title>
        <?= htmlspecialchars($trip['name']) ?>
    </title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link rel="stylesheet" href="assets/trip.css">
    <link rel="stylesheet" href="assets/planner.css">
</head>

<body class="trip-dashboard">


<?php require __DIR__ . '/includes/private_nav.php'; ?>


<header class="trip-hero">
    <div class="container">

        <p class="trip-eyebrow">GROUP ITINERARY</p>

        <h1><?= htmlspecialchars($trip['name']) ?></h1>

        <p class="trip-destination">
            <?= htmlspecialchars($trip['destination'] ?? '') ?>
        </p>

        <div class="trip-date-range">
            <?= date('j M Y', strtotime($trip['start_date'])) ?>
            –
            <?= date('j M Y', strtotime($trip['end_date'])) ?>
        </div>

    </div>
</header>

<main class="container py-4">

    <div class="mb-4">
        <a
            href="planner.php"
            class="btn btn-warning btn-lg"
        >
            Open collaborative planner, bookings and expenses
        </a>
    </div>

    <section class="trip-summary-grid">

        <div class="trip-stat-card">
            <span class="trip-stat-label">Trip length</span>
            <strong>
                <?= count($days) ?> days
            </strong>
        </div>

        <div class="trip-stat-card">
            <span class="trip-stat-label">Total distance</span>
            <strong>
                <?= number_format($totalDistance, 0) ?> km
            </strong>
        </div>

        <div class="trip-stat-card">
            <span class="trip-stat-label">
                Estimated cost
            </span>

            <strong>
                A$<?= number_format(
                    $totalEstimatedCostAud,
                    0
                ) ?>
            </strong>

            <small>
                ≈ ฿<?= number_format(
                    $totalEstimatedCostThb,
                    0
                ) ?>
                per person
            </small>

            <small class="d-block mt-1">
                Rate: A$1 = ฿<?= number_format(
                    $audToThbRate,
                    2
                ) ?>
            </small>
        </div>

        <div class="trip-stat-card">
            <span class="trip-stat-label">Members</span>
            <strong>Group trip</strong>
        </div>

    </section>

    <section class="mt-5">

        <div class="d-flex justify-content-between align-items-end mb-3">
            <div>
                <p class="trip-section-label">DAY BY DAY</p>
                <h2 class="mb-0">Itinerary</h2>
            </div>

            <?php if (($_SESSION['trip_role'] ?? '') === 'admin'): ?>
                <a
                    href="admin/index.php"
                    class="btn btn-outline-primary"
                >
                    Manage trip
                </a>
            <?php endif; ?>
        </div>

        <?php if (!$days): ?>

            <div class="alert alert-info">
                No itinerary days have been added yet.
            </div>

        <?php else: ?>

            <div class="trip-timeline">

                <?php foreach ($days as $index => $day): ?>

                    <article class="trip-day-card">

                        <div class="trip-day-number">
                            <span>DAY</span>
                            <strong><?= $index + 1 ?></strong>
                        </div>

                        <div class="trip-day-content">

                            <div class="trip-day-heading">

                                <div>
                                    <div class="trip-day-date">
                                        <?= date(
                                            'l, j F Y',
                                            strtotime($day['trip_date'])
                                        ) ?>
                                    </div>

                                    <h3>
                                        <?= htmlspecialchars($day['title']) ?>
                                    </h3>
                                </div>

                                <a
                                    class="btn btn-primary"
                                    href="day.php?id=<?= (int) $day['id'] ?>"
                                >
                                    View day
                                </a>

                            </div>

                            <?php if (
                                $day['origin'] ||
                                $day['destination']
                            ): ?>
                                <div class="trip-route">
                                    <span>📍</span>

                                    <?= htmlspecialchars(
                                        $day['origin'] ?? ''
                                    ) ?>

                                    <?php if (
                                        $day['origin'] &&
                                        $day['destination']
                                    ): ?>
                                        <span class="trip-route-arrow">→</span>
                                    <?php endif; ?>

                                    <?= htmlspecialchars(
                                        $day['destination'] ?? ''
                                    ) ?>
                                </div>
                            <?php endif; ?>

                            <div class="trip-day-meta">

                                <?php if ($day['distance_km']): ?>
                                    <span>
                                        🛣️
                                        <?= number_format(
                                            (float) $day['distance_km'],
                                            0
                                        ) ?>
                                        km
                                    </span>
                                <?php endif; ?>

                                <?php if ($day['drive_minutes']): ?>
                                    <span>
                                        ⏱️
                                        <?= htmlspecialchars(
                                            formatDrivingTime(
                                                (int) $day['drive_minutes']
                                            )
                                        ) ?>
                                    </span>
                                <?php endif; ?>

                                <span>
                                    📋
                                    <?= (int) $day['item_count'] ?>
                                    itinerary items
                                </span>

                                <?php if (
                                    $day['estimated_cost_per_person']
                                ): ?>

                                    <?php
                                    $dayCost =
                                        (float) $day[
                                            'estimated_cost_per_person'
                                        ];

                                    $dayCurrency =
                                        $day['cost_currency']
                                        ?? 'AUD';

                                    if ($dayCurrency === 'THB') {
                                        $dayCostThb = $dayCost;
                                        $dayCostAud =
                                            $dayCost / $audToThbRate;
                                    } else {
                                        $dayCostAud = $dayCost;
                                        $dayCostThb =
                                            $dayCost * $audToThbRate;
                                    }
                                    ?>

                                    <span>
                                        💰 A$<?= number_format(
                                            $dayCostAud,
                                            0
                                        ) ?>
                                        ≈ ฿<?= number_format(
                                            $dayCostThb,
                                            0
                                        ) ?>
                                    </span>

                                <?php endif; ?>

                            </div>

                            <?php if ($day['summary']): ?>
                                <p class="trip-day-summary">
                                    <?= nl2br(
                                        htmlspecialchars($day['summary'])
                                    ) ?>
                                </p>
                            <?php endif; ?>

                        </div>

                    </article>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </section>

</main>

</body>
</html>