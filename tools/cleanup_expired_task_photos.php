<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/work_tracker.php';
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only.\n"); }

$dryRun = in_array('--dry-run', $argv ?? [], true);
$limit = 500;

$stmt = $pdo->prepare("
    SELECT *
    FROM work_task_photos
    WHERE keep_permanent=0
      AND (
        (server_expires_at IS NOT NULL AND server_expires_at < NOW() AND file_deleted_at IS NULL)
        OR
        (social_expires_at IS NOT NULL AND social_expires_at < NOW() AND (
          (social_relative_path IS NOT NULL AND social_deleted_at IS NULL) OR
          (instagram_relative_path IS NOT NULL AND instagram_deleted_at IS NULL) OR
          (tiktok_relative_path IS NOT NULL AND tiktok_deleted_at IS NULL) OR
          (facebook_relative_path IS NOT NULL AND facebook_deleted_at IS NULL)
        ))
      )
    ORDER BY id
    LIMIT {$limit}
");
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$deletedOriginal = 0;
$deletedSocial = 0;

foreach ($rows as $row) {
    $updates = [];
    $params = [];

    if (!empty($row['server_expires_at']) && strtotime((string)$row['server_expires_at']) < time()) {
        $path = wt_task_photo_path((string)$row['relative_path']);
        if (is_file($path)) {
            echo ($dryRun ? 'Would delete ' : 'Deleting ') . $path . PHP_EOL;
            if (!$dryRun && @unlink($path)) {
                $deletedOriginal++;
                $updates[] = 'file_deleted_at=NOW()';
            }
        } else {
            $updates[] = 'file_deleted_at=COALESCE(file_deleted_at,NOW())';
        }
    }

    if (!empty($row['social_expires_at']) && strtotime((string)$row['social_expires_at']) < time()) {
        foreach (['social','instagram','tiktok','facebook'] as $variant) {
            $pathColumn = $variant . '_relative_path';
            $deletedColumn = $variant . '_deleted_at';
            if (empty($row[$pathColumn]) || !empty($row[$deletedColumn])) continue;
            $path = wt_task_photo_path((string)$row[$pathColumn]);
            echo ($dryRun ? 'Would delete ' : 'Deleting ') . $path . PHP_EOL;
            if (!$dryRun && (!is_file($path) || @unlink($path))) {
                $deletedSocial++;
                $updates[] = $deletedColumn . '=COALESCE(' . $deletedColumn . ',NOW())';
            }
        }
    }

    if (!$dryRun && $updates) {
        $params[] = (int)$row['id'];
        $sql = 'UPDATE work_task_photos SET ' . implode(',', array_unique($updates)) . ' WHERE id=?';
        $pdo->prepare($sql)->execute($params);
    }
}

echo 'Expired task photo cleanup complete. ';
echo 'Original files deleted: ' . $deletedOriginal . '. ';
echo 'Social variant files deleted: ' . $deletedSocial . '.';
echo $dryRun ? ' Dry run only.' : '';
echo PHP_EOL;
