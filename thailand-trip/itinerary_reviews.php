<?php

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/trip_auth.php';
requireTripAdmin();

$tripId = (int) $_SESSION['trip_id'];

function e(?string $value): string
{
    return htmlspecialchars(
        $value ?? '',
        ENT_QUOTES,
        'UTF-8'
    );
}

function formatValue(
    mixed $value,
    string $field
): string {
    if ($value === null || $value === '') {
        return '—';
    }

    if ($field === 'trip_week_id') {
        return 'Week record #' . (int) $value;
    }

    if ($field === 'distance_km') {
        return number_format((float) $value, 1) . ' km';
    }

    if ($field === 'drive_minutes') {
        $minutes = (int) $value;
        $hours = intdiv($minutes, 60);
        $remainder = $minutes % 60;

        return ($hours ? $hours . ' hr ' : '')
            . ($remainder ? $remainder . ' min' : '');
    }

    if ($field === 'estimated_cost_per_person') {
        return (string) $value;
    }

    return (string) $value;
}

$fieldLabels = [
    'trip_week_id' => 'Week',
    'trip_date' => 'Date',
    'title' => 'Title',
    'origin' => 'Starting location',
    'destination' => 'Destination',
    'travel_mode' => 'Travel mode',
    'distance_km' => 'Distance',
    'drive_minutes' => 'Travel time',
    'summary' => 'Summary and activities',
    'map_url' => 'Google Maps link',
    'estimated_cost_per_person' =>
        'Estimated cost per person'
];

