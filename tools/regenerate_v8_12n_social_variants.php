<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/work_tracker.php';

$jobId = (int)($argv[1] ?? 0);
$verbose = in_array('--verbose', $argv, true);

function regen_find_photo_by_basename(string $relativePath): string
{
    $basename = basename($relativePath);
    if ($basename === '' || $basename === '.' || $basename === '..') {
        return '';
    }

    $baseDir = wt_task_photo_base_dir();
    if (!is_dir($baseDir)) {
        return '';
    }

    try {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($baseDir, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getFilename() === $basename) {
                return substr($file->getPathname(), strlen(rtrim($baseDir, '/')) + 1);
            }
        }
    } catch (Throwable $e) {
        return '';
    }

    return '';
}

$where = $jobId > 0 ? 'WHERE job_id=?' : '';
$params = $jobId > 0 ? [$jobId] : [];

$stmt = $pdo->prepare("
    SELECT id,
           relative_path,
           social_relative_path,
           instagram_relative_path,
           tiktok_relative_path,
           facebook_relative_path,
           mime_type,
           social_mime_type,
           instagram_mime_type,
           tiktok_mime_type,
           facebook_mime_type,
           photo_type
    FROM work_task_photos
    {$where}
    ORDER BY id
");
$stmt->execute($params);

$done = 0;
$skipped = 0;
$failed = 0;
$missingExamples = [];

foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $photo) {
    $outputRelative = (string)($photo['relative_path'] ?? '');
    $sourceRelative = '';
    $sourceMime = '';
    $missingTried = [];

    $candidates = [
        [(string)($photo['relative_path'] ?? ''), (string)($photo['mime_type'] ?? '')],
        [(string)($photo['social_relative_path'] ?? ''), (string)($photo['social_mime_type'] ?? 'image/jpeg')],
        [(string)($photo['facebook_relative_path'] ?? ''), (string)($photo['facebook_mime_type'] ?? 'image/jpeg')],
        [(string)($photo['instagram_relative_path'] ?? ''), (string)($photo['instagram_mime_type'] ?? 'image/jpeg')],
        [(string)($photo['tiktok_relative_path'] ?? ''), (string)($photo['tiktok_mime_type'] ?? 'image/jpeg')],
    ];

    foreach ($candidates as [$candidateRelative, $candidateMime]) {
        if ($candidateRelative !== '' && is_file(wt_task_photo_path($candidateRelative))) {
            $sourceRelative = $candidateRelative;
            $sourceMime = $candidateMime !== '' ? $candidateMime : 'image/jpeg';
            break;
        }
        if ($candidateRelative !== '') {
            $foundRelative = regen_find_photo_by_basename($candidateRelative);
            if ($foundRelative !== '' && is_file(wt_task_photo_path($foundRelative))) {
                $sourceRelative = $foundRelative;
                $sourceMime = $candidateMime !== '' ? $candidateMime : 'image/jpeg';
                break;
            }
            $missingTried[] = $candidateRelative;
        }
    }

    if ($sourceRelative === '') {
        $skipped++;
        if (count($missingExamples) < 8) {
            $missingExamples[] = '#' . (int)$photo['id'] . ' tried: ' . implode(', ', array_slice($missingTried, 0, 4));
        }
        continue;
    }

    if ($outputRelative === '') {
        $outputRelative = $sourceRelative;
    }

    $sourcePath = wt_task_photo_path($sourceRelative);
    $platformCopies = [];
    foreach (wt_platform_photo_specs() as $platform => $spec) {
        $platformCopies[$platform] = wt_create_platform_task_photo(
            $sourcePath,
            $sourceMime,
            (string)($photo['photo_type'] ?: 'progress'),
            $outputRelative,
            $platform,
            $spec
        );
    }

    if (!$platformCopies['instagram'] && !$platformCopies['tiktok'] && !$platformCopies['facebook']) {
        $failed++;
        if ($verbose) {
            echo 'Photo #' . (int)$photo['id'] . " found source but could not generate variants from {$sourceRelative}\n";
        }
        continue;
    }

    wt_update_task_photo_platform_copies($pdo, (int)$photo['id'], $platformCopies);
    wt_seed_social_drafts_for_photo($pdo, (int)$photo['id'], (string)($photo['photo_type'] ?: 'progress'));

    if ($verbose) {
        echo 'Photo #' . (int)$photo['id'] . " regenerated from {$sourceRelative}\n";
    }
    $done++;
}

echo "Regenerated social variants for {$done} photo(s). Skipped {$skipped} missing source file(s). Failed {$failed} generation attempt(s).\n";
if ($missingExamples) {
    echo "Missing examples:\n";
    foreach ($missingExamples as $example) {
        echo "- {$example}\n";
    }
}
