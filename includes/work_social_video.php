<?php
declare(strict_types=1);

function wt_social_video_base_dir(): string
{
    return dirname(wt_task_photo_base_dir()) . '/social_videos';
}

function wt_social_video_path(string $relative): string
{
    return wt_social_video_base_dir() . '/' . ltrim($relative, '/');
}

function wt_social_music_dir(): string
{
    return dirname(wt_task_photo_base_dir()) . '/social_music';
}

function wt_social_video_platforms(): array
{
    return [
        'tiktok' => 'TikTok',
        'instagram_reel' => 'Instagram Reel',
        'youtube_short' => 'YouTube Short',
    ];
}

function wt_social_video_voices(): array
{
    return [
        'alloy' => 'Alloy — neutral',
        'ash' => 'Ash — warm',
        'echo' => 'Echo — clear',
        'fable' => 'Fable — expressive',
        'onyx' => 'Onyx — deeper',
        'nova' => 'Nova — bright',
        'sage' => 'Sage — calm',
        'shimmer' => 'Shimmer — friendly',
    ];
}

function wt_social_video_accents(): array
{
    return [
        'australian' => 'Australian — natural',
        'new_zealand' => 'New Zealand — natural',
        'british' => 'British — natural',
        'american' => 'American — natural',
        'neutral' => 'Neutral / international',
    ];
}

function wt_social_voice_accent_instruction(string $accent): string
{
    $instructions = [
        'australian' => 'Speak in a natural, contemporary Australian English accent suitable for a Melbourne tradesperson. Use authentic Australian pronunciation without exaggeration, parody or stereotypes.',
        'new_zealand' => 'Speak in a natural, contemporary New Zealand English accent without exaggeration, parody or stereotypes.',
        'british' => 'Speak in a natural, contemporary British English accent without sounding theatrical, exaggerated or stereotyped.',
        'american' => 'Speak in a natural, contemporary American English accent without sounding theatrical, exaggerated or stereotyped.',
        'neutral' => 'Speak in clear international English with a neutral, easily understood accent.',
    ];

    return $instructions[$accent] ?? $instructions['australian'];
}

function wt_social_music_files(): array
{
    $dir = wt_social_music_dir();
    if (!is_dir($dir)) return [];
    $files = [];
    foreach (scandir($dir) ?: [] as $name) {
        if ($name === '.' || $name === '..') continue;
        if (!preg_match('/\.(mp3|m4a|aac|wav)$/i', $name)) continue;
        if (is_file($dir . '/' . $name)) $files[] = $name;
    }
    natcasesort($files);
    return array_values($files);
}

function wt_social_video_binary(string $envName, array $candidates): ?string
{
    $configured = trim((string)wt_env($envName, ''));
    if ($configured !== '' && is_executable($configured)) return $configured;
    foreach ($candidates as $candidate) {
        if (str_contains($candidate, '/') && is_executable($candidate)) return $candidate;
        $found = trim((string)@shell_exec('command -v ' . escapeshellarg($candidate) . ' 2>/dev/null'));
        if ($found !== '' && is_executable($found)) return $found;
    }
    return null;
}

function wt_social_ffmpeg(): ?string
{
    return wt_social_video_binary('WORKTRACKER_FFMPEG_PATH', [
        '/opt/homebrew/bin/ffmpeg', '/usr/local/bin/ffmpeg', '/usr/bin/ffmpeg', 'ffmpeg'
    ]);
}

function wt_social_ffprobe(): ?string
{
    return wt_social_video_binary('WORKTRACKER_FFPROBE_PATH', [
        '/opt/homebrew/bin/ffprobe', '/usr/local/bin/ffprobe', '/usr/bin/ffprobe', 'ffprobe'
    ]);
}

function wt_start_social_video_worker(): array
{
    if (!function_exists('exec')) return ['started'=>false,'message'=>'PHP exec() is disabled.'];
    $php = wt_social_video_binary('WORKTRACKER_PHP_PATH', [
        PHP_BINDIR . '/php',
        '/Applications/XAMPP/xamppfiles/bin/php',
        '/usr/bin/php',
        '/usr/local/bin/php',
        '/opt/homebrew/bin/php',
        'php',
    ]);
    $worker = dirname(__DIR__) . '/tools/process_social_video_queue.php';
    if (!$php) return ['started'=>false,'message'=>'The command-line PHP executable could not be found. Set WORKTRACKER_PHP_PATH.'];
    if (!is_file($worker)) return ['started'=>false,'message'=>'The social video worker script is missing: tools/process_social_video_queue.php'];
    $log = sys_get_temp_dir() . '/mot_social_video_worker.log';
    $command = escapeshellarg($php) . ' ' . escapeshellarg($worker) . ' 1 >> ' . escapeshellarg($log) . ' 2>&1 & echo $!';
    $output=[]; $code=1; @exec($command,$output,$code);
    $pid=(int)trim((string)($output[0]??''));
    return $code===0 && $pid>0
        ? ['started'=>true,'message'=>'Rendering started in the background.','pid'=>$pid]
        : ['started'=>false,'message'=>'The video was queued, but the web server could not start the renderer automatically.'];
}

