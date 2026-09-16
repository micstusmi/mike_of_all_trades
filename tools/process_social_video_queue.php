<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only.\n"); }
require_once __DIR__ . '/../includes/work_tracker.php';
require_once __DIR__ . '/../includes/work_social_video.php';

$limit = max(1, min(10, (int)($argv[1] ?? 1)));
$ffmpeg = wt_social_ffmpeg();
if (!$ffmpeg) { fwrite(STDERR, "FFmpeg was not found. Set WORKTRACKER_FFMPEG_PATH.\n"); exit(1); }

function svq_remove_tree(string $dir): void {
    if (!is_dir($dir)) return;
    foreach (scandir($dir) ?: [] as $name) {
        if ($name === '.' || $name === '..') continue;
        $path = $dir . '/' . $name;
        is_dir($path) ? svq_remove_tree($path) : @unlink($path);
    }
    @rmdir($dir);
}

for ($run = 0; $run < $limit; $run++) {
    $pdo->beginTransaction();
    $q = $pdo->query("SELECT * FROM work_social_videos WHERE status='queued' ORDER BY id LIMIT 1 FOR UPDATE");
    $video = $q->fetch(PDO::FETCH_ASSOC);
    if (!$video) { $pdo->commit(); if ($run === 0) echo "No queued social videos.\n"; break; }
    $videoId = (int)$video['id'];
    $pdo->prepare("UPDATE work_social_videos SET status='processing',started_at=NOW(),error_message=NULL WHERE id=?")->execute([$videoId]);
    $pdo->commit();
    $temp = sys_get_temp_dir() . '/mot_social_video_' . $videoId . '_' . bin2hex(random_bytes(4));
    try {
        if (!mkdir($temp, 0770, true) && !is_dir($temp)) throw new RuntimeException('Temporary render folder could not be created.');
        $itemsStmt = $pdo->prepare("SELECT i.*,p.relative_path,p.mime_type,p.photo_type FROM work_social_video_items i JOIN work_task_photos p ON p.id=i.photo_id WHERE i.video_id=? ORDER BY i.sort_order,i.id");
        $itemsStmt->execute([$videoId]); $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($items) < 2) throw new RuntimeException('At least two usable slideshow items are required.');
        $concat = ''; $srt = ''; $narration = []; $clock = 0.0;
        foreach ($items as $index => $item) {
            $source = wt_task_photo_path((string)$item['relative_path']);
            if (!is_file($source)) throw new RuntimeException('Source photo #' . (int)$item['photo_id'] . ' is missing.');
            $frame = $temp . '/frame_' . sprintf('%03d', $index + 1) . '.jpg';
            wt_social_video_frame($source, (string)$item['mime_type'], (string)$item['photo_type'], (string)$item['screen_caption'], $frame);
            $duration = max(1.0, min(12.0, (float)$item['duration_seconds']));
            $safeFrame = str_replace("'", "'\\''", $frame);
            $concat .= "file '" . $safeFrame . "'\n" . 'duration ' . number_format($duration, 3, '.', '') . "\n";
            $start = $clock; $clock += $duration;
            $srt .= ($index + 1) . "\n" . wt_srt_time($start) . ' --> ' . wt_srt_time($clock) . "\n" . trim((string)$item['screen_caption']) . "\n\n";
            $spoken = trim((string)($item['narration_text'] ?? ''));
            if ($spoken !== '') $narration[] = $spoken;
        }
        $lastFrame = $temp . '/frame_' . sprintf('%03d', count($items)) . '.jpg';
        $concat .= "file '" . str_replace("'", "'\\''", $lastFrame) . "'\n";
        $concatPath = $temp . '/slides.txt'; file_put_contents($concatPath, $concat);
        $silent = $temp . '/silent.mp4';
        wt_run_command([$ffmpeg,'-y','-f','concat','-safe','0','-i',$concatPath,'-vf','fps=30,format=yuv420p','-c:v','libx264','-preset','medium','-crf','20','-movflags','+faststart',$silent], 'Video rendering failed');

        $voicePath = null;
        $voiceTempo = 1.0;
        if (!empty($video['voiceover_enabled']) && $narration) {
            $voicePath = $temp . '/voice.mp3';
            wt_openai_tts(implode("\n\n", $narration), (string)$video['voice_name'], $voicePath);
            $voiceDuration = wt_social_media_duration($voicePath);
            if ($voiceDuration !== null && $voiceDuration > $clock) {
                $voiceTempo = $voiceDuration / $clock;
                if ($voiceTempo > 1.35) {
                    throw new RuntimeException('The narration is too long for the approved slide timing. Shorten the narration or increase slide durations.');
                }
            }
        }
        $musicPath = null; $musicChoice = (string)($video['music_file'] ?? ''); $musicFiles = wt_social_music_files();
        if ($musicChoice === '__auto__' && $musicFiles) {
            $recentMusicStmt = $pdo->prepare("
                SELECT music_file
                FROM work_social_videos
                WHERE id<>?
                  AND status='complete'
                  AND music_file IS NOT NULL
                  AND music_file<>''
                  AND music_file<>'__auto__'
                ORDER BY completed_at DESC,id DESC
                LIMIT 100
            ");
            $recentMusicStmt->execute([$videoId]);
            $recentMusic = array_map('strval', $recentMusicStmt->fetchAll(PDO::FETCH_COLUMN));
            $chosenTrack = wt_social_choose_music($clock, $recentMusic);
            $musicChoice = (string)($chosenTrack['name'] ?? '');
            if ($musicChoice !== '') {
                $pdo->prepare("UPDATE work_social_videos SET music_file=? WHERE id=?")->execute([$musicChoice,$videoId]);
            }
        }
        if ($musicChoice !== '' && in_array($musicChoice, $musicFiles, true)) $musicPath = wt_social_music_dir() . '/' . $musicChoice;

        $relativeDir = 'job_' . (int)$video['job_id']; $outDir = wt_social_video_path($relativeDir);
        if (!is_dir($outDir) && !mkdir($outDir,0770,true) && !is_dir($outDir)) throw new RuntimeException('Social video output folder could not be created.');
        $relativeVideo = $relativeDir . '/social_video_' . $videoId . '.mp4'; $final = wt_social_video_path($relativeVideo);
        $tempoFilter = 'atempo=' . number_format($voiceTempo, 4, '.', '') . ',volume=1.0';
        $musicFadeSeconds = min(2.0, max(0.5, $clock / 10));
        $musicFadeStart = max(0.0, $clock - $musicFadeSeconds);
        $musicFilter = 'volume=' . ($voicePath ? '0.10' : '0.13')
            . ',afade=t=out:st=' . number_format($musicFadeStart, 3, '.', '')
            . ':d=' . number_format($musicFadeSeconds, 3, '.', '');
        if ($voicePath && $musicPath) {
            wt_run_command([$ffmpeg,'-y','-i',$silent,'-i',$voicePath,'-stream_loop','-1','-i',$musicPath,'-filter_complex','[1:a]'.$tempoFilter.'[voice];[2:a]'.$musicFilter.'[music];[voice][music]amix=inputs=2:duration=longest:dropout_transition=2[a]','-map','0:v:0','-map','[a]','-c:v','copy','-c:a','aac','-b:a','160k','-t',number_format($clock,3,'.',''),'-movflags','+faststart',$final], 'Voice/music mixing failed');
        } elseif ($voicePath) {
            wt_run_command([$ffmpeg,'-y','-i',$silent,'-i',$voicePath,'-filter_complex','[1:a]'.$tempoFilter.'[voice]','-map','0:v:0','-map','[voice]','-c:v','copy','-c:a','aac','-b:a','160k','-t',number_format($clock,3,'.',''),'-movflags','+faststart',$final], 'Voice-over mixing failed');
        } elseif ($musicPath) {
            wt_run_command([$ffmpeg,'-y','-i',$silent,'-stream_loop','-1','-i',$musicPath,'-filter_complex','[1:a]'.$musicFilter.'[a]','-map','0:v:0','-map','[a]','-c:v','copy','-c:a','aac','-b:a','160k','-t',number_format($clock,3,'.',''),'-movflags','+faststart',$final], 'Music mixing failed');
        } else {
            if (!rename($silent,$final)) throw new RuntimeException('Rendered video could not be moved into storage.');
        }
        $relativeSrt = $relativeDir . '/social_video_' . $videoId . '.srt';
        if (file_put_contents(wt_social_video_path($relativeSrt), $srt) === false) throw new RuntimeException('Caption file could not be saved.');
        $expires = wt_task_photo_expiry_date(wt_task_photo_retention_months('video'));
        $pdo->prepare("UPDATE work_social_videos SET status='complete',output_relative_path=?,subtitle_relative_path=?,duration_seconds=?,completed_at=NOW(),expires_at=? WHERE id=?")->execute([$relativeVideo,$relativeSrt,$clock,$expires,$videoId]);
        echo "Rendered social video #{$videoId} (" . number_format($clock,1) . " seconds)"
            . ($musicPath ? " with music: {$musicChoice}" : '') . ".\n";
    } catch (Throwable $e) {
        $pdo->prepare("UPDATE work_social_videos SET status='failed',error_message=?,completed_at=NOW() WHERE id=?")->execute([mb_substr($e->getMessage(),0,6000),$videoId]);
        fwrite(STDERR, "Video #{$videoId} failed: {$e->getMessage()}\n");
    } finally { svq_remove_tree($temp); }
}
