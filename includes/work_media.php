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

function wt_allowed_task_photo_types(): array
{
    return [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/heic' => 'heic',
        'image/heif' => 'heif',
        'image/heic-sequence' => 'heic',
        'image/heif-sequence' => 'heif',
    ];
}

function wt_task_photo_accept_attr(): string
{
    // `image/*` is important on iOS: it keeps the Photos picker available.
    // Explicit HEIC/HEIF extensions (including uppercase) cover browsers that
    // do not advertise Apple's formats under image/*.
    return 'image/*,.heic,.heif,.HEIC,.HEIF';
}

function wt_normalise_uploaded_photo_mime(string $mime, string $originalName = ''): string
{
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    if ($ext === 'heic' && in_array($mime, [
        'application/octet-stream',
        'binary/octet-stream',
        'application/x-empty',
        'image/heic-sequence',
    ], true)) {
        return 'image/heic';
    }

    if ($ext === 'heif' && in_array($mime, [
        'application/octet-stream',
        'binary/octet-stream',
        'application/x-empty',
        'image/heif-sequence',
    ], true)) {
        return 'image/heif';
    }

    return $mime;
}

function wt_is_heic_photo(string $mime): bool
{
    return in_array($mime, ['image/heic', 'image/heif', 'image/heic-sequence', 'image/heif-sequence'], true);
}

function wt_task_photo_stored_extension(string $mime): string
{
    if (wt_is_heic_photo($mime)) {
        return 'jpg';
    }

    if (extension_loaded('gd') && in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        return 'jpg';
    }

    return wt_allowed_task_photo_types()[$mime] ?? 'jpg';
}