function wt_social_media_duration(string $path): ?float
{
    $ffprobe = wt_social_ffprobe();
    if (!$ffprobe || !is_file($path) || !function_exists('exec')) return null;
    $command = escapeshellarg($ffprobe) . ' -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 ' . escapeshellarg($path) . ' 2>/dev/null';
    $output = []; $code = 1; exec($command, $output, $code);
    if ($code !== 0 || !isset($output[0]) || !is_numeric(trim($output[0]))) return null;
    return (float)trim($output[0]);
}

function wt_social_music_catalog(): array
{
    static $catalog = null;
    if (is_array($catalog)) return $catalog;
    $catalog = [];
    foreach (wt_social_music_files() as $name) {
        $catalog[] = [
            'name' => $name,
            'duration_seconds' => wt_social_media_duration(wt_social_music_dir() . '/' . $name),
        ];
    }
    return $catalog;
}

function wt_social_duration_label(?float $seconds): string
{
    if ($seconds === null || $seconds <= 0) return 'length unavailable';
    $whole = (int)round($seconds);
    return sprintf('%d:%02d', intdiv($whole, 60), $whole % 60);
}

function wt_social_choose_music(float $videoSeconds, array $recentMusic = []): ?array
{
    $catalog = wt_social_music_catalog();
    if (!$catalog) return null;
    $bestScore = INF;
    $shortTrackPenalty = min(20.0, max(8.0, $videoSeconds * 0.25));
    $scored = [];
    foreach ($catalog as $track) {
        $duration = $track['duration_seconds'];
        if ($duration === null || $duration <= 0) continue;
        // Prefer a slightly longer song that can be trimmed over a slightly
        // shorter one that must loop, without choosing an excessively long song.
        $score = $duration >= $videoSeconds
            ? $duration - $videoSeconds
            : ($videoSeconds - $duration) + $shortTrackPenalty;
        $track['fit_score'] = $score;
        $scored[] = $track;
        $bestScore = min($bestScore, $score);
    }
    if (!$scored) return $catalog[0];

    // Treat very similar durations as an equally suitable pool. This avoids
    // choosing the same first 61-second song for every roughly minute-long post.
    $tieWindow = max(2.0, $videoSeconds * 0.04);
    $pool = array_values(array_filter(
        $scored,
        static fn(array $track): bool => (float)$track['fit_score'] <= $bestScore + $tieWindow
    ));

    $usage = array_count_values(array_values(array_filter(
        array_map('strval', $recentMusic),
        static fn(string $name): bool => $name !== '' && $name !== '__auto__'
    )));
    $lastUsedPosition = [];
    foreach ($recentMusic as $position => $name) {
        $name = (string)$name;
        if ($name !== '' && !isset($lastUsedPosition[$name])) {
            $lastUsedPosition[$name] = (int)$position;
        }
    }

    usort($pool, static function(array $a, array $b) use ($usage, $lastUsedPosition): int {
        $aName = (string)$a['name'];
        $bName = (string)$b['name'];
        $byUsage = ($usage[$aName] ?? 0) <=> ($usage[$bName] ?? 0);
        if ($byUsage !== 0) return $byUsage;

        // Recent history is newest first, so the larger position was used longer ago.
        $aPosition = $lastUsedPosition[$aName] ?? PHP_INT_MAX;
        $bPosition = $lastUsedPosition[$bName] ?? PHP_INT_MAX;
        $byRecency = $bPosition <=> $aPosition;
        if ($byRecency !== 0) return $byRecency;

        $byFit = (float)$a['fit_score'] <=> (float)$b['fit_score'];
        return $byFit !== 0 ? $byFit : strnatcasecmp($aName, $bName);
    });

    return $pool[0] ?? $scored[0];
}

function wt_social_video_wrap(string $text, int $maxChars = 34): array
{
    $words = preg_split('/\s+/', trim($text)) ?: [];
    $lines = [];
    $line = '';
    foreach ($words as $word) {
        $candidate = $line === '' ? $word : $line . ' ' . $word;
        if ($line !== '' && mb_strlen($candidate) > $maxChars) {
            $lines[] = $line;
            $line = $word;
        } else {
            $line = $candidate;
        }
    }
    if ($line !== '') $lines[] = $line;
    return array_slice($lines, 0, 4);
}

