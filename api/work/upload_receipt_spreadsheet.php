<?php
declare(strict_types=1);

require_once __DIR__ . '/_admin_auth.php';
require_once __DIR__ . '/../../includes/work_receipt_imports.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    wr_fail('POST required.', 405);
}

$jobId = (int)($_POST['job_id'] ?? 0);
$taskId = (int)($_POST['task_id'] ?? 0);
$sessionId = (int)($_POST['session_id'] ?? 0);

wt_job($pdo, $jobId);

if ($taskId > 0) {
    $check = $pdo->prepare(
        'SELECT id FROM work_tasks WHERE id=? AND job_id=?'
    );
    $check->execute([$taskId, $jobId]);

    if (!$check->fetchColumn()) {
        wr_fail('Choose a valid task.');
    }
} else {
    $taskId = null;
}

if ($sessionId > 0) {
    $check = $pdo->prepare(
        'SELECT id FROM work_sessions WHERE id=? AND job_id=?'
    );
    $check->execute([$sessionId, $jobId]);

    if (!$check->fetchColumn()) {
        wr_fail('Choose a valid activity.');
    }
} else {
    $sessionId = null;
}

try {
    $result = wri_store_upload(
        $pdo,
        $jobId,
        $taskId,
        $sessionId,
        (array)($_FILES['spreadsheet'] ?? [])
    );

    $ready = false;

    if (!$result['duplicate']) {
        $query = $pdo->prepare(
            'SELECT * FROM work_receipt_imports WHERE id=? AND job_id=?'
        );
        $query->execute([(int)$result['id'], $jobId]);
        $import = $query->fetch(PDO::FETCH_ASSOC);

        if (!$import) {
            throw new RuntimeException('The saved import record could not be read.');
        }

        try {
            wri_process_import($pdo, $import);
            $ready = true;
        } catch (Throwable $processingError) {
            $failure = $pdo->prepare(
                "UPDATE work_receipt_imports
                 SET status='failed',error_message=?,completed_at=NOW(),
                     processing_stage='failed',processing_detail=?,heartbeat_at=NOW()
                 WHERE id=?"
            );
            $message = mb_substr($processingError->getMessage(), 0, 4000);
            $failure->execute([$message, mb_substr($message, 0, 255), (int)$result['id']]);
            throw $processingError;
        }
    }

    header(
        'Location: ../../admin/work/materials.php?id='
        . $jobId
        . '&spreadsheet_import='
        . (int)$result['id']
        . '&spreadsheet_duplicate='
        . ($result['duplicate'] ? '1' : '0')
        . '&spreadsheet_ready='
        . ($ready ? '1' : '0')
        . '#spreadsheet-imports'
    );
    exit;
} catch (Throwable $error) {
    wr_fail(
        'Spreadsheet could not be queued: ' . $error->getMessage(),
        500
    );
}
