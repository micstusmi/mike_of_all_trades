<?php
declare(strict_types=1);

require_once __DIR__ . '/_admin_auth.php';
require_once __DIR__ . '/../../includes/work_receipt_imports.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    wr_fail('POST required.', 405);
}

$jobId = (int)($_POST['job_id'] ?? 0);
$importId = (int)($_POST['import_id'] ?? 0);
wt_job($pdo, $jobId);

try {
    $query = $pdo->prepare(
        'SELECT * FROM work_receipt_imports WHERE id=? AND job_id=?'
    );
    $query->execute([$importId, $jobId]);
    $import = $query->fetch(PDO::FETCH_ASSOC);

    if (!$import) {
        throw new RuntimeException('The spreadsheet import was not found.');
    }

    if (in_array((string)$import['status'], ['ready', 'applied'], true)) {
        throw new RuntimeException('This spreadsheet has already been processed.');
    }

    $reset = $pdo->prepare(
        "UPDATE work_receipt_imports
         SET status='processing',error_message=NULL,completed_at=NULL,
             progress_percent=1,processing_stage='starting',
             processing_detail='Retry started.',started_at=NOW(),heartbeat_at=NOW()
         WHERE id=? AND job_id=?"
    );
    $reset->execute([$importId, $jobId]);
    $import['status'] = 'processing';

    try {
        wri_process_import($pdo, $import);
    } catch (Throwable $processingError) {
        $failure = $pdo->prepare(
            "UPDATE work_receipt_imports
             SET status='failed',error_message=?,completed_at=NOW(),
                 processing_stage='failed',processing_detail=?,heartbeat_at=NOW()
             WHERE id=? AND job_id=?"
        );
        $message = mb_substr($processingError->getMessage(), 0, 4000);
        $failure->execute([
            $message,
            mb_substr($message, 0, 255),
            $importId,
            $jobId,
        ]);
        throw $processingError;
    }

    header(
        'Location: ../../admin/work/receipt_import_review.php?id=' . $jobId
        . '&retried_import=' . $importId
    );
    exit;
} catch (Throwable $error) {
    wr_fail('Spreadsheet retry failed: ' . $error->getMessage(), 500);
}
