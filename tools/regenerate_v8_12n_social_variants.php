<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/work_tracker.php';

$jobId = (int)($argv[1] ?? 0);
$verbose = in_array('--verbose', $argv, true);

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
    // Platform and legacy social copies may already contain white padding,
    // labels or logos. Never feed one branded derivative into another. Only
    // the clean stored source is valid input for a fresh platform crop.
    $sourceRelative = trim((string)($photo['relative_path'] ?? ''));
    $sourceMime = trim((string)($photo['mime_type'] ?? '')) ?: 'image/jpeg';

    if ($sourceRelative === '' || !is_file(wt_task_photo_path($sourceRelative))) {
        $skipped++;
        if (count($missingExamples) < 8) {
            $missingExamples[] = '#' . (int)$photo['id'] . ': clean stored source is unavailable; existing variants were left unchanged';
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
