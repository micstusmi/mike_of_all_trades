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

$weeksStmt = $pdo->prepare("
    SELECT *
    FROM trip_weeks
    WHERE trip_id = ?
    ORDER BY week_number
");

$weeksStmt->execute([$tripId]);
$weeks = $weeksStmt->fetchAll(PDO::FETCH_ASSOC);

$membersStmt = $pdo->prepare("
    SELECT
        tm.id,
        tm.name,
        tm.phone,
        tm.role,
        tm.is_active,
        tp.id AS person_id,
        tp.status AS overall_status
    FROM trip_members tm
    LEFT JOIN trip_people tp
        ON tp.trip_member_id = tm.id
    WHERE tm.trip_id = ?
    ORDER BY tm.name
");

$membersStmt->execute([$tripId]);
$members = $membersStmt->fetchAll(PDO::FETCH_ASSOC);

$attendanceStmt = $pdo->prepare("
    SELECT
        tpw.person_id,
        tpw.trip_week_id,
        tpw.attendance_status
    FROM trip_person_weeks tpw
    INNER JOIN trip_people tp
        ON tp.id = tpw.person_id
    WHERE tp.trip_id = ?
");

$attendanceStmt->execute([$tripId]);

$attendance = [];

foreach ($attendanceStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $attendance[(int) $row['person_id']]
               [(int) $row['trip_week_id']] =
        $row['attendance_status'];
}

$message = $_SESSION['member_message'] ?? '';
$error = $_SESSION['member_error'] ?? '';

unset(
    $_SESSION['member_message'],
    $_SESSION['member_error']
);
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

    <title>Trip Members</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link rel="stylesheet" href="assets/planner.css">

    <style>
        .member-layout {
            display: grid;
            grid-template-columns: 380px 1fr;
            gap: 22px;
        }

        .member-card {
            margin-bottom: 15px;
            padding: 20px;
            border: 1px solid #dce4e8;
            border-radius: 16px;
            background: white;
        }

        .member-card h3 {
            margin-bottom: 5px;
        }

        .attendance-pills {
            display: flex;
            flex-wrap: wrap;
            gap: 7px;
            margin-top: 14px;
        }

        .attendance-pill {
            padding: 7px 10px;
            border-radius: 999px;
            background: #edf3f6;
            font-size: 0.8rem;
            font-weight: 700;
        }

        .member-form label {
            display: block;
            margin-bottom: 14px;
            font-weight: 700;
        }

        .member-form input,
        .member-form select {
            width: 100%;
            margin-top: 6px;
            padding: 11px;
            border: 1px solid #cbd6dc;
            border-radius: 9px;
        }

        @media (max-width: 850px) {
            .member-layout {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

<?php require __DIR__ . '/includes/private_nav.php'; ?>



<header class="planner-header">
    <div class="container">
        <span>ADMIN AREA</span>
        <h1>Trip members</h1>
        <p>
            Create individual phone-number and PIN logins for travellers.
        </p>
    </div>
</header>

<main class="container py-4">

    <?php if ($message): ?>
        <div class="alert alert-success">
            <?= e($message) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <div class="member-layout">

        <section class="planner-form-card">

            <span class="section-label">NEW TRAVELLER</span>
            <h2>Create member login</h2>

            <form
                class="member-form"
                action="actions/add_member.php"
                method="post"
            >

                <label>
                    Traveller name
                    <input
                        type="text"
                        name="name"
                        maxlength="120"
                        required
                    >
                </label>

                <label>
                    Mobile number
                    <input
                        type="tel"
                        name="phone"
                        maxlength="40"
                        required
                    >
                </label>

                <label>
                    Initial PIN
                    <input
                        type="password"
                        name="pin"
                        inputmode="numeric"
                        pattern="[0-9]{4,8}"
                        minlength="4"
                        maxlength="8"
                        required
                    >
                </label>

                <label>
                    Access level
                    <select name="role">
                        <option value="viewer">
                            Traveller
                        </option>

                        <option value="editor">
                            Editor
                        </option>

                        <option value="admin">
                            Administrator
                        </option>
                    </select>
                </label>

                <button class="btn btn-primary" type="submit">
                    Create traveller
                </button>

            </form>

        </section>

        <section>

            <div class="section-heading">
                <div>
                    <span class="section-label">CURRENT GROUP</span>
                    <h2>Member logins</h2>
                </div>
            </div>

            <?php foreach ($members as $member): ?>

                <article class="member-card">

                    <div class="d-flex justify-content-between gap-3">

                        <div>
                            <h3><?= e($member['name']) ?></h3>

                            <div class="text-muted">
                                <?= e($member['phone']) ?>
                            </div>
                        </div>

                        <div class="text-end">
                            <span class="badge text-bg-primary">
                                <?= e($member['role']) ?>
                            </span>

                            <?php if (!(int) $member['is_active']): ?>
                                <span class="badge text-bg-secondary">
                                    Disabled
                                </span>
                            <?php endif; ?>
                        </div>

                    </div>

                    <?php if ($member['person_id']): ?>

                        <div class="attendance-pills">

                            <?php foreach ($weeks as $week): ?>

                                <?php
                                $status =
                                    $attendance[
                                        (int) $member['person_id']
                                    ][
                                        (int) $week['id']
                                    ]
                                    ?? 'not selected';
                                ?>

                                <span class="attendance-pill">
                                    Week <?= (int) $week['week_number'] ?>:
                                    <?= e(str_replace('_', ' ', $status)) ?>
                                </span>

                            <?php endforeach; ?>

                        </div>

                    <?php endif; ?>

                </article>

            <?php endforeach; ?>

        </section>

    </div>

</main>

</body>
</html>
