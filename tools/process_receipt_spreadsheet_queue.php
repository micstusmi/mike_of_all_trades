<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

require_once __DIR__ . '/../includes/work_receipt_imports.php';

$jobId = (int)($argv[1] ?? 0);
$lock = fopen(
    sys_get_temp_dir() . '/mot_receipt_spreadsheet_queue.lock',
    'c'
);

if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    exit(0);
}

while (true) {
    $pdo->beginTransaction();

    try {
        $sql = "SELECT *
                FROM work_receipt_imports
                WHERE status='processing'"
            . ($jobId > 0 ? ' AND job_id=' . $jobId : '')
            . ' ORDER BY id
                LIMIT 1
                FOR UPDATE';

        $import = $pdo->query($sql)->fetch(PDO::FETCH_ASSOC);
        $pdo->commit();

        if (!$import) {
            break;
        }

        try {
            wri_process_import($pdo, $import);
        } catch (Throwable $error) {
            $update = $pdo->prepare(
                "UPDATE work_receipt_imports
                 SET status='failed',
                     error_message=?,
                     processing_stage='failed',
                     processing_detail=?,
                     heartbeat_at=NOW(),
                     completed_at=NOW()
                 WHERE id=?
                   AND status='processing'"
            );

            $message = mb_substr($error->getMessage(), 0, 4000);
            $update->execute([
                $message,
                mb_substr($message, 0, 255),
                (int)$import['id'],
            ]);
        }
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        break;
    }
}

flock($lock, LOCK_UN);
fclose($lock);
