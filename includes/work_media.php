<?php
declare(strict_types=1);

function wt_task_photo_base_dir(): string
{
    $base = wt_env(
        'WORKTRACKER_PRIVATE_UPLOAD_DIR',
        dirname(__DIR__) . '/storage/private/job_intake'
    );

    return dirname(rtrim((string)$base, '/')) . '/task_photos';
}

function wt_task_photo_path(string $relativePath): string
{
    return wt_task_photo_base_dir() . '/' . ltrim($relativePath, '/');
}

function wt_photo_stage_label(string $photoType): string
{
    return match ($photoType) {
        'before' => 'BEFORE',
        'after' => 'AFTER',
        default => 'IN PROGRESS',
    };
}

function wt_social_photo_font(): ?string
{
    $candidates = [
        '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
        '/usr/share/fonts/dejavu/DejaVuSans-Bold.ttf',
        '/System/Library/Fonts/Supplemental/Arial Bold.ttf',
        '/System/Library/Fonts/Supplemental/Verdana Bold.ttf',
    ];

    foreach ($candidates as $font) {
        if (is_file($font)) {
            return $font;
        }
    }

    return null;
}

function wt_load_gd_image(string $path, string $mime)
{
    return match ($mime) {
        'image/jpeg' => imagecreatefromjpeg($path),
        'image/png' => imagecreatefrompng($path),
        'image/webp' => function_exists('imagecreatefromwebp')
            ? imagecreatefromwebp($path)
            : false,
        default => false,
    };
}

function wt_apply_jpeg_orientation($image, string $path, string $mime)
{
    if ($mime !== 'image/jpeg' || !function_exists('exif_read_data')) {
        return $image;
    }

    $exif = @exif_read_data($path);
    $orientation = (int)($exif['Orientation'] ?? 1);

    return match ($orientation) {
        3 => imagerotate($image, 180, 0),
        6 => imagerotate($image, -90, 0),
        8 => imagerotate($image, 90, 0),
        default => $image,
    };
}

function wt_task_photo_expiry_date(int $months = 12): string
{
    return date('Y-m-d H:i:s', strtotime('+' . max(1, $months) . ' months'));
}

function wt_save_optimised_task_photo(
    string $tmpPath,
    string $destPath,
    string $mime,
    int $maxSide = 1600,
    int $quality = 82
): ?array {
    if (!extension_loaded('gd')) {
        return null;
    }

    $source = wt_load_gd_image($tmpPath, $mime);
    if (!$source) {
        return null;
    }

    $source = wt_apply_jpeg_orientation($source, $tmpPath, $mime);
    $srcW = imagesx($source);
    $srcH = imagesy($source);

    if ($srcW < 1 || $srcH < 1) {
        return null;
    }

    $scale = min(1, $maxSide / max($srcW, $srcH));
    $outW = max(1, (int)round($srcW * $scale));
    $outH = max(1, (int)round($srcH * $scale));

    $canvas = imagecreatetruecolor($outW, $outH);
    $white = imagecolorallocate($canvas, 255, 255, 255);
    imagefilledrectangle($canvas, 0, 0, $outW, $outH, $white);
    imagecopyresampled($canvas, $source, 0, 0, 0, 0, $outW, $outH, $srcW, $srcH);

    if (!imagejpeg($canvas, $destPath, $quality)) {
        return null;
    }

    return [
        'mime_type' => 'image/jpeg',
        'file_size' => filesize($destPath) ?: 0,
        'sha256' => hash_file('sha256', $destPath) ?: null,
        'width' => $outW,
        'height' => $outH,
        'optimised' => true,
    ];
}

function wt_store_uploaded_task_photo_file(
    string $tmpPath,
    string $destPath,
    string $mime,
    int $originalSize
): array {
    $optimised = wt_save_optimised_task_photo($tmpPath, $destPath, $mime);

    if ($optimised) {
        return $optimised + [
            'original_file_size' => $originalSize,
            'server_expires_at' => wt_task_photo_expiry_date(12),
        ];
    }

    if (!move_uploaded_file($tmpPath, $destPath)) {
        throw new RuntimeException('A selected photo could not be saved.');
    }

    return [
        'mime_type' => $mime,
        'file_size' => filesize($destPath) ?: $originalSize,
        'sha256' => hash_file('sha256', $destPath) ?: null,
        'width' => null,
        'height' => null,
        'optimised' => false,
        'original_file_size' => $originalSize,
        'server_expires_at' => wt_task_photo_expiry_date(12),
    ];
}

