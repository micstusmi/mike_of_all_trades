<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only.\n"); }
require_once __DIR__ . '/../includes/work_tracker.php';
require_once __DIR__ . '/../includes/work_social_video.php';
$dryRun=in_array('--dry-run',$argv??[],true); $deleted=0;
$q=$pdo->query("SELECT id,output_relative_path,subtitle_relative_path FROM work_social_videos WHERE expires_at IS NOT NULL AND expires_at<NOW() AND file_deleted_at IS NULL ORDER BY id LIMIT 200");
foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row){$ok=true;foreach(['output_relative_path','subtitle_relative_path'] as $column){if(empty($row[$column]))continue;$path=wt_social_video_path((string)$row[$column]);echo($dryRun?'Would delete ':'Deleting ').$path.PHP_EOL;if(!$dryRun&&is_file($path)&&!@unlink($path))$ok=false;}if(!$dryRun&&$ok){$pdo->prepare("UPDATE work_social_videos SET file_deleted_at=NOW() WHERE id=?")->execute([(int)$row['id']]);$deleted++;}}
echo "Expired slideshow cleanup complete. Records cleaned: {$deleted}.".($dryRun?' Dry run only.':'').PHP_EOL;
