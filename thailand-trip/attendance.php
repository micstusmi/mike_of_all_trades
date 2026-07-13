<?php

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/trip_auth.php';
requireTripLogin();

$tripId = (int) $_SESSION['trip_id'];
$memberId = (int) $_SESSION['trip_member_id'];

function e(?string $value): string
{
    return htmlspecialchars(
        $value ?? '',
        ENT_QUOTES,
        'UTF-8'
    );
}

$personStmt = $pdo->prepare("
    SELECT *
    FROM trip_people
    WHERE trip_id = ?
      AND trip_member_id = ?
    LIMIT 1
");

$personStmt->execute([
    $tripId,
    $memberId
]);

$person = $personStmt->fetch(PDO::FETCH_ASSOC);

if (!$person) {
    $createStmt = $pdo->prepare("
        INSERT INTO trip_people (
            trip_id,
            trip_member_id,
            name,
            status
        ) VALUES (?, ?, ?, 'interested')
    ");

    $createStmt->execute([
        $tripId,
        $memberId,
        $_SESSION['trip_member_name']
    ]);

    $personId = (int) $pdo->lastInsertId();

    $person = [
        'id' => $personId,
        'name' => $_SESSION['trip_member_name'],
        'status' => 'interested'
    ];
}

$weeksStmt = $pdo->prepare("
    SELECT
        tw.*,
        tpw.attendance_status
    FROM trip_weeks tw
    LEFT JOIN trip_person_weeks tpw
        ON tpw.trip_week_id = tw.id
       AND tpw.person_id = ?
    WHERE tw.trip_id = ?
    ORDER BY tw.week_number
");

$weeksStmt->execute([
    $person['id'],
    $tripId
]);

$weeks = $weeksStmt->fetchAll(PDO::FETCH_ASSOC);

$message = $_SESSION['attendance_message'] ?? '';
unset($_SESSION['attendance_message']);
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

    <title>My Attendance</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link rel="stylesheet" href="assets/planner.css">

    <style>
        .attendance-card {
            margin-bottom: 14px;
            padding: 20px;
            display: grid;
            grid-template-columns: 1fr 220px;
            gap: 20px;
            align-items: center;
            border: 1px solid #dce4e8;
            border-radius: 16px;
            background: white;
        }

        .attendance-card h3 {
            margin-bottom: 4px;
        }

        .attendance-card select {
            width: 100%;
            padding: 11px;
            border: 1px solid #cbd6dc;
            border-radius: 9px;
        }

        @media (max-width: 650px) {
            .attendance-card {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

<?php require __DIR__ . '/includes/private_nav.php'; ?>



<header class="planner-header">
    <div class="container">
        <span>YOUR TRIP PLANS</span>
        <h1>Which weeks are you joining?</h1>
        <p>
            Update your attendance whenever your plans change.
        </p>
    </div>
</header>

<main class="container py-4">

    <?php if ($message): ?>
        <div class="alert alert-success">
            <?= e($message) ?>
        </div>
    <?php endif; ?>

    <form action="actions/save_attendance.php" method="post">

        <?php foreach ($weeks as $week): ?>

            <article class="attendance-card">

                <div>
                    <span class="section-label">
                        WEEK <?= (int) $week['week_number'] ?>
                    </span>

                    <h3><?= e($week['title']) ?></h3>

                    <div class="text-muted">
                        <?= date(
                            'j F',
                            strtotime($week['start_date'])
                        ) ?>
                        –
                        <?= date(
                            'j F Y',
                            strtotime($week['end_date'])
                        ) ?>
                    </div>
                </div>

                <select
                    name="attendance[<?= (int) $week['id'] ?>]"
                >
                    <?php
                    $current =
                        $week['attendance_status']
                        ?? 'interested';

                    $options = [
                        'interested' => 'Interested',
                        'likely' => 'Likely to come',
                        'confirmed' => 'Confirmed',
                        'not_coming' => 'Not joining this week'
                    ];
                    ?>

                    <?php foreach ($options as $value => $label): ?>
                        <option
                            value="<?= e($value) ?>"
                            <?= $current === $value
                                ? 'selected'
                                : ''
                            ?>
                        >
                            <?= e($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

            </article>

        <?php endforeach; ?>

        <button class="btn btn-primary btn-lg" type="submit">
            Save my attendance
        </button>

    </form>

</main>

</body>
</html>
