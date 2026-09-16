<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only.\n"); }
require_once __DIR__ . '/../includes/work_tracker.php';
$limit=max(1,min(5000,(int)($argv[1]??1000))); $made=0; $skipped=0;
$q=$pdo->prepare("SELECT id,relative_path,mime_type FROM work_task_photos WHERE thumbnail_relative_path IS NULL ORDER BY id DESC LIMIT {$limit}");$q->execute();
$update=$pdo->prepare("UPDATE work_task_photos SET thumbnail_relative_path=?,thumbnail_mime_type=?,thumbnail_file_size=?,thumbnail_sha256=? WHERE id=?");
foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row){$path=wt_task_photo_path((string)$row['relative_path']);if(!is_file($path)){$skipped++;continue;}$thumb=wt_create_task_photo_thumbnail($path,(string)$row['mime_type'],(string)$row['relative_path']);if(!$thumb){$skipped++;continue;}$update->execute([$thumb['relative_path'],$thumb['mime_type'],$thumb['file_size'],$thumb['sha256'],(int)$row['id']]);$made++;}
echo "Thumbnail backfill complete. Created: {$made}; skipped missing/unreadable: {$skipped}.\n";
