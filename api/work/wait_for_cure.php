<?php
declare(strict_types=1);

/*
 * Ends the current work activity because an applied product must
 * dry/cure/set.
 *
 * Important:
 * - this is NOT a personal pause;
 * - this is NOT job completion;
 * - the timer stops;
 * - the job remains open for a later attendance;
 * - elapsed drying/curing time is not charged as labour.
 */

require_once __DIR__ . '/_admin_auth.php';
require_once __DIR__ . '/../../includes/work_tracker.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('POST required.');
}

$jobId = (int)($_POST['job_id'] ?? 0);

if ($jobId <= 0) {
    http_response_code(400);
    exit('Invalid job.');
}

$job = wt_job($pdo, $jobId);

$material = trim((string)($_POST['cure_material'] ?? ''));
$note = trim((string)($_POST['cure_note'] ?? ''));
$expectedReturn = trim(
    (string)($_POST['cure_expected_return'] ?? '')
);

if ($material === '') {
    http_response_code(400);
    exit('Please select what was applied.');
}

if ($note === '') {
    http_response_code(400);
    exit('Please describe what is drying / curing and what happens next.');
}

/*
 * Prefer Mike's currently open session (worker_id NULL in this Work
 * Tracker). If another structure is encountered, fall back to the most
 * recent open session on the job.
 */
$q = $pdo->prepare("
    SELECT *
    FROM work_sessions
    WHERE job_id = ?
      AND ended_at IS NULL
    ORDER BY
        CASE WHEN worker_id IS NULL THEN 0 ELSE 1 END,
        id DESC
    LIMIT 1
");
$q->execute([$jobId]);

$session = $q->fetch(PDO::FETCH_ASSOC);

if (!$session) {
    header(
        'Location: ../../admin/work/manage_job.php?id=' .
        $jobId .
        '&cure_no_session=1#live-timer'
    );
    exit;
}

$sessionId = (int)$session['id'];

/*
 * If the session happened to have an open personal-break record,
 * close it cleanly before closing the activity itself.
 */
try {
    $closeBreak = $pdo->prepare("
        UPDATE work_session_breaks
        SET ended_at = NOW()
        WHERE session_id = ?
          AND ended_at IS NULL
    ");
    $closeBreak->execute([$sessionId]);
} catch (Throwable $e) {
    /*
     * Older installations without this table should still be able to
     * stop the session.
     */
}

$stopNote =
    $material .
    ': ' .
    $note;

$stmt = $pdo->prepare("
    UPDATE work_sessions
    SET
        ended_at = NOW(),
        stop_reason = 'waiting_cure',
        stop_note = ?,
        expected_return = ?
    WHERE id = ?
      AND job_id = ?
      AND ended_at IS NULL
");

$stmt->execute([
    $stopNote,
    $expectedReturn !== '' ? $expectedReturn : null,
    $sessionId,
    $jobId,
]);

/*
 * Leave the job OPEN.
 *
 * If another worker still has an active session, the job remains active.
 * Otherwise it becomes paused rather than completed.
 */
$running = $pdo->prepare("
    SELECT COUNT(*)
    FROM work_sessions
    WHERE job_id = ?
      AND ended_at IS NULL
");
$running->execute([$jobId]);

$jobStatus =
    ((int)$running->fetchColumn() > 0)
        ? 'active'
        : 'paused';

$pdo->prepare("
    UPDATE work_jobs
    SET status = ?
    WHERE id = ?
")->execute([
    $jobStatus,
    $jobId,
]);

/*
 * Customer communication:
 * drying/curing is a genuine job-status explanation, not an unrelated
 * personal interruption.
 */
$mode = $job['customer_update_mode'] ?? 'full_transparency';

$sendSms = in_array(
    $mode,
    ['full_transparency', 'important_only'],
    true
);

if (
    $sendSms &&
    !empty($job['customer_phone'])
) {
    $msg =
        "Mike of All Trades — Job update\n" .
        "Work has stopped for now while an applied material dries/cures.\n" .
        "Applied: " . $material . ".\n" .
        "Details: " . $note . ".\n" .
        "Drying/curing time is not being recorded as labour.";

    if ($expectedReturn !== '') {
        $msg .=
            "\nExpected return / next attendance: " .
            $expectedReturn .
            ".";
    }

    $msg .=
        "\nThe job remains open for the next stage." .
        "\nLive job record: " .
        wt_public_url($job);

    try {
        wt_send_sms(
            $pdo,
            $jobId,
            $job['customer_phone'],
            $msg,
            'waiting_cure'
        );
    } catch (Throwable $e) {
        /*
         * Never lose the timer/session update merely because SMS failed.
         */
        error_log(
            'Waiting/cure SMS failed for work job ' .
            $jobId .
            ': ' .
            $e->getMessage()
        );
    }
}

header(
    'Location: ../../admin/work/manage_job.php?id=' .
    $jobId .
    '&waiting_cure=1#live-timer'
);

exit;