function wt_update_task_photo_storage_metadata(PDO $pdo, int $photoId, array $stored): void
{
    try {
        $stmt = $pdo->prepare("
            UPDATE work_task_photos
            SET original_file_size=?,
                server_expires_at=?,
                social_expires_at=?
            WHERE id=?
        ");
        $stmt->execute([
            $stored['original_file_size'] ?? null,
            $stored['server_expires_at'] ?? null,
            wt_task_photo_expiry_date(12),
            $photoId,
        ]);
    } catch (Throwable $e) {
        error_log('Could not store task photo retention metadata: ' . $e->getMessage());
    }
}

function wt_create_branded_task_photo(
    string $sourcePath,
    string $mime,
    string $photoType,
    string $relativePath
): ?array {
    if (!extension_loaded('gd')) {
        return null;
    }

    $source = wt_load_gd_image($sourcePath, $mime);
    if (!$source) {
        return null;
    }

    $source = wt_apply_jpeg_orientation($source, $sourcePath, $mime);
    $srcW = imagesx($source);
    $srcH = imagesy($source);

    if ($srcW < 1 || $srcH < 1) {
        return null;
    }

    $maxSide = 1600;
    $scale = min(1, $maxSide / max($srcW, $srcH));
    $outW = max(1, (int)round($srcW * $scale));
    $outH = max(1, (int)round($srcH * $scale));

    $canvas = imagecreatetruecolor($outW, $outH);
    imagecopyresampled($canvas, $source, 0, 0, 0, 0, $outW, $outH, $srcW, $srcH);

    $label = wt_photo_stage_label($photoType);
    $font = wt_social_photo_font();
    $pad = max(18, (int)round(min($outW, $outH) * 0.025));
    $fontSize = max(28, (int)round(min($outW, $outH) * 0.052));
    $white = imagecolorallocate($canvas, 255, 255, 255);
    $shadow = imagecolorallocatealpha($canvas, 0, 0, 0, 45);
    $panel = imagecolorallocatealpha($canvas, 0, 0, 0, 55);

    if ($font) {
        $box = imagettfbbox($fontSize, 0, $font, $label);
        $textW = abs(($box[2] ?? 0) - ($box[0] ?? 0));
        $textH = abs(($box[7] ?? 0) - ($box[1] ?? 0));
        imagefilledrectangle(
            $canvas,
            $pad,
            $pad,
            $pad + $textW + ($pad * 2),
            $pad + $textH + ($pad * 2),
            $panel
        );
        imagettftext($canvas, $fontSize, 0, $pad * 2 + 2, $pad * 2 + $textH + 2, $shadow, $font, $label);
        imagettftext($canvas, $fontSize, 0, $pad * 2, $pad * 2 + $textH, $white, $font, $label);
    } else {
        imagefilledrectangle($canvas, $pad, $pad, $pad + 300, $pad + 70, $panel);
        imagestring($canvas, 5, $pad * 2, $pad * 2, $label, $white);
    }

    $logoPath = dirname(__DIR__) . '/assets/logos/mike_of_all_trades_logo.png';
    if (is_file($logoPath)) {
        $logo = @imagecreatefrompng($logoPath);
        if ($logo) {
            $logoW = imagesx($logo);
            $logoH = imagesy($logo);
            if ($logoW > 0 && $logoH > 0) {
                $targetW = max(90, (int)round($outW * 0.16));
                $targetH = max(90, (int)round($targetW * $logoH / $logoW));
                $x = $outW - $targetW - $pad;
                $y = $outH - $targetH - $pad;
                imagecopymerge($canvas, $logo, $x, $y, 0, 0, $targetW, $targetH, 36);
            }
        }
    }

    $sourceRelativeDir = trim(dirname($relativePath), '.');
    $baseName = pathinfo($relativePath, PATHINFO_FILENAME);
    $socialRelative = $sourceRelativeDir . '/social/' . $baseName . '_social.jpg';
    $socialPath = wt_task_photo_path($socialRelative);
    $socialDir = dirname($socialPath);

    if (!is_dir($socialDir) && !mkdir($socialDir, 0770, true) && !is_dir($socialDir)) {
        return null;
    }

    if (!imagejpeg($canvas, $socialPath, 88)) {
        return null;
    }

    return [
        'relative_path' => $socialRelative,
        'mime_type' => 'image/jpeg',
        'file_size' => filesize($socialPath) ?: 0,
        'sha256' => hash_file('sha256', $socialPath) ?: null,
    ];
}

function wt_update_task_photo_social_copy(PDO $pdo, int $photoId, ?array $social): void
{
    if (!$social) {
        return;
    }

    try {
        $stmt = $pdo->prepare("
            UPDATE work_task_photos
            SET social_relative_path=?,
                social_mime_type=?,
                social_file_size=?,
                social_sha256=?
            WHERE id=?
        ");
        $stmt->execute([
            $social['relative_path'],
            $social['mime_type'],
            $social['file_size'],
            $social['sha256'],
            $photoId,
        ]);
    } catch (Throwable $e) {
        error_log('Could not store branded task photo path: ' . $e->getMessage());
    }
}

function wt_after_task_photo_saved(
    PDO $pdo,
    int $photoId,
    string $absolutePath,
    string $mime,
    string $photoType,
    string $relativePath,
    ?array $stored = null
): void {
    if ($stored) {
        wt_update_task_photo_storage_metadata($pdo, $photoId, $stored);
    }

    $social = wt_create_branded_task_photo($absolutePath, $mime, $photoType, $relativePath);
    wt_update_task_photo_social_copy($pdo, $photoId, $social);
}
