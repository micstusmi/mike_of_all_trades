<?php

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/trip_auth.php';

$tripId = 1;

$tripStmt = $pdo->prepare("
    SELECT *
    FROM trips
    WHERE id = ?
    LIMIT 1
");

$tripStmt->execute([$tripId]);
$trip = $tripStmt->fetch(PDO::FETCH_ASSOC);

if (!$trip) {
    http_response_code(404);
    exit('Trip not found.');
}

$weekStmt = $pdo->prepare("
    SELECT
        tw.*,
        (
            tw.estimated_accommodation
            + tw.estimated_transport
            + tw.estimated_bike_or_car
            + tw.estimated_activities
            + tw.estimated_food
            + tw.estimated_other
        ) AS estimated_total,
        COUNT(DISTINCT CASE
            WHEN tir.attendance_status IN ('interested', 'likely', 'confirmed')
            THEN tir.id
        END) AS interest_count
    FROM trip_weeks tw
    LEFT JOIN trip_interest_weeks tiw
        ON tiw.trip_week_id = tw.id
    LEFT JOIN trip_interest_responses tir
        ON tir.id = tiw.response_id
    WHERE tw.trip_id = ?
      AND tw.is_public = 1
    GROUP BY tw.id
    ORDER BY tw.display_order, tw.week_number
");

$weekStmt->execute([$tripId]);
$weeks = $weekStmt->fetchAll(PDO::FETCH_ASSOC);

$milestoneStmt = $pdo->prepare("
    SELECT
        tm.*,
        tw.week_number
    FROM trip_milestones tm
    LEFT JOIN trip_weeks tw
        ON tw.id = tm.trip_week_id
    WHERE tm.trip_id = ?
    ORDER BY tm.milestone_date, tm.display_order
");

$milestoneStmt->execute([$tripId]);
$milestones = $milestoneStmt->fetchAll(PDO::FETCH_ASSOC);

$totalEstimate = 0;

foreach ($weeks as $week) {
    $totalEstimate += (float) $week['estimated_total'];
}

function e(?string $value): string
{
    return htmlspecialchars(
        $value ?? '',
        ENT_QUOTES,
        'UTF-8'
    );
}

function displayDate(string $date): string
{
    return date('j F Y', strtotime($date));
}

function displayShortDate(string $date): string
{
    return date('j M', strtotime($date));
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

    <meta
        name="description"
        content="Three-week Thailand group adventure covering northern motorcycle roads, Jomtien and the Buriram MotoGP."
    >

    <title><?= e($trip['name']) ?></title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="assets/overview.css"
    >
</head>

<body>

<nav class="overview-nav">
    <div class="container overview-nav-inner">

        <a class="overview-brand" href="index.php">
            <span>🏍️</span>
            Thailand 2027
        </a>

        <?php if (tripMemberIsLoggedIn()): ?>

            <div class="d-flex align-items-center gap-2">

                <span class="text-white d-none d-md-inline">
                    Hi, <?= e($_SESSION['trip_member_name'] ?? '') ?>
                </span>

                <a
                    class="btn btn-warning"
                    href="planner.php"
                >
                    Private planner
                </a>

                <a
                    class="btn btn-outline-light"
                    href="logout.php"
                >
                    Log out
                </a>

            </div>

        <?php else: ?>

            <a
                class="btn btn-outline-light"
                href="access.php"
            >
                Group member login
            </a>

        <?php endif; ?>

    </div>
</nav>

<header class="overview-hero overview-hero-teasers">
    <div class="container">

        <div class="hero-intro">

            <div>
                <div class="overview-kicker">
                    THAILAND 2027 · JOIN ONE, TWO OR ALL THREE
                </div>

                <h1>Choose your Thailand adventure</h1>

                <p>
                    Click a week to see its itinerary, dates and
                    estimated costs.
                </p>
            </div>

            <div class="hero-trip-dates">
                <span>9 February – 3 March 2027</span>
                <small>
                    Full trip estimate:
                    A$<?= number_format($totalEstimate, 0) ?>
                </small>
            </div>

        </div>

        <div class="hero-week-grid">

            <?php foreach ($weeks as $week): ?>

                <?php
                $heroImage = match ((int) $week['week_number']) {
                    1 => 'assets/images/week1-northern-thailand.jpg',
                    2 => 'assets/images/week2-jomtien-big-boss-bar.jpg',
                    3 => 'assets/images/week3-buriram-motogp.jpg',
                    default => ''
                };

                $heroIcon = match ((int) $week['week_number']) {
                    1 => '🏍️',
                    2 => '🍻',
                    3 => '🏁',
                    default => '🇹🇭'
                };
                ?>

                <a
                    class="hero-week-card"
                    href="#week-<?= (int) $week['week_number'] ?>"
                    style="background-image:
                        linear-gradient(
                            to top,
                            rgba(3, 20, 31, 0.92),
                            rgba(3, 20, 31, 0.05) 65%
                        ),
                        url('<?= e($heroImage) ?>');"
                >

                    <div class="hero-week-top">

                        <span class="hero-week-number">
                            Week <?= (int) $week['week_number'] ?>
                        </span>

                        <span class="hero-week-icon">
                            <?= $heroIcon ?>
                        </span>

                    </div>

                    <div class="hero-week-content">

                        <div class="hero-week-date">
                            <?= displayShortDate($week['start_date']) ?>
                            –
                            <?= displayShortDate($week['end_date']) ?>
                        </div>

                        <h2><?= e($week['title']) ?></h2>

                        <p><?= e($week['subtitle']) ?></p>

                        <span class="hero-week-link">
                            View this week →
                        </span>

                    </div>

                </a>

            <?php endforeach; ?>

        </div>

        <div class="hero-bottom-actions">

            <a
                class="btn btn-warning btn-lg"
                href="#interest"
            >
                Tell us which week interests you
            </a>

            <a
                class="btn btn-outline-light btn-lg"
                href="#timeline"
            >
                View full trip timeline
            </a>

        </div>

    </div>
</header>

<main>

<section class="overview-intro">
    <div class="container">

        <div class="overview-intro-grid">

            <div>
                <div class="section-label">THE IDEA</div>

                <h2>Come for the part that suits you</h2>

                <p>
                    Nobody has to commit to the full trip. Join the
                    motorcycle tour, meet the group in Jomtien, attend
                    the planned MotoGP week in Buriram, or combine any
                    of the three.
                </p>
            </div>

            <div class="overview-total-card">
                <span>Current full-trip estimate</span>

                <strong>
                    A$<?= number_format($totalEstimate, 0) ?>
                </strong>

                <small>
                    Per person, excluding international flights.
                    Every estimate can be updated as bookings are confirmed.
                </small>
            </div>

        </div>

    </div>
</section>

<section class="weeks-section" id="weeks">
    <div class="container">

        <div class="section-label">CHOOSE YOUR ADVENTURE</div>
        <h2>Three separate trip sections</h2>

        <div class="week-grid">

            <?php foreach ($weeks as $week): ?>

                <article
                    class="week-card"
                    id="week-<?= (int) $week['week_number'] ?>"
                >

                    <?php
                    $weekImage = match ((int) $week['week_number']) {
                        1 => 'assets/images/week1-northern-thailand.jpg',
                        2 => 'assets/images/week2-jomtien-big-boss-bar.jpg',
                        3 => 'assets/images/week3-buriram-motogp.jpg',
                        default => null
                    };

                    $weekImageAlt = match ((int) $week['week_number']) {
                        1 => 'Northern Thailand mountain motorcycle roads',
                        2 => 'Big Boss Bar and Cafe in Jomtien',
                        3 => 'Chang International Circuit in Buriram',
                        default => 'Thailand group trip'
                    };
                    ?>

                    <?php if ($weekImage): ?>
                        <div class="week-card-image-wrap">
                            <img
                                class="week-card-image"
                                src="<?= e($weekImage) ?>"
                                alt="<?= e($weekImageAlt) ?>"
                                loading="lazy"
                            >

                            <div class="week-card-image-overlay">
                                <span>
                                    Week <?= (int) $week['week_number'] ?>
                                </span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="week-card-header">
                        <span class="week-number">
                            Week <?= (int) $week['week_number'] ?>
                        </span>

                        <span class="week-dates">
                            <?= displayShortDate($week['start_date']) ?>
                            –
                            <?= displayShortDate($week['end_date']) ?>
                        </span>
                    </div>

                    <div class="week-card-body">

                        <div class="week-icon">
                            <?php
                            echo match ((int) $week['week_number']) {
                                1 => '🏍️',
                                2 => '🏖️',
                                3 => '🏁',
                                default => '🇹🇭'
                            };
                            ?>
                        </div>

                        <h3><?= e($week['title']) ?></h3>

                        <p class="week-subtitle">
                            <?= e($week['subtitle']) ?>
                        </p>

                        <div class="week-location">
                            📍 <?= e($week['location']) ?>
                        </div>

                        <p>
                            <?= e($week['description']) ?>
                        </p>

                        <div class="week-transport">
                            <strong>Transport:</strong>
                            <?= e($week['transport_summary']) ?>
                        </div>

                        <div class="week-cost-row">
                            <div>
                                <span>Estimated cost</span>

                                <strong>
                                    A$<?= number_format(
                                        (float) $week['estimated_total'],
                                        0
                                    ) ?>
                                </strong>

                                <small>per person</small>
                            </div>

                            <div class="week-interest">
                                <?= (int) $week['interest_count'] ?>
                                interested
                            </div>
                        </div>

                        <details class="cost-details">
                            <summary>View cost estimate</summary>

                            <dl>
                                <div>
                                    <dt>Accommodation</dt>
                                    <dd>
                                        A$<?= number_format(
                                            (float)
                                            $week['estimated_accommodation'],
                                            0
                                        ) ?>
                                    </dd>
                                </div>

                                <div>
                                    <dt>Major transport</dt>
                                    <dd>
                                        A$<?= number_format(
                                            (float)
                                            $week['estimated_transport'],
                                            0
                                        ) ?>
                                    </dd>
                                </div>

                                <div>
                                    <dt>Bike/car and local travel</dt>
                                    <dd>
                                        A$<?= number_format(
                                            (float)
                                            $week['estimated_bike_or_car'],
                                            0
                                        ) ?>
                                    </dd>
                                </div>

                                <div>
                                    <dt>Activities</dt>
                                    <dd>
                                        A$<?= number_format(
                                            (float)
                                            $week['estimated_activities'],
                                            0
                                        ) ?>
                                    </dd>
                                </div>

                                <div>
                                    <dt>Food</dt>
                                    <dd>
                                        A$<?= number_format(
                                            (float)
                                            $week['estimated_food'],
                                            0
                                        ) ?>
                                    </dd>
                                </div>

                                <div>
                                    <dt>Other allowance</dt>
                                    <dd>
                                        A$<?= number_format(
                                            (float)
                                            $week['estimated_other'],
                                            0
                                        ) ?>
                                    </dd>
                                </div>
                            </dl>
                        </details>

                    </div>

                </article>

            <?php endforeach; ?>

        </div>

    </div>
</section>

<section class="timeline-section" id="timeline">
    <div class="container">

        <div class="section-label">TRIP AT A GLANCE</div>
        <h2>Overall travel timeline</h2>

        <div class="overview-timeline">

            <?php foreach ($milestones as $milestone): ?>

                <article class="timeline-entry">

                    <div class="timeline-date">
                        <?= displayShortDate($milestone['milestone_date']) ?>

                        <?php if ($milestone['end_date']): ?>
                            <span>
                                to
                                <?= displayShortDate(
                                    $milestone['end_date']
                                ) ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="timeline-marker"></div>

                    <div class="timeline-content">

                        <span class="timeline-type">
                            <?= e($milestone['transport_type']) ?>
                        </span>

                        <h3><?= e($milestone['title']) ?></h3>

                        <div class="timeline-location">
                            📍 <?= e($milestone['location']) ?>
                        </div>

                        <p><?= e($milestone['description']) ?></p>

                        <?php if ($milestone['map_url']): ?>
                            <a
                                href="<?= e($milestone['map_url']) ?>"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                View location or route →
                            </a>
                        <?php endif; ?>

                    </div>

                </article>

            <?php endforeach; ?>

        </div>

    </div>
</section>

<section class="interest-section" id="interest">
    <div class="container">

        <div class="interest-panel">

            <div>
                <div class="section-label">NO COMMITMENT REQUIRED</div>

                <h2>Which part might you join?</h2>

                <p>
                    Add your name and select any sections that interest
                    you. This helps us estimate group sizes before anyone
                    needs to make a firm commitment.
                </p>
            </div>

            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success">
                    Thanks — your response has been saved.
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger">
                    Please enter your name and select at least one week.
                </div>
            <?php endif; ?>

            <form
                action="interest_submit.php"
                method="post"
                class="interest-form"
            >

                <input
                    type="text"
                    name="website"
                    tabindex="-1"
                    autocomplete="off"
                    class="honeypot"
                    aria-hidden="true"
                >

                <div class="form-grid">

                    <div>
                        <label for="name">Your name *</label>

                        <input
                            type="text"
                            id="name"
                            name="name"
                            maxlength="120"
                            required
                        >
                    </div>

                    <div>
                        <label for="phone">Mobile number</label>

                        <input
                            type="tel"
                            id="phone"
                            name="phone"
                            maxlength="40"
                        >
                    </div>

                    <div>
                        <label for="email">Email</label>

                        <input
                            type="email"
                            id="email"
                            name="email"
                            maxlength="190"
                        >
                    </div>

                    <div>
                        <label for="status">Current level of interest</label>

                        <select id="status" name="status">
                            <option value="interested">
                                Interested
                            </option>

                            <option value="likely">
                                Likely to come
                            </option>

                            <option value="confirmed">
                                Confirmed
                            </option>

                            <option value="not_coming">
                                Cannot come
                            </option>
                        </select>
                    </div>

                </div>

                <fieldset>
                    <legend>Select any sections that suit you *</legend>

                    <div class="week-checkboxes">

                        <?php foreach ($weeks as $week): ?>

                            <label class="week-checkbox">

                                <input
                                    type="checkbox"
                                    name="weeks[]"
                                    value="<?= (int) $week['id'] ?>"
                                >

                                <span>
                                    <strong>
                                        Week <?= (int) $week['week_number'] ?>:
                                        <?= e($week['title']) ?>
                                    </strong>

                                    <small>
                                        <?= displayShortDate(
                                            $week['start_date']
                                        ) ?>
                                        –
                                        <?= displayShortDate(
                                            $week['end_date']
                                        ) ?>
                                    </small>
                                </span>

                            </label>

                        <?php endforeach; ?>

                    </div>
                </fieldset>

                <div>
                    <label for="notes">
                        Questions or notes
                    </label>

                    <textarea
                        id="notes"
                        name="notes"
                        rows="4"
                        maxlength="2000"
                    ></textarea>
                </div>

                <button class="btn btn-warning btn-lg" type="submit">
                    Save my interest
                </button>

            </form>

        </div>

    </div>
</section>

</main>

<footer class="overview-footer">
    <div class="container">
        <p>
            Preliminary group itinerary. Dates, bookings, routes and costs
            remain subject to confirmation.
        </p>

        <a href="access.php">
            Private group member area
        </a>
    </div>
</footer>

</body>
</html>
