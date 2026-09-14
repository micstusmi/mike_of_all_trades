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

if ($jobId <= 0 || $sessionId <= 0) {
    http_response_code(400);
    exit('Invalid job or session.');
}

$stmt = $pdo->prepare("
    SELECT *
    FROM work_sessions
    WHERE id = ?
      AND job_id = ?
    LIMIT 1
");
$stmt->execute([$sessionId, $jobId]);

$session = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$session) {
    http_response_code(404);
    exit('Recorded work entry not found.');
}

if (empty($session['ended_at'])) {
    http_response_code(400);
    exit('A currently running session cannot be edited here.');
}

/*
 * datetime-local arrives as YYYY-MM-DDTHH:MM
 */
$startRaw = trim((string)($_POST['started_at'] ?? ''));
$endRaw   = trim((string)($_POST['ended_at'] ?? ''));

$start = DateTime::createFromFormat('Y-m-d\TH:i', $startRaw);
$end   = DateTime::createFromFormat('Y-m-d\TH:i', $endRaw);

if (!$start || !$end) {
    http_response_code(400);
    exit('Valid start and finish times are required.');
}

if ($end <= $start) {
    http_response_code(400);
    exit('Finish time must be after start time.');
}

$durationSeconds = $end->getTimestamp() - $start->getTimestamp();

if ($durationSeconds > 86400) {
    http_response_code(400);
    exit('A single recorded session cannot exceed 24 hours.');
}

$startedAt = $start->format('Y-m-d H:i:s');
$endedAt   = $end->format('Y-m-d H:i:s');
$hours     = round($durationSeconds / 3600, 2);

$notes    = trim((string)($_POST['notes'] ?? ''));
$stopNote = trim((string)($_POST['stop_note'] ?? ''));

$treatment = trim(
    (string)($_POST['charge_treatment'] ?? 'billable')
);

$allowed = [
    'billable',
    'no_charge_labour',
    'no_charge_rectification',
];

if (!in_array($treatment, $allowed, true)) {
    $treatment = 'billable';
}

$billable = $treatment === 'billable' ? 1 : 0;

/*
 * A linked no-charge item gets a stable marker so subsequent edits update
 * the same ledger item rather than creating duplicates.
 */
$marker = '[session:' . $sessionId . ']';

$pdo->beginTransaction();

try {

    /*
     * Update the actual historical session.
     *
     * For retrospective sessions, keep retrospective_hours synchronised with
     * the corrected duration. For ordinary live sessions it remains unchanged.
     */
    if (($session['session_source'] ?? '') === 'retrospective') {

        $update = $pdo->prepare("
            UPDATE work_sessions
            SET
                started_at = ?,
                ended_at = ?,
                billable = ?,
                notes = ?,
                stop_note = ?,
                retrospective_entry_basis = 'hours',
                retrospective_hours = ?
            WHERE id = ?
              AND job_id = ?
        ");

        $update->execute([
            $startedAt,
            $endedAt,
            $billable,
            $notes !== '' ? $notes : null,
            $stopNote !== '' ? $stopNote : null,
            $hours,
            $sessionId,
            $jobId
        ]);

    } else {

        $update = $pdo->prepare("
            UPDATE work_sessions
            SET
                started_at = ?,
                ended_at = ?,
                billable = ?,
                notes = ?,
                stop_note = ?
            WHERE id = ?
              AND job_id = ?
        ");

        $update->execute([
            $startedAt,
            $endedAt,
            $billable,
            $notes !== '' ? $notes : null,
            $stopNote !== '' ? $stopNote : null,
            $sessionId,
            $jobId
        ]);
    }

    /*
     * Find any no-charge ledger row that this editor previously generated.
     */
    $find = $pdo->prepare("
        SELECT id
        FROM work_complimentary_items
        WHERE job_id = ?
          AND note LIKE ?
        ORDER BY id
        LIMIT 1
    ");

    $find->execute([
        $jobId,
        '%' . $marker . '%'
    ]);

    $linkedId = (int)($find->fetchColumn() ?: 0);

    /*
     * If changed back to billable, remove ONLY the automatically linked
     * no-charge record. Manually entered complimentary records are untouched.
     */
    if ($treatment === 'billable') {

        if ($linkedId > 0) {
            $delete = $pdo->prepare("
                DELETE FROM work_complimentary_items
                WHERE id = ?
                  AND job_id = ?
            ");

            $delete->execute([
                $linkedId,
                $jobId
            ]);
        }

    } else {

        $reason = $treatment === 'no_charge_rectification'
            ? 'rectification'
            : 'goodwill';

        /*
         * Get the job's agreed hourly rate for the labour-value tally.
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
        $labourValue = round($hours * $hourlyRate, 2);

        $description = $notes !== ''
            ? $notes
            : 'Recorded work session';

        $ledgerNote = $marker . ' Automatically linked to recorded work.';

        if ($linkedId > 0) {

            $ledger = $pdo->prepare("
                UPDATE work_complimentary_items
                SET
                    item_type = 'labour',
                    no_charge_reason = ?,
                    labour_hours = ?,
                    labour_value = ?,
                    material_value = 0,
                    description = ?,
                    estimated_value = ?,
                    note = ?,
                    updated_at = NOW()
                WHERE id = ?
                  AND job_id = ?
            ");

            $ledger->execute([
                $reason,
                $hours,
                $labourValue,
                $description,
                $labourValue,
                $ledgerNote,
                $linkedId,
                $jobId
            ]);

        } else {

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
                $reason,
                $hours,
                $labourValue,
                $description,
                $labourValue,
                $ledgerNote
            ]);
        }
    }

    $pdo->commit();

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'update_recorded_session failed: ' .
        $e->getMessage()
    );

    http_response_code(500);
    exit('Could not update recorded work.');
}

header(
    'Location: ../../admin/work/manage_job.php?id=' .
    $jobId .
    '&session_updated=1'
);

exit;
