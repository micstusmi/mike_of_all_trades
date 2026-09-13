<?php
declare(strict_types=1);

require_once __DIR__ . '/_admin_auth.php';
require_once __DIR__ . '/../../includes/work_tracker.php';

function fail_closeout(string $message, int $status = 400): never
{
    http_response_code($status);
    die($message);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail_closeout('POST required.', 405);
}

$jobId = (int)($_POST['job_id'] ?? 0);
$action = trim((string)($_POST['action'] ?? ''));
$correctionNotes = trim(
    (string)($_POST['correction_notes'] ?? '')
);

if ($jobId <= 0) {
    fail_closeout('Invalid job.');
}

if (!in_array(
    $action,
    ['save_draft', 'mark_reviewed', 'approve'],
    true
)) {
    fail_closeout('Invalid close-out action.');
}

$job = wt_job($pdo, $jobId);
$totals = wt_totals($pdo, $jobId);

/*
 * Never approve a close-out while work is still actively timing.
 */
$running = $pdo->prepare("
    SELECT COUNT(*)
    FROM work_sessions
    WHERE job_id=?
      AND ended_at IS NULL
");
$running->execute([$jobId]);
$runningCount = (int)$running->fetchColumn();

if ($action === 'approve' && $runningCount > 0) {
    fail_closeout(
        'Cannot approve close-out while a work session is still running.'
    );
}

$tasks = wt_job_tasks($pdo, $jobId);

$q = $pdo->prepare("
    SELECT
        m.*,
        t.title AS task_title
    FROM work_materials m
    LEFT JOIN work_tasks t
        ON t.id=m.task_id
       AND t.job_id=m.job_id
    WHERE m.job_id=?
    ORDER BY
        COALESCE(m.purchase_date, DATE(m.purchased_at)),
        m.id
");
$q->execute([$jobId]);
$materials = $q->fetchAll(PDO::FETCH_ASSOC);

$q = $pdo->prepare("
    SELECT *
    FROM work_payments
    WHERE job_id=?
    ORDER BY paid_at,id
");
$q->execute([$jobId]);
$payments = $q->fetchAll(PDO::FETCH_ASSOC);

$q = $pdo->prepare("
    SELECT
        s.*,
        COALESCE(w.worker_name,'Mike') AS worker_name,
        t.title AS task_title
    FROM work_sessions s
    LEFT JOIN work_workers w
        ON w.id=s.worker_id
    LEFT JOIN work_tasks t
        ON t.id=s.task_id
       AND t.job_id=s.job_id
    WHERE s.job_id=?
    ORDER BY s.started_at,s.id
");
$q->execute([$jobId]);
$sessions = $q->fetchAll(PDO::FETCH_ASSOC);

$q = $pdo->prepare("
    SELECT *
    FROM work_task_photos
    WHERE job_id=?
    ORDER BY created_at,id
");
$q->execute([$jobId]);
$photos = $q->fetchAll(PDO::FETCH_ASSOC);

/*
 * The snapshot contains the source records used for close-out review.
 *
 * receipt_gst_amount is retained only as information appearing on
 * third-party source receipts. It is NOT GST charged by
 * Mike Of All Trades.
 */
$snapshot = [
    'schema_version' => 1,
    'captured_at' => date('c'),

    'job' => [
        'id' => (int)$job['id'],
        'customer_name' =>
            (string)($job['customer_name'] ?? ''),
        'job_title' =>
            (string)($job['title'] ?? ''),
        'agreed_hourly_rate' =>
            (float)($job['agreed_hourly_rate'] ?? 0),

        'opening_balances' => [
            'work_already_value' =>
                (float)($job['work_already_value'] ?? 0),
            'materials_already_value' =>
                (float)($job['materials_already_value'] ?? 0),
            'payments_received' =>
                (float)($job['payments_received'] ?? 0),
        ],
    ],

    'totals' => [
        'labour' => (float)$totals['labour'],
        'materials' => (float)$totals['materials'],
        'other' => 0.0,
        'gross_job_amount' => (float)$totals['total'],
        'payments' => (float)$totals['payments'],
        'outstanding' => (float)$totals['outstanding'],
    ],

    'tasks' => $tasks,
    'sessions' => $sessions,
    'materials' => $materials,
    'payments' => $payments,

    'photos' => array_map(
        static fn(array $photo): array => [
            'id' => (int)$photo['id'],
            'task_id' =>
                isset($photo['task_id'])
                    ? (int)$photo['task_id']
                    : null,
            'photo_type' =>
                (string)($photo['photo_type'] ?? ''),
            'note' =>
                (string)($photo['note'] ?? ''),
            'created_at' =>
                (string)($photo['created_at'] ?? ''),
        ],
        $photos
    ),

    'tax_note' =>
        'GST shown on supplier/source receipts is source cost ' .
        'information only and is not GST charged by Mike Of All Trades.',
];

$status = match ($action) {
    'mark_reviewed' => 'reviewed',
    'approve' => 'approved',
    default => 'draft',
};

$adminUserId = (int)($_SESSION['user_id'] ?? 0);

try {
    $pdo->beginTransaction();

    /*
     * A newly approved snapshot becomes the authoritative approved
     * close-out. Older approved snapshots remain historical records
     * but are marked superseded.
     */
    if ($status === 'approved') {
        $q = $pdo->prepare("
            UPDATE work_closeout_snapshots
            SET status='superseded'
            WHERE job_id=?
              AND status='approved'
        ");
        $q->execute([$jobId]);
    }

    $q = $pdo->prepare("
        INSERT INTO work_closeout_snapshots
        (
            job_id,
            status,
            labour_amount,
            materials_amount,
            other_amount,
            gross_job_amount,
            payments_amount,
            outstanding_amount,
            snapshot_json,
            correction_notes,
            created_by,
            reviewed_at,
            approved_at
        )
        VALUES
        (
            ?,?,?,?,?,?,?,?,?,?,?,?,?
        )
    ");

    $reviewedAt =
        in_array($status, ['reviewed','approved'], true)
            ? date('Y-m-d H:i:s')
            : null;

    $approvedAt =
        $status === 'approved'
            ? date('Y-m-d H:i:s')
            : null;

    $q->execute([
        $jobId,
        $status,
        (float)$totals['labour'],
        (float)$totals['materials'],
        0.0,
        (float)$totals['total'],
        (float)$totals['payments'],
        (float)$totals['outstanding'],
        json_encode(
            $snapshot,
            JSON_UNESCAPED_SLASHES |
            JSON_UNESCAPED_UNICODE
        ),
        $correctionNotes !== ''
            ? $correctionNotes
            : null,
        $adminUserId ?: null,
        $reviewedAt,
        $approvedAt,
    ]);

    $snapshotId = (int)$pdo->lastInsertId();

    $pdo->commit();

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Work Tracker close-out snapshot failed: ' .
        $e->getMessage()
    );

    fail_closeout(
        'Could not save the close-out snapshot.',
        500
    );
}

header(
    'Location: ../../admin/work/closeout.php?id=' .
    $jobId .
    '&snapshot_saved=' .
    $snapshotId
);

exit;
