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

$peopleStmt = $pdo->prepare("
    SELECT *
    FROM trip_people
    WHERE trip_id = ?
    ORDER BY
        FIELD(status, 'confirmed', 'likely', 'interested', 'invited', 'not_coming'),
        name
");

$peopleStmt->execute([$tripId]);
$people = $peopleStmt->fetchAll(PDO::FETCH_ASSOC);

$weeksStmt = $pdo->prepare("
    SELECT *
    FROM trip_weeks
    WHERE trip_id = ?
    ORDER BY week_number
");

$weeksStmt->execute([$tripId]);
$weeks = $weeksStmt->fetchAll(PDO::FETCH_ASSOC);

$bookingsStmt = $pdo->prepare("
    SELECT
        tb.*,
        tw.week_number,
        tp.name AS paid_by_name,
        GROUP_CONCAT(
            DISTINCT guest.name
            ORDER BY guest.name
            SEPARATOR ', '
        ) AS guests
    FROM trip_bookings tb
    LEFT JOIN trip_weeks tw
        ON tw.id = tb.trip_week_id
    LEFT JOIN trip_people tp
        ON tp.id = tb.paid_by_person_id
    LEFT JOIN trip_booking_people tbp
        ON tbp.booking_id = tb.id
    LEFT JOIN trip_people guest
        ON guest.id = tbp.person_id
    WHERE tb.trip_id = ?
    GROUP BY tb.id
    ORDER BY tb.start_date, tb.id
");

$bookingsStmt->execute([$tripId]);
$bookings = $bookingsStmt->fetchAll(PDO::FETCH_ASSOC);

$expensesStmt = $pdo->prepare("
    SELECT
        te.*,
        payer.name AS payer_name,
        tw.week_number
    FROM trip_expenses te
    INNER JOIN trip_people payer
        ON payer.id = te.paid_by_person_id
    LEFT JOIN trip_weeks tw
        ON tw.id = te.trip_week_id
    WHERE te.trip_id = ?
    ORDER BY te.expense_date DESC, te.id DESC
");

$expensesStmt->execute([$tripId]);
$expenses = $expensesStmt->fetchAll(PDO::FETCH_ASSOC);

$paymentsStmt = $pdo->prepare("
    SELECT
        pay.*,
        from_person.name AS from_name,
        to_person.name AS to_name
    FROM trip_payments pay
    INNER JOIN trip_people from_person
        ON from_person.id = pay.from_person_id
    INNER JOIN trip_people to_person
        ON to_person.id = pay.to_person_id
    WHERE pay.trip_id = ?
    ORDER BY pay.payment_date DESC, pay.id DESC
");

$paymentsStmt->execute([$tripId]);
$payments = $paymentsStmt->fetchAll(PDO::FETCH_ASSOC);

/*
 * Positive net means the person should receive money.
 * Negative net means the person owes money.
 */
$balances = [];

foreach ($people as $person) {
    $balances[(int) $person['id']] = [
        'name' => $person['name'],
        'paid' => 0.0,
        'share' => 0.0,
        'net' => 0.0
    ];
}

$paidStmt = $pdo->prepare("
    SELECT
        paid_by_person_id,
        SUM(amount) AS total_paid
    FROM trip_expenses
    WHERE trip_id = ?
      AND currency = 'THB'
    GROUP BY paid_by_person_id
");

$paidStmt->execute([$tripId]);

foreach ($paidStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $personId = (int) $row['paid_by_person_id'];

    if (isset($balances[$personId])) {
        $balances[$personId]['paid'] =
            (float) $row['total_paid'];
    }
}

$shareStmt = $pdo->prepare("
    SELECT
        tep.person_id,
        SUM(tep.share_amount) AS total_share
    FROM trip_expense_people tep
    INNER JOIN trip_expenses te
        ON te.id = tep.expense_id
    WHERE te.trip_id = ?
      AND te.currency = 'THB'
    GROUP BY tep.person_id
");

$shareStmt->execute([$tripId]);

foreach ($shareStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $personId = (int) $row['person_id'];

    if (isset($balances[$personId])) {
        $balances[$personId]['share'] =
            (float) $row['total_share'];
    }
}

foreach ($balances as $personId => &$balance) {
    $balance['net'] =
        $balance['paid'] - $balance['share'];
}
unset($balance);

/*
 * Apply recorded repayments:
 * A payment reduces what the sender owes and reduces what the receiver
 * is due to receive.
 */
foreach ($payments as $payment) {
    $fromId = (int) $payment['from_person_id'];
    $toId = (int) $payment['to_person_id'];
    $amount = (float) $payment['amount'];

    if (
        $payment['currency'] === 'THB'
        && isset($balances[$fromId], $balances[$toId])
    ) {
        $balances[$fromId]['net'] += $amount;
        $balances[$toId]['net'] -= $amount;
    }
}

/*
 * Produce simplified settlements.
 */
$creditors = [];
$debtors = [];

foreach ($balances as $personId => $balance) {
    if ($balance['net'] > 0.01) {
        $creditors[] = [
            'id' => $personId,
            'name' => $balance['name'],
            'amount' => $balance['net']
        ];
    } elseif ($balance['net'] < -0.01) {
        $debtors[] = [
            'id' => $personId,
            'name' => $balance['name'],
            'amount' => abs($balance['net'])
        ];
    }
}

$settlements = [];
$creditorIndex = 0;
$debtorIndex = 0;

while (
    isset($creditors[$creditorIndex])
    && isset($debtors[$debtorIndex])
) {
    $amount = min(
        $creditors[$creditorIndex]['amount'],
        $debtors[$debtorIndex]['amount']
    );

    if ($amount > 0.01) {
        $settlements[] = [
            'from' => $debtors[$debtorIndex]['name'],
            'to' => $creditors[$creditorIndex]['name'],
            'amount' => $amount
        ];
    }

    $creditors[$creditorIndex]['amount'] -= $amount;
    $debtors[$debtorIndex]['amount'] -= $amount;

    if ($creditors[$creditorIndex]['amount'] < 0.01) {
        $creditorIndex++;
    }

    if ($debtors[$debtorIndex]['amount'] < 0.01) {
        $debtorIndex++;
    }
}


$attendanceOverviewStmt = $pdo->prepare("
    SELECT
        tp.id AS person_id,
        tp.name,
        tp.status AS overall_status,
        tw.id AS week_id,
        tw.week_number,
        tw.title AS week_title,
        COALESCE(
            tpw.attendance_status,
            'not_selected'
        ) AS attendance_status
    FROM trip_people tp
    CROSS JOIN trip_weeks tw
    LEFT JOIN trip_person_weeks tpw
        ON tpw.person_id = tp.id
       AND tpw.trip_week_id = tw.id
    WHERE tp.trip_id = ?
      AND tw.trip_id = ?
    ORDER BY
        FIELD(
            tp.status,
            'confirmed',
            'likely',
            'interested',
            'invited',
            'not_coming'
        ),
        tp.name,
        tw.week_number
");

$attendanceOverviewStmt->execute([
    $tripId,
    $tripId
]);

$attendanceOverviewRows =
    $attendanceOverviewStmt->fetchAll(PDO::FETCH_ASSOC);

$attendanceOverview = [];

foreach ($attendanceOverviewRows as $row) {
    $personId = (int) $row['person_id'];

    if (!isset($attendanceOverview[$personId])) {
        $attendanceOverview[$personId] = [
            'name' => $row['name'],
            'overall_status' => $row['overall_status'],
            'weeks' => []
        ];
    }

    $attendanceOverview[$personId]['weeks'][] = [
        'week_number' => (int) $row['week_number'],
        'week_title' => $row['week_title'],
        'status' => $row['attendance_status']
    ];
}


$readinessStmt = $pdo->prepare("
    SELECT *
    FROM trip_readiness_items
    WHERE trip_id = ?
    ORDER BY display_order, title
");

$readinessStmt->execute([$tripId]);

$readinessItems =
    $readinessStmt->fetchAll(PDO::FETCH_ASSOC);

$readinessScore = 0;
$readinessMaximum = 0;

$readinessWeights = [
    'not_started' => 0,
    'researching' => 35,
    'shortlisted' => 65,
    'booked' => 100,
    'not_required' => 100
];

foreach ($readinessItems as $item) {
    $readinessScore +=
        $readinessWeights[$item['status']] ?? 0;

    $readinessMaximum += 100;
}

$readinessPercentage =
    $readinessMaximum > 0
        ? (int) round(
            ($readinessScore / $readinessMaximum) * 100
        )
        : 0;

$statusMessage = $_SESSION['planner_message'] ?? '';
unset($_SESSION['planner_message']);
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

    <title>Thailand Trip Planner</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link rel="stylesheet" href="assets/planner.css">
</head>

<body>

<?php require __DIR__ . '/includes/private_nav.php'; ?>



<header class="planner-header">
    <div class="container">
        <span>PRIVATE GROUP AREA</span>
        <h1>Bookings, costs and attendance</h1>
        <p>
            Logged in as <?= e($_SESSION['trip_member_name']) ?>.
            Every group member can add planning information.
        </p>
    </div>
</header>

<main class="container py-4">

    <section
        class="planner-section readiness-section"
        id="readiness"
    >

        <div class="readiness-header">

            <div>
                <span class="section-label">TRIP READINESS</span>
                <h2>What still needs organising?</h2>
            </div>

            <div class="readiness-score">
                <strong>
                    <?= $readinessPercentage ?>%
                </strong>

                <span>ready</span>
            </div>

        </div>

        <div class="readiness-progress">
            <span
                style="width: <?= $readinessPercentage ?>%;"
            ></span>
        </div>

        <div class="readiness-grid">

            <?php foreach ($readinessItems as $item): ?>

                <?php
                $statusIcon = match ($item['status']) {
                    'booked' => '🟢',
                    'not_required' => '⚪',
                    'shortlisted' => '🟡',
                    'researching' => '🟠',
                    default => '🔴'
                };

                $statusLabel = match ($item['status']) {
                    'booked' => 'Booked',
                    'not_required' => 'Not required',
                    'shortlisted' => 'Shortlisted',
                    'researching' => 'Researching',
                    default => 'Not started'
                };
                ?>

                <article class="readiness-card">

                    <div>
                        <span class="readiness-category">
                            <?= e($item['category']) ?>
                        </span>

                        <h3>
                            <?= $statusIcon ?>
                            <?= e($item['title']) ?>
                        </h3>

                        <small>
                            <?= e($statusLabel) ?>
                        </small>
                    </div>

                    <?php if (
                        ($_SESSION['trip_role'] ?? '')
                        === 'admin'
                    ): ?>

                        <details>
                            <summary>Edit status</summary>

                            <form
                                action="actions/update_readiness.php"
                                method="post"
                            >
                                <input
                                    type="hidden"
                                    name="item_id"
                                    value="<?= (int) $item['id'] ?>"
                                >

                                <select name="status">
                                    <?php
                                    $statuses = [
                                        'not_started' => 'Not started',
                                        'researching' => 'Researching',
                                        'shortlisted' => 'Shortlisted',
                                        'booked' => 'Booked',
                                        'not_required' => 'Not required'
                                    ];
                                    ?>

                                    <?php foreach (
                                        $statuses as $value => $label
                                    ): ?>
                                        <option
                                            value="<?= e($value) ?>"
                                            <?= $item['status'] === $value
                                                ? 'selected'
                                                : ''
                                            ?>
                                        >
                                            <?= e($label) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>

                                <input
                                    type="text"
                                    name="notes"
                                    value="<?= e($item['notes'] ?? '') ?>"
                                    placeholder="Optional note"
                                >

                                <button
                                    class="btn btn-sm btn-primary"
                                    type="submit"
                                >
                                    Save
                                </button>
                            </form>

                        </details>

                    <?php endif; ?>

                </article>

            <?php endforeach; ?>

        </div>

    </section>



    <div class="d-flex flex-wrap gap-2 mb-4">

        <a
            href="itinerary_editor.php"
            class="btn btn-primary"
        >
            Edit itinerary
        </a>

        <a
            href="attendance.php"
            class="btn btn-warning"
        >
            My attendance
        </a>

        <a
            href="documents.php"
            class="btn btn-outline-primary"
        >
            Group documents
        </a>

        <?php if (($_SESSION['trip_role'] ?? '') === 'admin'): ?>
            <a
                href="members.php"
                class="btn btn-outline-primary"
            >
                Manage travellers
            </a>
        <?php endif; ?>

    </div>

    <?php if ($statusMessage): ?>
        <div class="alert alert-success">
            <?= e($statusMessage) ?>
        </div>
    <?php endif; ?>

    
    <section class="planner-section">

        <div class="section-heading">
            <div>
                <span class="section-label">WHO IS JOINING?</span>
                <h2>Traveller attendance</h2>
            </div>

            <a
                class="btn btn-outline-primary"
                href="attendance.php"
            >
                Update my attendance
            </a>
        </div>

        <div class="traveller-attendance-grid">

            <?php foreach (
                $attendanceOverview as $traveller
            ): ?>

                <article class="traveller-attendance-card">

                    <div class="traveller-attendance-heading">

                        <div class="traveller-avatar">
                            <?= strtoupper(
                                substr(
                                    $traveller['name'],
                                    0,
                                    1
                                )
                            ) ?>
                        </div>

                        <div>
                            <strong>
                                <?= e($traveller['name']) ?>
                            </strong>

                            <span>
                                <?= e(str_replace(
                                    '_',
                                    ' ',
                                    $traveller['overall_status']
                                )) ?>
                            </span>
                        </div>

                    </div>

                    <div class="traveller-week-list">

                        <?php foreach (
                            $traveller['weeks'] as $week
                        ): ?>

                            <?php
                            $weekStatus = $week['status'];

                            $weekIcon = match ($weekStatus) {
                                'confirmed' => '✅',
                                'likely' => '👍',
                                'interested' => '❓',
                                'not_coming' => '❌',
                                default => '➖'
                            };
                            ?>

                            <div>
                                <span>
                                    <?= $weekIcon ?>
                                    Week <?= $week['week_number'] ?>
                                </span>

                                <small>
                                    <?= e(str_replace(
                                        '_',
                                        ' ',
                                        $weekStatus
                                    )) ?>
                                </small>
                            </div>

                        <?php endforeach; ?>

                    </div>

                </article>

            <?php endforeach; ?>

        </div>

    </section>

<section class="planner-section">
        <div class="section-heading">
            <div>
                <span class="section-label">LIVE TALLY</span>
                <h2>Who owes whom?</h2>
            </div>
        </div>

        <?php if ($settlements): ?>
            <div class="settlement-grid">
                <?php foreach ($settlements as $settlement): ?>
                    <article class="settlement-card">
                        <strong><?= e($settlement['from']) ?></strong>
                        <span>pays</span>
                        <strong><?= e($settlement['to']) ?></strong>
                        <b>
                            ฿<?= number_format(
                                $settlement['amount'],
                                2
                            ) ?>
                        </b>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="balanced-card">
                Everyone is currently settled.
            </div>
        <?php endif; ?>

        <div class="balance-table-wrap">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Traveller</th>
                        <th>Paid</th>
                        <th>Their share</th>
                        <th>Net position</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($balances as $balance): ?>
                        <tr>
                            <td><?= e($balance['name']) ?></td>

                            <td>
                                ฿<?= number_format(
                                    $balance['paid'],
                                    2
                                ) ?>
                            </td>

                            <td>
                                ฿<?= number_format(
                                    $balance['share'],
                                    2
                                ) ?>
                            </td>

                            <td>
                                <?php if ($balance['net'] > 0.01): ?>
                                    <span class="positive">
                                        Receives
                                        ฿<?= number_format(
                                            $balance['net'],
                                            2
                                        ) ?>
                                    </span>
                                <?php elseif ($balance['net'] < -0.01): ?>
                                    <span class="negative">
                                        Owes
                                        ฿<?= number_format(
                                            abs($balance['net']),
                                            2
                                        ) ?>
                                    </span>
                                <?php else: ?>
                                    Settled
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="planner-section">
        <div class="section-heading">
            <div>
                <span class="section-label">CONFIRMED AND PROPOSED</span>
                <h2>Bookings</h2>
            </div>
        </div>

        <div class="booking-grid">
            <?php foreach ($bookings as $booking): ?>
                <article class="booking-card">
                    <div class="booking-card-top">
                        <span>
                            <?= e(ucfirst($booking['booking_type'])) ?>
                        </span>

                        <strong>
                            <?= e(str_replace(
                                '_',
                                ' ',
                                ucfirst($booking['booking_status'])
                            )) ?>
                        </strong>
                    </div>

                    <h3><?= e($booking['title']) ?></h3>

                    <p>
                        📍 <?= e($booking['location']) ?>
                    </p>

                    <p>
                        📅
                        <?= e($booking['start_date']) ?>
                        –
                        <?= e($booking['end_date']) ?>
                    </p>

                    <?php if ($booking['guests']): ?>
                        <p>
                            👥 <?= e($booking['guests']) ?>
                        </p>
                    <?php endif; ?>

                    <div class="booking-costs">
                        <div>
                            <span>Total</span>
                            <strong>
                                <?= e($booking['currency']) ?>
                                <?= number_format(
                                    (float) $booking['total_amount'],
                                    2
                                ) ?>
                            </strong>
                        </div>

                        <div>
                            <span>Deposit paid</span>
                            <strong>
                                <?= e($booking['currency']) ?>
                                <?= number_format(
                                    (float) $booking['deposit_amount'],
                                    2
                                ) ?>
                            </strong>
                        </div>

                        <div>
                            <span>Balance due</span>
                            <strong>
                                <?= e($booking['currency']) ?>
                                <?= number_format(
                                    (float) $booking['balance_due'],
                                    2
                                ) ?>
                            </strong>
                        </div>
                    </div>

                    <?php if ($booking['notes']): ?>
                        <details>
                            <summary>Booking notes</summary>
                            <p><?= nl2br(e($booking['notes'])) ?></p>
                        </details>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="planner-columns">

        <article class="planner-form-card">
            <span class="section-label">ADD A SHARED COST</span>
            <h2>Add expense</h2>

            <form action="actions/add_expense.php" method="post">

                <label>
                    Description
                    <input
                        type="text"
                        name="description"
                        required
                    >
                </label>

                <div class="form-row">
                    <label>
                        Amount
                        <input
                            type="number"
                            name="amount"
                            min="0.01"
                            step="0.01"
                            required
                        >
                    </label>

                    <label>
                        Currency
                        <select name="currency">
                            <option value="THB">THB</option>
                            <option value="AUD">AUD</option>
                        </select>
                    </label>
                </div>

                <div class="form-row">
                    <label>
                        Paid by
                        <select name="paid_by_person_id" required>
                            <?php foreach ($people as $person): ?>
                                <option value="<?= (int) $person['id'] ?>">
                                    <?= e($person['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label>
                        Week
                        <select name="trip_week_id">
                            <option value="">General trip cost</option>

                            <?php foreach ($weeks as $week): ?>
                                <option value="<?= (int) $week['id'] ?>">
                                    Week <?= (int) $week['week_number'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>

                <fieldset>
                    <legend>Split equally between</legend>

                    <?php foreach ($people as $person): ?>
                        <label class="checkbox-label">
                            <input
                                type="checkbox"
                                name="people[]"
                                value="<?= (int) $person['id'] ?>"
                            >
                            <?= e($person['name']) ?>
                        </label>
                    <?php endforeach; ?>
                </fieldset>

                <label>
                    Notes
                    <textarea name="notes" rows="3"></textarea>
                </label>

                <button class="btn btn-primary" type="submit">
                    Add expense
                </button>
            </form>
        </article>

        <article class="planner-form-card">
            <span class="section-label">RECORD REPAYMENT</span>
            <h2>Add payment</h2>

            <form action="actions/add_payment.php" method="post">

                <label>
                    Paid by
                    <select name="from_person_id" required>
                        <?php foreach ($people as $person): ?>
                            <option value="<?= (int) $person['id'] ?>">
                                <?= e($person['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>
                    Paid to
                    <select name="to_person_id" required>
                        <?php foreach ($people as $person): ?>
                            <option value="<?= (int) $person['id'] ?>">
                                <?= e($person['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <div class="form-row">
                    <label>
                        Amount
                        <input
                            type="number"
                            name="amount"
                            min="0.01"
                            step="0.01"
                            required
                        >
                    </label>

                    <label>
                        Currency
                        <select name="currency">
                            <option value="THB">THB</option>
                            <option value="AUD">AUD</option>
                        </select>
                    </label>
                </div>

                <label>
                    Notes
                    <textarea name="notes" rows="3"></textarea>
                </label>

                <button class="btn btn-primary" type="submit">
                    Record payment
                </button>
            </form>
        </article>

    </section>

    <section class="planner-section">
        <span class="section-label">RECENT COSTS</span>
        <h2>Expense history</h2>

        <div class="history-list">
            <?php foreach ($expenses as $expense): ?>
                <article>
                    <div>
                        <strong><?= e($expense['description']) ?></strong>

                        <span>
                            Paid by <?= e($expense['payer_name']) ?>
                            · <?= e($expense['expense_date']) ?>
                        </span>
                    </div>

                    <div class="history-amount-actions">

                        <b>
                            <?= e($expense['currency']) ?>
                            <?= number_format(
                                (float) $expense['amount'],
                                2
                            ) ?>
                        </b>

                        <?php if (
                            ($_SESSION['trip_role'] ?? '')
                            === 'admin'
                        ): ?>

                            <form
                                action="actions/delete_expense.php"
                                method="post"
                                onsubmit="return confirm(
                                    'Delete this expense? This will also '
                                    + 'remove its split shares.'
                                );"
                            >
                                <input
                                    type="hidden"
                                    name="expense_id"
                                    value="<?= (int) $expense['id'] ?>"
                                >

                                <button
                                    class="btn btn-sm btn-outline-danger"
                                    type="submit"
                                >
                                    Delete
                                </button>
                            </form>

                        <?php endif; ?>

                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>


    <section class="planner-section">

        <span class="section-label">RECORDED REPAYMENTS</span>
        <h2>Payment history</h2>

        <?php if (!$payments): ?>
            <div class="alert alert-info mb-0">
                No repayments have been recorded yet.
            </div>
        <?php else: ?>

            <div class="history-list">

                <?php foreach ($payments as $payment): ?>

                    <article>

                        <div>
                            <strong>
                                <?= e($payment['from_name']) ?>
                                paid
                                <?= e($payment['to_name']) ?>
                            </strong>

                            <span>
                                <?= e($payment['payment_date']) ?>

                                <?php if ($payment['notes']): ?>
                                    · <?= e($payment['notes']) ?>
                                <?php endif; ?>
                            </span>
                        </div>

                        <div class="history-amount-actions">

                            <b>
                                <?= e($payment['currency']) ?>
                                <?= number_format(
                                    (float) $payment['amount'],
                                    2
                                ) ?>
                            </b>

                            <?php if (
                                ($_SESSION['trip_role'] ?? '')
                                === 'admin'
                            ): ?>

                                <form
                                    action="actions/delete_payment.php"
                                    method="post"
                                    onsubmit="return confirm(
                                        'Delete this recorded payment?'
                                    );"
                                >
                                    <input
                                        type="hidden"
                                        name="payment_id"
                                        value="<?= (int) $payment['id'] ?>"
                                    >

                                    <button
                                        class="btn btn-sm btn-outline-danger"
                                        type="submit"
                                    >
                                        Delete
                                    </button>
                                </form>

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