function wt_social_video_frame(string $sourcePath, string $mime, string $stage, string $caption, string $dest): void
{
    if (!extension_loaded('gd')) throw new RuntimeException('PHP GD is required to create slideshow frames.');
    $source = wt_load_gd_image($sourcePath, $mime);
    if (!$source) throw new RuntimeException('A selected source photo could not be read.');
    $source = wt_apply_jpeg_orientation($source, $sourcePath, $mime);
    $srcW = imagesx($source); $srcH = imagesy($source);
    $canvas = imagecreatetruecolor(1080, 1920);
    $black = imagecolorallocate($canvas, 0, 0, 0);
    imagefill($canvas, 0, 0, $black);
    wt_imagecopy_platform_fit($canvas, $source, 1080, 1920, $srcW, $srcH, 'cover');
    // Reel overlays use a different safe-area layout from static carousel
    // images. Keep the stage label below the platform header, move the brand
    // mark away from right-side controls and reserve the lower quarter for
    // readable embedded captions.
    wt_draw_social_photo_marks($canvas, 1080, 1920, $stage, [
        'label_top_ratio' => 0.20,
        'logo_position' => 'middle-left',
        'logo_left_ratio' => 0.06,
        'logo_top_ratio' => 0.50,
        'logo_alpha' => 40,
    ]);

    $font = wt_social_photo_font();
    $lines = wt_social_video_wrap($caption, 34);
    $lineHeight = 58;
    $panelH = max(150, count($lines) * $lineHeight + 72);
    // Centre embedded captions roughly one quarter of the frame up from the
    // bottom. This avoids the caption/CTA/navigation stack commonly drawn by
    // vertical-video platforms along the bottom edge.
    $panelCentreY = (int)round(1920 * 0.73);
    $panelTop = max((int)round(1920 * 0.60), $panelCentreY - (int)round($panelH / 2));
    $panel = imagecolorallocatealpha($canvas, 0, 0, 0, 38);
    imagefilledrectangle($canvas, 54, $panelTop, 1026, $panelTop + $panelH, $panel);
    $white = imagecolorallocate($canvas, 255, 255, 255);
    $shadow = imagecolorallocatealpha($canvas, 0, 0, 0, 35);
    foreach ($lines as $index => $line) {
        $y = $panelTop + 58 + ($index * $lineHeight);
        if ($font) {
            $box = imagettfbbox(38, 0, $font, $line);
            $w = abs(($box[2] ?? 0) - ($box[0] ?? 0));
            $x = max(74, (int)((1080 - $w) / 2));
            imagettftext($canvas, 38, 0, $x + 2, $y + 2, $shadow, $font, $line);
            imagettftext($canvas, 38, 0, $x, $y, $white, $font, $line);
        } else {
            imagestring($canvas, 5, 90, $y - 20, $line, $white);
        }
    }
    if (!imagejpeg($canvas, $dest, 91)) throw new RuntimeException('A slideshow frame could not be written.');
}

function wt_srt_time(float $seconds): string
{
    $ms = (int)round($seconds * 1000);
    $h = intdiv($ms, 3600000); $ms %= 3600000;
    $m = intdiv($ms, 60000); $ms %= 60000;
    $s = intdiv($ms, 1000); $ms %= 1000;
    return sprintf('%02d:%02d:%02d,%03d', $h, $m, $s, $ms);
}

function wt_run_command(array $parts, string $errorLabel): void
{
    $command = implode(' ', array_map('escapeshellarg', $parts)) . ' 2>&1';
    $output = []; $code = 1;
    exec($command, $output, $code);
    if ($code !== 0) throw new RuntimeException($errorLabel . ': ' . implode("\n", array_slice($output, -12)));
}

function wt_openai_tts(string $text, string $voice, string $dest, string $accent = 'australian'): void
{
    $key = trim((string)wt_env('OPENAI_API_KEY', ''));
    if ($key === '') throw new RuntimeException('OPENAI_API_KEY is required for voice-over.');
    $payload = json_encode([
        'model' => wt_env('OPENAI_TTS_MODEL', 'gpt-4o-mini-tts'),
        'voice' => $voice,
        'input' => $text,
        'instructions' => wt_social_voice_accent_instruction($accent) . ' ' . wt_env('WORKTRACKER_SOCIAL_VOICE_STYLE', 'Speak like an enthusiastic, warm and confident home-improvement storyteller revealing a satisfying real-job transformation. Sound genuinely excited and proud, with lively pacing, expressive emphasis, natural rises and falls, and a friendly smile in the voice. Build curiosity and momentum without shouting, sounding fake, rushing, or becoming a hard-sell announcer. Give important before-and-after contrasts and homeowner benefits extra emphasis.'),
        'response_format' => 'mp3',
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $ch = curl_init('https://api.openai.com/v1/audio/speech');
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload, CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 180, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $key, 'Content-Type: application/json'
        ]]);
    $body = curl_exec($ch); $error = curl_error($ch); $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($body === false || $status < 200 || $status >= 300) throw new RuntimeException($error ?: 'Voice-over request failed with HTTP ' . $status);
    if (file_put_contents($dest, $body) === false) throw new RuntimeException('Voice-over audio could not be saved.');
}
