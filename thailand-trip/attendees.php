<?php

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/trip_auth.php';

requireTripLogin();

$tripId = (int) ($_SESSION['trip_id'] ?? 0);
$isAdmin = ($_SESSION['trip_role'] ?? '') === 'admin';

function e(?string $value): string
{
    return htmlspecialchars(
        $value ?? '',
        ENT_QUOTES,
        'UTF-8'
    );
}

function normaliseContact(?string $value): string
{
    return strtolower(
        preg_replace(
            '/[^a-zA-Z0-9@.]+/',
            '',
            $value ?? ''
        )
    );
}

function attendanceLabel(string $status): string
{
    return match ($status) {
        'confirmed' => 'Confirmed',
        'likely' => 'Likely',
        'not_coming' => 'Not coming',
        default => 'Interested'
    };
}

function attendanceIcon(string $status): string
{
    return match ($status) {
        'confirmed' => '✅',
        'likely' => '👍',
        'not_coming' => '❌',
        default => '🤔'
    };
}

/*
 * Private planner members.
 */
$membersStmt = $pdo->prepare("
    SELECT
        id,
        name,
        phone,
        NULL AS email,
        role,
        is_active,
        created_at
    FROM trip_members
    WHERE trip_id = ?
    ORDER BY
        is_active DESC,
        name
");

$membersStmt->execute([$tripId]);

$members = $membersStmt->fetchAll(PDO::FETCH_ASSOC);

/*
 * Public expressions of interest and selected weeks.
 */
$responsesStmt = $pdo->prepare("
    SELECT
        r.id,
        r.name,
        r.phone,
        r.email,
        r.attendance_status,
        r.notes,
        r.created_at,
        r.updated_at,
        GROUP_CONCAT(
            CONCAT(
                'Week ',
                w.week_number,
                ': ',
                w.title
            )
            ORDER BY w.week_number
            SEPARATOR '||'
        ) AS selected_weeks
    FROM trip_interest_responses r
    LEFT JOIN trip_interest_weeks iw
        ON iw.response_id = r.id
    LEFT JOIN trip_weeks w
        ON w.id = iw.trip_week_id
    WHERE r.trip_id = ?
    GROUP BY
        r.id,
        r.name,
        r.phone,
        r.email,
        r.attendance_status,
        r.notes,
        r.created_at,
        r.updated_at
    ORDER BY
        FIELD(
            r.attendance_status,
            'confirmed',
            'likely',
            'interested',
            'not_coming'
        ),
        r.created_at DESC
");

$responsesStmt->execute([$tripId]);

$responses = $responsesStmt->fetchAll(PDO::FETCH_ASSOC);

/*
 * Match public registrations to private accounts.
 */
$memberContacts = [];

foreach ($members as $member) {
    foreach ([
        $member['phone'] ?? '',
        $member['email'] ?? ''
    ] as $contact) {
        $normalised = normaliseContact($contact);

        if ($normalised !== '') {
            $memberContacts[$normalised] = true;
        }
    }
}

foreach ($responses as &$response) {
    $response['has_login'] = false;

    foreach ([
        $response['phone'] ?? '',
        $response['email'] ?? ''
    ] as $contact) {
        $normalised = normaliseContact($contact);

        if (
            $normalised !== ''
            && isset($memberContacts[$normalised])
        ) {
            $response['has_login'] = true;
            break;
        }
    }

    $response['weeks'] =
        $response['selected_weeks']
            ? explode('||', $response['selected_weeks'])
            : [];
}

unset($response);

$groups = [
    'confirmed' => [],
    'likely' => [],
    'interested' => [],
    'not_coming' => []
];

foreach ($responses as $response) {
    $status = $response['attendance_status'];

    if (!isset($groups[$status])) {
        $status = 'interested';
    }

    $groups[$status][] = $response;
}

/*
 * Active private members without a matching public registration are
 * still treated as confirmed planner attendees.
 */
$registeredMemberContacts = [];

foreach ($responses as $response) {
    foreach ([
        $response['phone'] ?? '',
        $response['email'] ?? ''
    ] as $contact) {
        $normalised = normaliseContact($contact);

        if ($normalised !== '') {
            $registeredMemberContacts[$normalised] = true;
        }
    }
}

$confirmedMembersWithoutResponse = [];

foreach ($members as $member) {
    if (!(int) $member['is_active']) {
        continue;
    }

    $hasRegistration = false;

    foreach ([
        $member['phone'] ?? '',
        $member['email'] ?? ''
    ] as $contact) {
        $normalised = normaliseContact($contact);

        if (
            $normalised !== ''
            && isset($registeredMemberContacts[$normalised])
        ) {
            $hasRegistration = true;
            break;
        }
    }

    if (!$hasRegistration) {
        $confirmedMembersWithoutResponse[] = $member;
    }
}

$confirmedCount =
    count($groups['confirmed'])
    + count($confirmedMembersWithoutResponse);

$likelyCount = count($groups['likely']);
$interestedCount = count($groups['interested']);
$notComingCount = count($groups['not_coming']);

$message = $_SESSION['attendees_message'] ?? '';
$error = $_SESSION['attendees_error'] ?? '';

unset(
    $_SESSION['attendees_message'],
    $_SESSION['attendees_error']
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

    <title>Thailand Trip Attendees</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link rel="stylesheet" href="assets/planner.css">

    <style>
        .attendee-summary-grid {
            display: grid;
            grid-template-columns:
                repeat(auto-fit, minmax(170px, 1fr));
            gap: 13px;
            margin-bottom: 28px;
        }

        .attendee-summary-card {
            padding: 18px;
            border: 1px solid #dce4e8;
            border-radius: 14px;
            background: white;
        }

        .attendee-summary-card span,
        .attendee-summary-card strong {
            display: block;
        }

        .attendee-summary-card span {
            color: #6b7c87;
            font-size: 0.75rem;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .attendee-summary-card strong {
            margin-top: 7px;
            color: #20313d;
            font-size: 1.8rem;
        }

        .attendee-section {
            margin-bottom: 24px;
            padding: 20px;
            border: 1px solid #dce4e8;
            border-radius: 16px;
            background: white;
        }

        .attendee-section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            margin-bottom: 15px;
        }

        .attendee-section-header h2 {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 850;
        }

        .attendee-count {
            min-width: 34px;
            height: 34px;
            display: grid;
            place-items: center;
            border-radius: 999px;
            color: white;
            background: #123a51;
            font-weight: 850;
        }

        .attendee-list {
            display: grid;
            gap: 12px;
        }

        .attendee-card {
            padding: 15px;
            border: 1px solid #e0e7eb;
            border-radius: 12px;
            background: #f9fbfc;
        }

        .attendee-card-top {
            display: flex;
            align-items: start;
            justify-content: space-between;
            gap: 16px;
        }

        .attendee-card h3 {
            margin: 0 0 4px;
            font-size: 1rem;
            font-weight: 850;
        }

        .attendee-contact {
            color: #62737e;
            font-size: 0.84rem;
        }

        .attendee-contact a {
            color: #146c9c;
            text-decoration: none;
        }

        .attendee-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 7px;
            margin-top: 10px;
        }

        .attendee-badge {
            padding: 6px 9px;
            border-radius: 999px;
            color: #536470;
            background: #edf2f5;
            font-size: 0.75rem;
            font-weight: 750;
        }

        .attendee-notes {
            margin: 11px 0 0;
            color: #536470;
            font-size: 0.86rem;
        }

        .attendee-meta {
            margin-top: 10px;
            color: #7b8991;
            font-size: 0.72rem;
        }

        .status-form {
            display: flex;
            align-items: center;
            gap: 7px;
        }

        .status-form select {
            min-width: 130px;
            padding: 7px;
            border: 1px solid #cbd6dc;
            border-radius: 7px;
            background: white;
        }

        .empty-attendee-group {
            margin: 0;
            padding: 13px;
            border-radius: 9px;
            color: #667680;
            background: #f1f4f6;
        }

        @media (max-width: 650px) {
            .attendee-card-top {
                flex-direction: column;
            }

            .status-form {
                width: 100%;
            }

            .status-form select {
                flex: 1;
            }
        }
    </style>
</head>

<body>

<?php require __DIR__ . '/includes/private_nav.php'; ?>

<header class="planner-header">
    <div class="container">
        <span>TRIP GROUP</span>
        <h1>Attendees</h1>

        <p>
            See who is confirmed, interested, likely or unable to join.
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

    <div
        class="d-flex flex-wrap justify-content-between
               align-items-center gap-3 mb-4"
    >
        <div>
            <span class="section-label">CURRENT RESPONSES</span>
            <h2 class="mb-0">Thailand 2027 group</h2>
        </div>

        <?php if ($isAdmin): ?>
            <a
                class="btn btn-primary"
                href="members.php"
            >
                Manage traveller logins
            </a>
        <?php endif; ?>
    </div>

    <section class="attendee-summary-grid">

        <article class="attendee-summary-card">
            <span>Confirmed</span>
            <strong><?= $confirmedCount ?></strong>
        </article>

        <article class="attendee-summary-card">
            <span>Likely</span>
            <strong><?= $likelyCount ?></strong>
        </article>

        <article class="attendee-summary-card">
            <span>Interested</span>
            <strong><?= $interestedCount ?></strong>
        </article>

        <article class="attendee-summary-card">
            <span>Not coming</span>
            <strong><?= $notComingCount ?></strong>
        </article>

    </section>

    <?php
    $displayGroups = [
        'confirmed' => [
            'title' => 'Confirmed attendees',
            'icon' => '✅'
        ],
        'likely' => [
            'title' => 'Likely attending',
            'icon' => '👍'
        ],
        'interested' => [
            'title' => 'Interested',
            'icon' => '🤔'
        ],
        'not_coming' => [
            'title' => 'Not coming',
            'icon' => '❌'
        ]
    ];
    ?>

    <?php foreach ($displayGroups as $status => $group): ?>

        <?php
        $statusResponses = $groups[$status];

        if ($status === 'confirmed') {
            $sectionCount =
                count($statusResponses)
                + count($confirmedMembersWithoutResponse);
        } else {
            $sectionCount = count($statusResponses);
        }
        ?>

        <section class="attendee-section">

            <div class="attendee-section-header">
                <h2>
                    <?= $group['icon'] ?>
                    <?= e($group['title']) ?>
                </h2>

                <span class="attendee-count">
                    <?= $sectionCount ?>
                </span>
            </div>

            <div class="attendee-list">

                <?php if (
                    $status === 'confirmed'
                    && $confirmedMembersWithoutResponse
                ): ?>

                    <?php foreach (
                        $confirmedMembersWithoutResponse
                        as $member
                    ): ?>

                        <article class="attendee-card">

                            <div class="attendee-card-top">

                                <div>
                                    <h3>
                                        <?= e($member['name']) ?>
                                    </h3>

                                    <div class="attendee-contact">

                                        <?php if ($member['phone']): ?>
                                            <a href="tel:<?= e(
                                                $member['phone']
                                            ) ?>">
                                                <?= e($member['phone']) ?>
                                            </a>
                                        <?php endif; ?>

                                        <?php if (
                                            $member['phone']
                                            && $member['email']
                                        ): ?>
                                            ·
                                        <?php endif; ?>

                                        <?php if ($member['email']): ?>
                                            <a href="mailto:<?= e(
                                                $member['email']
                                            ) ?>">
                                                <?= e($member['email']) ?>
                                            </a>
                                        <?php endif; ?>

                                    </div>
                                </div>

                                <span class="attendee-badge">
                                    Planner login active
                                </span>

                            </div>

                            <div class="attendee-badges">
                                <span class="attendee-badge">
                                    <?= $member['role'] === 'admin'
                                        ? 'Administrator'
                                        : 'Traveller'
                                    ?>
                                </span>

                                <span class="attendee-badge">
                                    Confirmed via private membership
                                </span>
                            </div>

                        </article>

                    <?php endforeach; ?>

                <?php endif; ?>

                <?php foreach (
                    $statusResponses as $response
                ): ?>

                    <article class="attendee-card">

                        <div class="attendee-card-top">

                            <div>
                                <h3>
                                    <?= e($response['name']) ?>
                                </h3>

                                <div class="attendee-contact">

                                    <?php if ($response['phone']): ?>
                                        <a href="tel:<?= e(
                                            $response['phone']
                                        ) ?>">
                                            <?= e($response['phone']) ?>
                                        </a>
                                    <?php endif; ?>

                                    <?php if (
                                        $response['phone']
                                        && $response['email']
                                    ): ?>
                                        ·
                                    <?php endif; ?>

                                    <?php if ($response['email']): ?>
                                        <a href="mailto:<?= e(
                                            $response['email']
                                        ) ?>">
                                            <?= e($response['email']) ?>
                                        </a>
                                    <?php endif; ?>

                                </div>
                            </div>

                            <?php if ($isAdmin): ?>

                                <form
                                    action="actions/update_interest_status.php"
                                    method="post"
                                    class="status-form"
                                >
                                    <input
                                        type="hidden"
                                        name="response_id"
                                        value="<?= (int) $response['id'] ?>"
                                    >

                                    <select name="attendance_status">
                                        <?php
                                        $statuses = [
                                            'interested' => 'Interested',
                                            'likely' => 'Likely',
                                            'confirmed' => 'Confirmed',
                                            'not_coming' => 'Not coming'
                                        ];
                                        ?>

                                        <?php foreach (
                                            $statuses
                                            as $value => $label
                                        ): ?>
                                            <option
                                                value="<?= e($value) ?>"
                                                <?= $response[
                                                    'attendance_status'
                                                ] === $value
                                                    ? 'selected'
                                                    : ''
                                                ?>
                                            >
                                                <?= e($label) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>

                                    <button
                                        class="btn btn-sm btn-primary"
                                        type="submit"
                                    >
                                        Save
                                    </button>
                                </form>

                            <?php endif; ?>

                        </div>

                        <div class="attendee-badges">

                            <span class="attendee-badge">
                                <?= attendanceIcon(
                                    $response['attendance_status']
                                ) ?>

                                <?= e(attendanceLabel(
                                    $response['attendance_status']
                                )) ?>
                            </span>

                            <span class="attendee-badge">
                                <?= $response['has_login']
                                    ? 'Planner login active'
                                    : 'No planner login'
                                ?>
                            </span>

                            <?php foreach (
                                $response['weeks'] as $week
                            ): ?>
                                <span class="attendee-badge">
                                    <?= e($week) ?>
                                </span>
                            <?php endforeach; ?>

                        </div>

                        <?php if ($response['notes']): ?>
                            <p class="attendee-notes">
                                <?= nl2br(e($response['notes'])) ?>
                            </p>
                        <?php endif; ?>

                        <div class="attendee-meta">
                            Registered
                            <?= date(
                                'j M Y, g:i a',
                                strtotime($response['created_at'])
                            ) ?>
                        </div>

                    </article>

                <?php endforeach; ?>

                <?php if ($sectionCount === 0): ?>
                    <p class="empty-attendee-group">
                        Nobody is currently listed in this group.
                    </p>
                <?php endif; ?>

            </div>

        </section>

    <?php endforeach; ?>

</main>

</body>
</html>