function wt_heic_support_error(): string
{
    return 'This iPhone HEIC/HEIF photo was selected, but this server cannot convert it to JPEG. Please enable ImageMagick/Imagick with HEIC support, or share/export this photo as JPEG and upload it again.';
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

function wt_save_heic_as_task_jpeg(
    string $tmpPath,
    string $destPath,
    int $maxSide = 1600,
    int $quality = 82
): array {
    $width = null;
    $height = null;

    if (extension_loaded('imagick') && class_exists('Imagick')) {
        try {
            $image = new Imagick();
            $image->readImage($tmpPath);
            if (method_exists($image, 'setIteratorIndex')) {
                $image->setIteratorIndex(0);
            }
            if (method_exists($image, 'setImageBackgroundColor')) {
                $image->setImageBackgroundColor('white');
            }
            if (method_exists($image, 'autoOrientImage')) {
                $image->autoOrientImage();
            }
            if (method_exists($image, 'thumbnailImage')) {
                // Preserve the photo's real aspect ratio. The fourth Imagick
                // argument is `fill`; true forces a rectangular photo onto a
                // square white canvas, which then makes later overlays appear
                // outside the photograph. Never add that canvas.
                $image->thumbnailImage($maxSide, $maxSide, true, false);
            }
            if (defined('Imagick::ORIENTATION_TOPLEFT')) {
                $image->setImageOrientation(Imagick::ORIENTATION_TOPLEFT);
            }
            $image->setImageFormat('jpeg');
            $image->setImageCompressionQuality($quality);
            $image->writeImage($destPath);
            $width = $image->getImageWidth();
            $height = $image->getImageHeight();
            $image->clear();
            $image->destroy();
        } catch (Throwable $e) {
            @unlink($destPath);
        }
    }

    if (!is_file($destPath)) {
        wt_convert_heic_with_cli($tmpPath, $destPath, $maxSide, $quality);
    }

    if (is_file($destPath) && ($width === null || $height === null)) {
        $size = @getimagesize($destPath);
        if (is_array($size)) {
            $width = (int)($size[0] ?? 0) ?: null;
            $height = (int)($size[1] ?? 0) ?: null;
        }
    }

    if (!is_file($destPath)) {
        throw new RuntimeException(wt_heic_support_error());
    }

    return [
        'mime_type' => 'image/jpeg',
        'file_size' => filesize($destPath) ?: 0,
        'sha256' => hash_file('sha256', $destPath) ?: null,
        'width' => $width,
        'height' => $height,
        'optimised' => true,
    ];
}

function wt_convert_heic_with_cli(string $tmpPath, string $destPath, int $maxSide, int $quality): void
{
    if (!function_exists('exec')) {
        throw new RuntimeException(wt_heic_support_error());
    }

    $commands = [];

    foreach (['/usr/bin/sips', 'sips'] as $sips) {
        $commands[] = sprintf(
            '%s -s format jpeg --resampleHeightWidthMax %d %s --out %s 2>&1',
            escapeshellcmd($sips),
            $maxSide,
            escapeshellarg($tmpPath),
            escapeshellarg($destPath)
        );
    }

    foreach (['magick', 'convert'] as $tool) {
        $commands[] = sprintf(
            '%s %s -auto-orient -resize %dx%d\\> -quality %d %s 2>&1',
            escapeshellcmd($tool),
            escapeshellarg($tmpPath),
            $maxSide,
            $maxSide,
            $quality,
            escapeshellarg($destPath)
        );
    }

    foreach ($commands as $command) {
        $output = [];
        $code = 1;
        @exec($command, $output, $code);

        if ($code === 0 && is_file($destPath) && (filesize($destPath) ?: 0) > 0) {
            return;
        }

        @unlink($destPath);
    }

    // Debian/Ubuntu commonly provide HEIC decoding through libheif's
    // `heif-convert`, even when the installed ImageMagick build has no HEIC
    // delegate. Decode to a temporary JPEG first, then resize that JPEG so the
    // stored task-photo copy remains storage-safe.
    foreach (['/usr/bin/heif-convert', 'heif-convert'] as $tool) {
        $decodedPath = $destPath . '.decoded-' . bin2hex(random_bytes(4)) . '.jpg';
        $decodeCommand = sprintf(
            '%s -q %d %s %s 2>&1',
            escapeshellcmd($tool),
            $quality,
            escapeshellarg($tmpPath),
            escapeshellarg($decodedPath)
        );
        $output = [];
        $code = 1;
        @exec($decodeCommand, $output, $code);

        if ($code !== 0 || !is_file($decodedPath) || (filesize($decodedPath) ?: 0) < 1) {
            @unlink($decodedPath);
            continue;
        }

        foreach (['magick', 'convert'] as $resizeTool) {
            $resizeCommand = sprintf(
                '%s %s -auto-orient -resize %dx%d\\> -quality %d %s 2>&1',
                escapeshellcmd($resizeTool),
                escapeshellarg($decodedPath),
                $maxSide,
                $maxSide,
                $quality,
                escapeshellarg($destPath)
            );
            $resizeOutput = [];
            $resizeCode = 1;
            @exec($resizeCommand, $resizeOutput, $resizeCode);
            if ($resizeCode === 0 && is_file($destPath) && (filesize($destPath) ?: 0) > 0) {
                @unlink($decodedPath);
                return;
            }
            @unlink($destPath);
        }

        if (extension_loaded('gd')) {
            $source = @imagecreatefromjpeg($decodedPath);
            if ($source) {
                $srcW = imagesx($source);
                $srcH = imagesy($source);
                $scale = min(1, $maxSide / max($srcW, $srcH));
                $outW = max(1, (int)round($srcW * $scale));
                $outH = max(1, (int)round($srcH * $scale));
                $canvas = imagecreatetruecolor($outW, $outH);
                imagecopyresampled($canvas, $source, 0, 0, 0, 0, $outW, $outH, $srcW, $srcH);
                $saved = imagejpeg($canvas, $destPath, $quality);
                imagedestroy($canvas);
                imagedestroy($source);
                @unlink($decodedPath);
                if ($saved && is_file($destPath) && (filesize($destPath) ?: 0) > 0) {
                    return;
                }
                @unlink($destPath);
                continue;
            }
        }

        // A successfully decoded JPEG is preferable to rejecting the upload.
        // This branch is only used when neither ImageMagick nor GD can resize.
        if (@rename($decodedPath, $destPath)) {
            return;
        }
        @unlink($decodedPath);
    }

    throw new RuntimeException(wt_heic_support_error());
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

function wt_task_photo_retention_months(string $kind): int
{
    $defaults = ['source' => 12, 'social' => 6, 'video' => 3];
    $envNames = ['source' => 'WORKTRACKER_PHOTO_RETENTION_MONTHS', 'social' => 'WORKTRACKER_SOCIAL_RETENTION_MONTHS', 'video' => 'WORKTRACKER_VIDEO_RETENTION_MONTHS'];
    $value = (int)wt_env($envNames[$kind] ?? '', (string)($defaults[$kind] ?? 12));
    return max(1, min(120, $value));
}

function wt_create_task_photo_thumbnail(string $sourcePath, string $mime, string $relativePath): ?array
{
    if (!extension_loaded('gd')) return null;
    $source = wt_load_gd_image($sourcePath, $mime);
    if (!$source) return null;
    $source = wt_apply_jpeg_orientation($source, $sourcePath, $mime);
    $srcW = imagesx($source); $srcH = imagesy($source);
    if ($srcW < 1 || $srcH < 1) return null;
    $scale = min(1, 360 / max($srcW, $srcH));
    $outW = max(1, (int)round($srcW * $scale)); $outH = max(1, (int)round($srcH * $scale));
    $canvas = imagecreatetruecolor($outW, $outH); $white = imagecolorallocate($canvas,255,255,255);
    imagefilledrectangle($canvas,0,0,$outW,$outH,$white);
    imagecopyresampled($canvas,$source,0,0,0,0,$outW,$outH,$srcW,$srcH);
    $relative = trim(dirname($relativePath), '.') . '/thumbnails/' . pathinfo($relativePath, PATHINFO_FILENAME) . '_thumb.jpg';
    $path = wt_task_photo_path($relative);
    if (!is_dir(dirname($path)) && !mkdir(dirname($path),0770,true) && !is_dir(dirname($path))) return null;
    if (!imagejpeg($canvas,$path,72)) return null;
    return ['relative_path'=>$relative,'mime_type'=>'image/jpeg','file_size'=>filesize($path) ?: 0,'sha256'=>hash_file('sha256',$path) ?: null];
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
    if (wt_is_heic_photo($mime)) {
        return wt_save_heic_as_task_jpeg($tmpPath, $destPath) + [
            'original_file_size' => $originalSize,
            'server_expires_at' => wt_task_photo_expiry_date(wt_task_photo_retention_months('source')),
        ];
    }

    $optimised = wt_save_optimised_task_photo($tmpPath, $destPath, $mime);

    if ($optimised) {
        return $optimised + [
            'original_file_size' => $originalSize,
            'server_expires_at' => wt_task_photo_expiry_date(wt_task_photo_retention_months('source')),
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
        'server_expires_at' => wt_task_photo_expiry_date(wt_task_photo_retention_months('source')),
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
            wt_task_photo_expiry_date(wt_task_photo_retention_months('social')),
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
        $labelPadX = (int)round($pad * 0.75);
        $labelPadY = (int)round($pad * 0.45);
        $labelW = $textW + ($labelPadX * 2);
        $labelH = $textH + ($labelPadY * 2);
        $labelX = max($pad, (int)round(($outW - $labelW) / 2));
        $labelY = $pad;
        imagefilledrectangle(
            $canvas,
            $labelX,
            $labelY,
            $labelX + $labelW,
            $labelY + $labelH,
            $panel
        );
        imagettftext($canvas, $fontSize, 0, $labelX + $labelPadX + 2, $labelY + $labelPadY + $textH + 2, $shadow, $font, $label);
        imagettftext($canvas, $fontSize, 0, $labelX + $labelPadX, $labelY + $labelPadY + $textH, $white, $font, $label);
    } else {
        $labelW = min($outW - ($pad * 2), 360);
        $labelX = max($pad, (int)round(($outW - $labelW) / 2));
        imagefilledrectangle($canvas, $labelX, $pad, $labelX + $labelW, $pad + 70, $panel);
        imagestring($canvas, 5, $labelX + 18, $pad + 22, $label, $white);
    }

    $logoPath = dirname(__DIR__) . '/assets/logos/mike_of_all_trades_logo_wireframe.png';
    if (is_file($logoPath)) {
        $logo = @imagecreatefrompng($logoPath);
        if ($logo) {
            $logoW = imagesx($logo);
            $logoH = imagesy($logo);
            if ($logoW > 0 && $logoH > 0) {
                $safeX = max(28, (int)round($outW * 0.06));
                $safeY = max(28, (int)round($outH * 0.06));
                $targetW = max(82, (int)round($outW * 0.12));
                $targetH = max(1, (int)round($targetW * $logoH / $logoW));
                $maxLogoH = max(100, (int)round($outH * 0.22));
                if ($targetH > $maxLogoH) {
                    $targetH = $maxLogoH;
                    $targetW = max(1, (int)round($targetH * $logoW / $logoH));
                }
                $x = max($safeX, $outW - $targetW - $safeX);
                $y = max($safeY, $outH - $targetH - $safeY);
                wt_copy_logo_watermark($canvas, $logo, $x, $y, $targetW, $targetH, 34);
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

function wt_platform_photo_specs(): array
{
    return [
        'instagram' => [
            'label' => 'Instagram',
            'width' => 1080,
            'height' => 1350,
            'quality' => 91,
            'fit' => 'cover',
            'shape' => 'instagram',
            'tag_position' => 'top-centre',
            'logo_position' => 'bottom-right',
            'label_top_ratio' => 0.035,
            'logo_right_ratio' => 0.035,
            'logo_bottom_ratio' => 0.035,
            'logo_alpha' => 38,
        ],
        'tiktok' => [
            'label' => 'TikTok',
            'width' => 1080,
            'height' => 1920,
            'quality' => 84,
            'fit' => 'cover',
            'shape' => 'portrait',
            'tag_position' => 'top-centre',
            'logo_position' => 'bottom-right',
            'label_top_ratio' => 0.04,
            'logo_right_ratio' => 0.045,
            'logo_bottom_ratio' => 0.045,
            'logo_alpha' => 38,
        ],
        'facebook' => [
            'label' => 'Facebook',
            'width' => 1600,
            'height' => 1200,
            'quality' => 86,
            'fit' => 'cover',
            'shape' => 'source',
            'tag_position' => 'top-centre',
            'logo_position' => 'bottom-right',
            'label_top_ratio' => 0.04,
            'logo_right_ratio' => 0.035,
            'logo_bottom_ratio' => 0.035,
            'logo_alpha' => 36,
        ],
    ];
}

function wt_platform_output_dimensions(array $spec, int $srcW, int $srcH): array
{
    $shape = (string)($spec['shape'] ?? 'fixed');
    $isLandscape = $srcW > $srcH;
    $isPortrait = $srcH > $srcW;

    if ($shape === 'instagram') {
        return $isLandscape ? [1080, 1080] : [1080, 1350];
    }

    if ($shape === 'portrait') {
        return [1080, 1920];
    }

    if ($shape === 'source') {
        if ($isLandscape) {
            return [1600, 900];
        }
        if ($isPortrait) {
            return [1200, 1600];
        }
        return [1200, 1200];
    }

    return [(int)$spec['width'], (int)$spec['height']];
}

function wt_imagecopy_platform_fit($canvas, $source, int $outW, int $outH, int $srcW, int $srcH, string $fit): void
{
    if ($fit === 'contain') {
        $scale = min($outW / $srcW, $outH / $srcH, 1);
        $drawW = max(1, (int)round($srcW * $scale));
        $drawH = max(1, (int)round($srcH * $scale));
        $x = (int)floor(($outW - $drawW) / 2);
        $y = (int)floor(($outH - $drawH) / 2);
        imagecopyresampled($canvas, $source, $x, $y, 0, 0, $drawW, $drawH, $srcW, $srcH);
        return;
    }

    $scale = max($outW / $srcW, $outH / $srcH);
    $cropW = max(1, (int)round($outW / $scale));
    $cropH = max(1, (int)round($outH / $scale));
    // Always use a centred crop. Never anchor the crop to the left edge.
    $srcX = max(0, (int)floor(($srcW - $cropW) / 2));
    $srcY = max(0, (int)floor(($srcH - $cropH) / 2));
    imagecopyresampled($canvas, $source, 0, 0, $srcX, $srcY, $outW, $outH, $cropW, $cropH);
}

function wt_copy_logo_watermark($canvas, $logo, int $x, int $y, int $targetW, int $targetH, int $opacity): void
{
    $logoW = imagesx($logo);
    $logoH = imagesy($logo);
    if ($logoW < 1 || $logoH < 1 || $targetW < 1 || $targetH < 1) {
        return;
    }

    $resized = imagecreatetruecolor($targetW, $targetH);
    imagealphablending($resized, false);
    imagesavealpha($resized, true);
    $transparent = imagecolorallocatealpha($resized, 255, 255, 255, 127);
    imagefill($resized, 0, 0, $transparent);
    imagecopyresampled($resized, $logo, 0, 0, 0, 0, $targetW, $targetH, $logoW, $logoH);

    $opacity = max(1, min(100, $opacity));
    for ($py = 0; $py < $targetH; $py++) {
        for ($px = 0; $px < $targetW; $px++) {
            $rgba = imagecolorat($resized, $px, $py);
            $alpha = ($rgba >> 24) & 0x7F;
            if ($alpha >= 127) {
                continue;
            }

            $visible = 127 - $alpha;
            $newVisible = (int)round($visible * ($opacity / 100));
            $newAlpha = 127 - max(0, min(127, $newVisible));
            $r = ($rgba >> 16) & 0xFF;
            $g = ($rgba >> 8) & 0xFF;
            $b = $rgba & 0xFF;
            $colour = imagecolorallocatealpha($resized, $r, $g, $b, $newAlpha);
            imagesetpixel($resized, $px, $py, $colour);
        }
    }

    imagealphablending($canvas, true);
    imagecopy($canvas, $resized, $x, $y, 0, 0, $targetW, $targetH);
}

function wt_draw_social_photo_marks($canvas, int $outW, int $outH, string $photoType, array $spec): void
{
    $label = wt_photo_stage_label($photoType);
    $font = wt_social_photo_font();
    // All coordinates below are relative to the final cropped bitmap. They are
    // deliberately independent of the HTML preview/card dimensions.
    $pad = max(28, (int)round(min($outW, $outH) * 0.032));
    $fontSize = max(28, (int)round(min($outW, $outH) * 0.038));
    $white = imagecolorallocate($canvas, 255, 255, 255);
    $shadow = imagecolorallocatealpha($canvas, 0, 0, 0, 46);
    $panel = imagecolorallocatealpha($canvas, 0, 0, 0, 58);

    if ($font) {
        $box = imagettfbbox($fontSize, 0, $font, $label);
        $textW = abs(($box[2] ?? 0) - ($box[0] ?? 0));
        $textH = abs(($box[7] ?? 0) - ($box[1] ?? 0));
        $labelPadX = (int)round($pad * 0.7);
        $labelPadY = (int)round($pad * 0.45);
        $labelW = $textW + ($labelPadX * 2);
        $labelH = $textH + ($labelPadY * 2);
        $labelX = max($pad, (int)round(($outW - $labelW) / 2));
        $labelY = max($pad, (int)round($outH * (float)($spec['label_top_ratio'] ?? 0.06)));
        imagefilledrectangle($canvas, $labelX, $labelY, $labelX + $labelW, $labelY + $labelH, $panel);
        imagettftext($canvas, $fontSize, 0, $labelX + $labelPadX + 2, $labelY + $labelPadY + $textH + 2, $shadow, $font, $label);
        imagettftext($canvas, $fontSize, 0, $labelX + $labelPadX, $labelY + $labelPadY + $textH, $white, $font, $label);
    } else {
        $labelW = min($outW - ($pad * 2), 380);
        $labelX = max($pad, (int)round(($outW - $labelW) / 2));
        $labelY = max($pad, (int)round($outH * (float)($spec['label_top_ratio'] ?? 0.06)));
        imagefilledrectangle($canvas, $labelX, $labelY, $labelX + $labelW, $labelY + 80, $panel);
        imagestring($canvas, 5, $labelX + 18, $labelY + 24, $label, $white);
    }

    $logoPath = dirname(__DIR__) . '/assets/logos/mike_of_all_trades_logo_wireframe.png';
    if (!is_file($logoPath)) {
        $logoPath = dirname(__DIR__) . '/assets/logos/mike_of_all_trades_logo.png';
    }
    if (!is_file($logoPath)) {
        return;
    }

    $logo = @imagecreatefrompng($logoPath);
    if (!$logo) {
        return;
    }

    $logoW = imagesx($logo);
    $logoH = imagesy($logo);
    if ($logoW < 1 || $logoH < 1) {
        return;
    }

    // Anchor the complete wireframe mark to the bottom-right of the final
    // cropped bitmap. Preserve its aspect ratio and keep every pixel inside.
    $safeX = max(28, (int)round($outW * (float)($spec['logo_right_ratio'] ?? $spec['logo_left_ratio'] ?? 0.035)));
    $safeY = max(28, (int)round($outH * (float)($spec['logo_bottom_ratio'] ?? 0.035)));
    $targetW = max(76, (int)round($outW * 0.105));
    $targetH = max(1, (int)round($targetW * $logoH / $logoW));
    $maxLogoH = max(100, (int)round($outH * 0.18));
    if ($targetH > $maxLogoH) {
        $targetH = $maxLogoH;
        $targetW = max(1, (int)round($targetH * $logoW / $logoH));
    }
    if (($spec['logo_position'] ?? 'bottom-right') === 'middle-left') {
        $x = max(48, (int)round($outW * (float)($spec['logo_left_ratio'] ?? 0.06)));
        $y = max(48, min($outH - $targetH - 48, (int)round($outH * (float)($spec['logo_top_ratio'] ?? 0.50))));
    } else {
        $x = max($safeX, $outW - $targetW - $safeX);
        $y = max($safeY, $outH - $targetH - $safeY);
    }
    wt_copy_logo_watermark($canvas, $logo, $x, $y, $targetW, $targetH, (int)($spec['logo_alpha'] ?? 32));
}

function wt_create_platform_task_photo(
    string $sourcePath,
    string $mime,
    string $photoType,
    string $relativePath,
    string $platform,
    array $spec
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

    [$outW, $outH] = wt_platform_output_dimensions($spec, $srcW, $srcH);
    $canvas = imagecreatetruecolor($outW, $outH);
    $white = imagecolorallocate($canvas, 255, 255, 255);
    imagefilledrectangle($canvas, 0, 0, $outW, $outH, $white);
    wt_imagecopy_platform_fit($canvas, $source, $outW, $outH, $srcW, $srcH, (string)$spec['fit']);
    wt_draw_social_photo_marks($canvas, $outW, $outH, $photoType, $spec);

    $sourceRelativeDir = trim(dirname($relativePath), '.');
    $baseName = pathinfo($relativePath, PATHINFO_FILENAME);
    $platformRelative = $sourceRelativeDir . '/social/' . $baseName . '_' . $platform . '.jpg';
    $platformPath = wt_task_photo_path($platformRelative);
    $platformDir = dirname($platformPath);

    if (!is_dir($platformDir) && !mkdir($platformDir, 0770, true) && !is_dir($platformDir)) {
        return null;
    }

    if (!imagejpeg($canvas, $platformPath, (int)$spec['quality'])) {
        return null;
    }

    return [
        'relative_path' => $platformRelative,
        'mime_type' => 'image/jpeg',
        'file_size' => filesize($platformPath) ?: 0,
        'sha256' => hash_file('sha256', $platformPath) ?: null,
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

function wt_update_task_photo_platform_copies(PDO $pdo, int $photoId, array $platformCopies): void
{
    if (!$platformCopies) {
        return;
    }

    foreach ($platformCopies as $platform => $copy) {
        if (!$copy || !in_array($platform, ['instagram', 'tiktok', 'facebook'], true)) {
            continue;
        }

        try {
            $stmt = $pdo->prepare("
                UPDATE work_task_photos
                SET {$platform}_relative_path=?,
                    {$platform}_mime_type=?,
                    {$platform}_file_size=?,
                    {$platform}_sha256=?
                WHERE id=?
            ");
            $stmt->execute([
                $copy['relative_path'],
                $copy['mime_type'],
                $copy['file_size'],
                $copy['sha256'],
                $photoId,
            ]);
        } catch (Throwable $e) {
            error_log('Could not store ' . $platform . ' task photo path: ' . $e->getMessage());
        }
    }
}

function wt_seed_social_drafts_for_photo(PDO $pdo, int $photoId, string $photoType): void
{
    try {
        $stmt = $pdo->prepare("
            SELECT p.job_id,p.note,p.created_at,t.title AS task_title,j.job_address
            FROM work_task_photos p
            LEFT JOIN work_tasks t ON t.id=p.task_id
            JOIN work_jobs j ON j.id=p.job_id
            WHERE p.id=?
            LIMIT 1
        ");
        $stmt->execute([$photoId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return;
        }

        $jobId = (int)$row['job_id'];
        $stage = wt_photo_stage_label($photoType);
        $task = trim((string)($row['task_title'] ?? 'this job'));
        $suburb = trim((string)preg_replace('/^.*\b([A-Za-z]+)\s+\d{4}$/', '$1', (string)($row['job_address'] ?? '')));

        $platforms = [
            'instagram' => [
                'title' => 'The hidden stage that can make or break this finish',
                'caption' => "This is the crucial part people rarely see: {$task} taking shape through careful preparation, sharp attention to detail and patient decisions. The glamorous reveal comes later—right now is where a tired problem starts becoming a result worth feeling proud of.",
                'short' => 'The transformation starts long before the final reveal.',
                'tags' => '#MikeOfAllTrades #HandymanVictoria #HomeMaintenance #BeforeAfter #PropertyMaintenance',
                'plan' => 'Use the Instagram portrait/square branded photo. Good for carousel or story update.',
            ],
            'tiktok' => [
                'title' => 'Wait until you see what this awkward stage becomes',
                'caption' => "It looked simple at first glance—but the real story is hidden in the preparation, the stubborn details and knowing exactly what must never be rushed. This messy middle is building toward one seriously satisfying transformation.",
                'short' => 'Messy middle. Careful choices. Satisfying reveal loading.',
                'tags' => '#handyman #propertymaintenance #beforeafter #tradielife #melbournehomes',
                'plan' => 'Use the TikTok portrait version. Best as a slideshow/reel step in the job journey.',
            ],
            'facebook' => [
                'title' => 'Why this “small” job deserves a closer look' . ($suburb !== '' ? ' in ' . $suburb : ''),
                'caption' => "This {$stage} stage of {$task} reveals what homeowners rarely get to see: thoughtful preparation, practical judgement and patient problem-solving before the polished result arrives. Watching an awkward problem become neat, dependable and cared for is one of the most satisfying parts of the work.",
                'short' => 'A stubborn problem becoming a result worth feeling proud of.',
                'tags' => '#MikeOfAllTrades #LocalHandyman #HomeRepairs #Maintenance',
                'plan' => 'Use Facebook version, or a mixed group of before/progress/after photos if available.',
            ],
        ];

        $exists = $pdo->prepare("
            SELECT COUNT(*)
            FROM work_social_drafts
            WHERE job_id=? AND platform=? AND draft_date=CURDATE() AND selected_photo_ids=?
        ");
        $insert = $pdo->prepare("
            INSERT INTO work_social_drafts
            (job_id,draft_date,platform,tone,title,caption,short_caption,hashtags,photo_plan,selected_photo_ids,ai_model,raw_ai)
            VALUES(?,CURDATE(),?,'auto_journey',?,?,?,?,?,?,'auto-template','')
        ");

        foreach ($platforms as $platform => $draft) {
            $exists->execute([$jobId, $platform, (string)$photoId]);
            if ((int)$exists->fetchColumn() > 0) {
                continue;
            }
            $insert->execute([
                $jobId,
                $platform,
                mb_substr($draft['title'], 0, 255),
                $draft['caption'],
                $draft['short'],
                $draft['tags'],
                $draft['plan'],
                (string)$photoId,
            ]);
        }
    } catch (Throwable $e) {
        error_log('Could not seed platform social draft: ' . $e->getMessage());
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

    $thumbnail = wt_create_task_photo_thumbnail($absolutePath, $mime, $relativePath);
    if ($thumbnail) {
        try {
            $pdo->prepare("UPDATE work_task_photos SET thumbnail_relative_path=?,thumbnail_mime_type=?,thumbnail_file_size=?,thumbnail_sha256=? WHERE id=?")
                ->execute([$thumbnail['relative_path'],$thumbnail['mime_type'],$thumbnail['file_size'],$thumbnail['sha256'],$photoId]);
        } catch (Throwable $e) {
            @unlink(wt_task_photo_path((string)$thumbnail['relative_path']));
            error_log('Could not store task photo thumbnail metadata: ' . $e->getMessage());
        }
    }

    $social = wt_create_branded_task_photo($absolutePath, $mime, $photoType, $relativePath);
    wt_update_task_photo_social_copy($pdo, $photoId, $social);

    $platformCopies = [];
    foreach (wt_platform_photo_specs() as $platform => $spec) {
        $platformCopies[$platform] = wt_create_platform_task_photo($absolutePath, $mime, $photoType, $relativePath, $platform, $spec);
    }
    wt_update_task_photo_platform_copies($pdo, $photoId, $platformCopies);
    wt_seed_social_drafts_for_photo($pdo, $photoId, $photoType);
}

function wt_upload_field_has_files(string $fieldName): bool
{
    $files = $_FILES[$fieldName] ?? null;
    if (!$files || !isset($files['name'])) {
        return false;
    }

    $names = is_array($files['name']) ? $files['name'] : [$files['name']];
    $errors = is_array($files['error']) ? $files['error'] : [$files['error']];

    foreach ($names as $i => $name) {
        if ((string)$name !== '' && (($errors[$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE)) {
            return true;
        }
    }

    return false;
}

function wt_upload_field_file_count(string $fieldName): int
{
    $files = $_FILES[$fieldName] ?? null;
    if (!$files || !isset($files['name'])) {
        return 0;
    }

    $names = is_array($files['name']) ? $files['name'] : [$files['name']];
    $errors = is_array($files['error']) ? $files['error'] : [$files['error']];
    $count = 0;

    foreach ($names as $i => $name) {
        if ((string)$name !== '' && (($errors[$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE)) {
            $count++;
        }
    }

    return $count;
}

function wt_save_task_photo_upload_field(
    PDO $pdo,
    int $jobId,
    int $taskId,
    string $fieldName,
    string $photoType,
    string $note = '',
    int $limit = 20
): int {
    if (
        $jobId <= 0 ||
        $taskId <= 0 ||
        !in_array($photoType, ['before', 'progress', 'after'], true)
    ) {
        return 0;
    }

    $files = $_FILES[$fieldName] ?? null;
    if (!$files || !isset($files['name'])) {
        return 0;
    }

    $names = is_array($files['name']) ? $files['name'] : [$files['name']];
    $tmpNames = is_array($files['tmp_name']) ? $files['tmp_name'] : [$files['tmp_name']];
    $errors = is_array($files['error']) ? $files['error'] : [$files['error']];
    $sizes = is_array($files['size']) ? $files['size'] : [$files['size']];

    if (count($names) > $limit) {
        throw new RuntimeException('Please upload no more than ' . $limit . ' photos at once.');
    }

    $allowed = wt_allowed_task_photo_types();
    $photoBase = wt_task_photo_base_dir();
    $dir = $photoBase . '/job_' . $jobId . '/task_' . $taskId;

    if (!is_dir($dir) && !mkdir($dir, 0770, true) && !is_dir($dir)) {
        throw new RuntimeException('Could not create photo directory.');
    }

    $denyFile = $photoBase . '/.htaccess';
    if (!is_file($denyFile)) {
        @file_put_contents(
            $denyFile,
            "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n" .
            "<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n"
        );
    }

    $insert = $pdo->prepare("
        INSERT INTO work_task_photos
        (job_id,task_id,photo_type,uploader_type,original_name,stored_name,relative_path,mime_type,file_size,sha256,note,keep_permanent,created_at)
        VALUES(?,?,?,?,?,?,?,?,?,?,?,?,NOW())
    ");

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $uploaded = 0;

    foreach ($names as $i => $originalName) {
        if (($errors[$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            continue;
        }

        $size = (int)($sizes[$i] ?? 0);
        if ($size <= 0 || $size > 20 * 1024 * 1024) {
            continue;
        }

        $tmp = (string)($tmpNames[$i] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            continue;
        }

        $mime = wt_normalise_uploaded_photo_mime((string)$finfo->file($tmp), (string)$originalName);
        if (!isset($allowed[$mime])) {
            continue;
        }

        $safeOriginal = preg_replace('/[^A-Za-z0-9._-]+/', '_', basename((string)$originalName)) ?: 'photo.' . $allowed[$mime];
        $stored = $photoType . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(5)) . '.' . wt_task_photo_stored_extension($mime);
        $dest = $dir . '/' . $stored;
        $storedInfo = wt_store_uploaded_task_photo_file($tmp, $dest, $mime, $size);
        $relative = 'job_' . $jobId . '/task_' . $taskId . '/' . $stored;

        $insert->execute([
            $jobId,
            $taskId,
            $photoType,
            'mike',
            $safeOriginal,
            $stored,
            $relative,
            $storedInfo['mime_type'],
            $storedInfo['file_size'],
            $storedInfo['sha256'],
            $note !== '' ? $note : null,
            0,
        ]);

        wt_after_task_photo_saved($pdo, (int)$pdo->lastInsertId(), $dest, (string)$storedInfo['mime_type'], $photoType, $relative, $storedInfo);
        $uploaded++;
    }

    return $uploaded;
}
