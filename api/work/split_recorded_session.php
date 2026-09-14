<?php
declare(strict_types=1);

require_once __DIR__ . '/_admin_auth.php';
require_once __DIR__ . '/../../includes/work_tracker.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('POST required.');
}

$jobId     = (int)($_POST['job_id'] ?? 0);
$sessionId = (int)($_POST['session_id'] ?? 0);

$billableHours = (float)($_POST['billable_hours'] ?? 0);
$freeHours     = (float)($_POST['free_hours'] ?? 0);

$freeReason = trim((string)($_POST['free_reason'] ?? 'goodwill'));
$freeNote   = trim((string)($_POST['free_note'] ?? ''));

if ($jobId <= 0 || $sessionId <= 0) {
    http_response_code(400);
    exit('Invalid job or session.');
}

if ($billableHours <= 0 || $freeHours <= 0) {
    http_response_code(400);
    exit('Both billable and no-charge hours must be greater than zero.');
}

if (!in_array($freeReason, ['goodwill', 'rectification', 'other'], true)) {
    $freeReason = 'goodwill';
}

$q = $pdo->prepare("
    SELECT *
    FROM work_sessions
    WHERE id = ?
      AND job_id = ?
    LIMIT 1
");
$q->execute([$sessionId, $jobId]);

$session = $q->fetch(PDO::FETCH_ASSOC);

if (!$session) {
    http_response_code(404);
    exit('Recorded work entry not found.');
}

if (($session['session_source'] ?? '') !== 'retrospective') {
    http_response_code(400);
    exit('Only retrospective recorded work can currently be split.');
}

if (empty($session['ended_at'])) {
    http_response_code(400);
    exit('Running work cannot be split.');
}

$originalHours = isset($session['retrospective_hours'])
    && $session['retrospective_hours'] !== null
        ? (float)$session['retrospective_hours']
        : (
            max(
                0,
                strtotime((string)$session['ended_at'])
                - strtotime((string)$session['started_at'])
            ) / 3600
        );

$requestedTotal = $billableHours + $freeHours;

if (abs($requestedTotal - $originalHours) > 0.02) {
    http_response_code(400);
    exit(
        'Split hours must add up to the original recorded hours (' .
        number_format($originalHours, 2) .
        ').'
    );
}

/*
 * Do not split the same source session twice.
 */
$splitMarker = '[split-source:' . $sessionId . ']';

$already = $pdo->prepare("
    SELECT id
    FROM work_sessions
    WHERE job_id = ?
      AND entry_note LIKE ?
    LIMIT 1
");
$already->execute([
    $jobId,
    '%' . $splitMarker . '%'
]);

if ($already->fetchColumn()) {
    http_response_code(400);
    exit('This recorded session has already been split.');
}

$originalStart = strtotime((string)$session['started_at']);

if ($originalStart === false) {
    http_response_code(500);
    exit('Original session start time is invalid.');
}

$billableEndTs = $originalStart + (int)round($billableHours * 3600);
$freeEndTs     = $billableEndTs + (int)round($freeHours * 3600);

$billableEnd = date('Y-m-d H:i:s', $billableEndTs);
$freeStart   = $billableEnd;
$freeEnd     = date('Y-m-d H:i:s', $freeEndTs);

$pdo->beginTransaction();

try {

    /*
     * 1. Existing row becomes the billable portion.
     */
    $update = $pdo->prepare("
        UPDATE work_sessions
        SET
            ended_at = ?,
            billable = 1,
            retrospective_entry_basis = ?,
            retrospective_hours = ?,
            entry_note = ?
        WHERE id = ?
          AND job_id = ?
    ");

    $originalEntryNote = trim((string)($session['entry_note'] ?? ''));

    $billableEntryNote = trim(
        $originalEntryNote .
        ' [split-billable]'
    );

    $update->execute([
        $billableEnd,
        ($session['retrospective_entry_basis'] ?? 'hours'),
        $billableHours,
        $billableEntryNote,
        $sessionId,
        $jobId
    ]);

    /*
     * 2. Clone the relevant session details into a second retrospective row
     *    representing only the no-charge portion.
     */
    $insert = $pdo->prepare("
        INSERT INTO work_sessions
        (
            job_id,
            session_source,
            retrospective_entered_at,
            retrospective_entry_basis,
            retrospective_hours,
            entered_at,
            entry_note,
            worker_id,
            task_id,
            started_at,
            ended_at,
            category,
            start_location,
            location_detail,
            travel_type,
            travel_eta,
            billable,
            notes,
            stop_reason,
            stop_note,
            expected_return
        )
        VALUES
        (
            ?,
            'retrospective',
            NOW(),
            ?,
            ?,
            NOW(),
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            0,
            ?,
            ?,
            ?,
            ?
        )
    ");

    $freeEntryNote = $splitMarker . ' No-charge portion split from recorded session.';

    $insert->execute([
        $jobId,
        ($session['retrospective_entry_basis'] ?? 'hours'),
        $freeHours,
        $freeEntryNote,
        $session['worker_id'],
        $session['task_id'],
        $freeStart,
        $freeEnd,
        $session['category'],
        $session['start_location'],
        $session['location_detail'],
        $session['travel_type'],
        $session['travel_eta'],
        $session['notes'],
        $session['stop_reason'],
        $session['stop_note'],
        $session['expected_return']
    ]);

    $freeSessionId = (int)$pdo->lastInsertId();

    /*
     * 3. Create the corresponding no-charge labour ledger entry.
     */
    $jobStmt = $pdo->prepare("
        SELECT *
        FROM work_jobs
        WHERE id = ?
        LIMIT 1
    ");
    $jobStmt->execute([$jobId]);

    $job = $jobStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $hourlyRate = (float)($job['agreed_hourly_rate'] ?? 0);
    $labourValue = round($freeHours * $hourlyRate, 2);

    $description = trim((string)($session['notes'] ?? ''));

    if ($description === '') {
        $description = 'No-charge portion of recorded work';
    }

    $ledgerNote =
        '[session:' . $freeSessionId . '] ' .
        '[split-from:' . $sessionId . '] ' .
        ($freeNote !== '' ? $freeNote : 'Split from mixed paid/no-charge work.');

    $ledger = $pdo->prepare("
        INSERT INTO work_complimentary_items
        (
            job_id,
            item_type,
            no_charge_reason,
            labour_hours,
            labour_value,
            material_value,
            description,
            estimated_value,
            note,
            updated_at
        )
        VALUES
        (
            ?,
            'labour',
            ?,
            ?,
            ?,
            0,
            ?,
            ?,
            ?,
            NOW()
        )
    ");

    $ledger->execute([
        $jobId,
        $freeReason,
        $freeHours,
        $labourValue,
        $description,
        $labourValue,
        $ledgerNote
    ]);

    $pdo->commit();

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'split_recorded_session failed: ' .
        $e->getMessage()
    );

    http_response_code(500);
    exit('Could not split recorded work.');
}

header(
    'Location: ../../admin/work/manage_job.php?id=' .
    $jobId .
    '&session_split=1'
);

exit;
