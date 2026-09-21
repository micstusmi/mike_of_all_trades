<?php
declare(strict_types=1);

require_once __DIR__ . '/_admin_auth.php';
require_once __DIR__ . '/../../includes/work_tracker.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('POST required');
}

$jobId = (int)($_POST['job_id'] ?? 0);
$rawIds = trim((string)($_POST['material_ids'] ?? ''));
$groupLabel = trim((string)($_POST['group_label'] ?? 'grouped receipt'));

if ($jobId <= 0 || $rawIds === '') {
    exit('Invalid request.');
}

wt_job($pdo, $jobId);

$ids = array_values(array_unique(array_filter(
    array_map('intval', explode(',', $rawIds)),
    static fn(int $id): bool => $id > 0
)));

if (!$ids || count($ids) > 250) {
    exit('Invalid material group.');
}

$marks = implode(',', array_fill(0, count($ids), '?'));
$params = array_merge([$jobId], $ids);
$check = $pdo->prepare(
    "SELECT id FROM work_materials WHERE job_id=? AND id IN ($marks)"
);
$check->execute($params);
$found = array_map('intval', $check->fetchAll(PDO::FETCH_COLUMN));
sort($found);
$expected = $ids;
sort($expected);

if ($found !== $expected) {
    exit('One or more grouped materials do not belong to this job. Nothing changed.');
}

$groupLabel = mb_substr($groupLabel, 0, 180);
$auditNote = '[Excluded duplicate group ' . date('Y-m-d H:i:s') . '] ' . $groupLabel;

$pdo->beginTransaction();
try {
    $updateParams = array_merge([$auditNote, $jobId], $ids);
    $update = $pdo->prepare(
        "UPDATE work_materials
         SET material_status='not_required',
             notes=CONCAT_WS(CHAR(10), NULLIF(notes,''), ?),
             updated_at=NOW()
         WHERE job_id=? AND id IN ($marks)"
    );
    $update->execute($updateParams);
    $pdo->commit();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $error;
}

header('Location: ../../admin/work/invoice_preparation.php?id=' . $jobId . '&group_excluded=1');
exit;