$stmt = $pdo->prepare("
    SELECT
        tip.*,
        tm.name AS submitted_by_name,
        td.title AS current_day_title
    FROM trip_itinerary_proposals tip
    INNER JOIN trip_members tm
        ON tm.id = tip.submitted_by_member_id
    LEFT JOIN trip_days td
        ON td.id = tip.trip_day_id
    WHERE tip.trip_id = ?
      AND tip.status = 'pending'
    ORDER BY tip.submitted_at
");

$stmt->execute([$tripId]);
$proposals = $stmt->fetchAll(PDO::FETCH_ASSOC);

$message = $_SESSION['review_message'] ?? '';
unset($_SESSION['review_message']);
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

    <title>Itinerary Change Approvals</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link rel="stylesheet" href="assets/planner.css">

    <style>
        .review-card {
            margin-bottom: 24px;
            padding: 24px;
            border: 1px solid #dce4e8;
            border-radius: 18px;
            background: white;
        }

        .comparison-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin: 20px 0;
        }

        .comparison-panel {
            overflow: hidden;
            border: 1px solid #dce4e8;
            border-radius: 14px;
        }

        .comparison-panel h3 {
            margin: 0;
            padding: 13px 16px;
            font-size: 1rem;
        }

        .comparison-panel.current h3 {
            background: #edf1f3;
        }

        .comparison-panel.proposed h3 {
            background: #fff0bd;
        }

        .comparison-row {
            padding: 13px 16px;
            border-top: 1px solid #e4eaed;
        }

        .comparison-row.changed {
            background: #fff9e6;
        }

        .comparison-label {
            display: block;
            margin-bottom: 5px;
            color: #667681;
            font-size: 0.75rem;
            font-weight: 800;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        .comparison-value {
            white-space: pre-wrap;
            overflow-wrap: anywhere;
        }

        .review-actions {
            display: grid;
            grid-template-columns: 1fr auto auto;
            gap: 10px;
            align-items: end;
        }

        .review-actions textarea {
            width: 100%;
            min-height: 80px;
            padding: 10px;
            border: 1px solid #cbd6dc;
            border-radius: 9px;
        }

        @media (max-width: 750px) {
            .comparison-grid {
                grid-template-columns: 1fr;
            }

            .review-actions {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

<?php require __DIR__ . '/includes/private_nav.php'; ?>



<header class="planner-header compact-planner-header">
    <div class="container">
        <span>ADMIN APPROVAL</span>
        <h1>Proposed itinerary changes</h1>
        <p>
            Compare the existing itinerary with each proposed change.
        </p>
    </div>
</header>

<main class="container py-4">

    <?php if ($message): ?>
        <div class="alert alert-success">
            <?= e($message) ?>
        </div>
    <?php endif; ?>

    <?php if (!$proposals): ?>
        <div class="alert alert-info">
            There are currently no proposed changes awaiting approval.
        </div>
    <?php endif; ?>

    <?php foreach ($proposals as $proposal): ?>

        <?php
        $oldData = $proposal['original_data']
            ? json_decode(
                $proposal['original_data'],
                true
            )
            : [];

        $newData = json_decode(
            $proposal['proposed_data'],
            true
        ) ?: [];
        ?>

        <article class="review-card">

            <div class="d-flex flex-wrap justify-content-between gap-3">

                <div>
                    <span class="section-label">
                        <?= e(strtoupper(
                            $proposal['proposal_type']
                        )) ?>
                    </span>

                    <h2>
                        <?= e(
                            $newData['title']
                            ?? $proposal['current_day_title']
                            ?? 'New itinerary day'
                        ) ?>
                    </h2>

                    <div class="text-muted">
                        Proposed by
                        <strong>
                            <?= e($proposal['submitted_by_name']) ?>
                        </strong>
                        on
                        <?= e($proposal['submitted_at']) ?>
                    </div>
                </div>

                <span class="badge text-bg-warning align-self-start">
                    Awaiting approval
                </span>

            </div>

            <div class="comparison-grid">

                <section class="comparison-panel current">
                    <h3>Current itinerary</h3>

                    <?php foreach ($fieldLabels as $field => $label): ?>

                        <?php
                        $oldValue = $oldData[$field] ?? null;
                        $newValue = $newData[$field] ?? null;
                        $changed = $oldValue != $newValue;
                        ?>

                        <div class="comparison-row <?= $changed
                            ? 'changed'
                            : ''
                        ?>">
                            <span class="comparison-label">
                                <?= e($label) ?>
                            </span>

                            <div class="comparison-value">
                                <?= nl2br(e(formatValue(
                                    $oldValue,
                                    $field
                                ))) ?>
                            </div>
                        </div>

                    <?php endforeach; ?>
                </section>

                <section class="comparison-panel proposed">
                    <h3>Proposed itinerary</h3>

                    <?php foreach ($fieldLabels as $field => $label): ?>

                        <?php
                        $oldValue = $oldData[$field] ?? null;
                        $newValue = $newData[$field] ?? null;
                        $changed = $oldValue != $newValue;
                        ?>

                        <div class="comparison-row <?= $changed
                            ? 'changed'
                            : ''
                        ?>">
                            <span class="comparison-label">
                                <?= e($label) ?>
                            </span>

                            <div class="comparison-value">
                                <?= nl2br(e(formatValue(
                                    $newValue,
                                    $field
                                ))) ?>
                            </div>
                        </div>

                    <?php endforeach; ?>
                </section>

            </div>

            <form
                action="actions/review_itinerary_proposal.php"
                method="post"
                class="review-actions"
            >

                <input
                    type="hidden"
                    name="proposal_id"
                    value="<?= (int) $proposal['id'] ?>"
                >

                <label>
                    Review note
                    <textarea
                        name="review_notes"
                        placeholder="Optional reason or instructions..."
                    ></textarea>
                </label>

                <button
                    class="btn btn-success"
                    type="submit"
                    name="decision"
                    value="approve"
                >
                    Approve change
                </button>

                <button
                    class="btn btn-outline-danger"
                    type="submit"
                    name="decision"
                    value="reject"
                >
                    Reject
                </button>

            </form>

        </article>

    <?php endforeach; ?>

</main>

</body>
</html>
