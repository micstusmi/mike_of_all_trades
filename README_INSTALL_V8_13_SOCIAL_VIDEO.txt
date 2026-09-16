MIKE OF ALL TRADES — V8.13 SOCIAL SLIDESHOW VIDEO PATCH
=======================================================

This combined continuation patch includes:

- The latest Manage Job quick-action icons.
- Coffee/meal on/off toggles and automatic customer SMS updates.
- HEIC/social-image/logo-safe-area changes from the current patched snapshot.
- A social slideshow builder for TikTok, Instagram Reels and YouTube Shorts.
- Editable AI title, post caption, hashtags, per-slide screen captions and
  optional narration.
- 1080x1920 vertical MP4 output.
- Burned-in visible captions plus downloadable SRT closed captions.
- Optional OpenAI text-to-speech narration.
- Licensed music upload/library with automatic track rotation.
- A queued CLI renderer so long videos do not time out in the browser.

No customer name, phone or exact address is sent into public social copy.

IMPORTANT MUSIC NOTE
--------------------

The system rotates music that you upload and confirm you have permission to
use. It does not pretend copyrighted tracks are unique AI compositions. A
future music-generation service can be connected without changing video data.

DEPENDENCIES
------------

- PHP extensions already used by Work Tracker: PDO, cURL, fileinfo, GD.
- FFmpeg available to the PHP CLI/cron user.
- OPENAI_API_KEY for AI planning and optional voice-over.

Optional configuration values:

  WORKTRACKER_FFMPEG_PATH=/absolute/path/to/ffmpeg
  WORKTRACKER_FFPROBE_PATH=/absolute/path/to/ffprobe
  WORKTRACKER_SOCIAL_VIDEO_AI_MODEL=gpt-4.1-mini
  OPENAI_TTS_MODEL=gpt-4o-mini-tts

VOICE-OVER DISCLOSURE
---------------------

When voice-over is enabled, the queued post caption automatically adds:

  Voice-over generated with AI.

INSTALLATION
------------

1. Overlay the ZIP from the project root.
2. Run tools/migration_v8_13_social_video.sql once.
3. PHP-lint the changed files.
4. Install/check FFmpeg.
5. Run the worker manually for testing.
6. Add the worker to cron on the live Lightsail server.

LOCAL WORKER TEST
-----------------

  /Applications/XAMPP/xamppfiles/bin/php tools/process_social_video_queue.php 1

The final number is the maximum queued videos processed in that run.

LIVE CRON EXAMPLE
-----------------

Run every minute from the account that can read the website configuration and
write to storage/private:

  * * * * * cd /var/www/html && /usr/bin/php tools/process_social_video_queue.php 1 >> /var/www/html/storage/social_video_worker.log 2>&1

Create the log first and make it writable by the chosen cron user. Do not run
the worker concurrently from multiple cron entries.

WORKFLOW
--------

1. Open a job's Social drafts page.
2. Select Create slideshow / Reel / Short.
3. Choose platform, photos, timing, optional AI voice and licensed music.
4. Generate the AI plan.
5. Review/edit every caption, title and narration.
6. Approve and queue.
7. Run/wait for the worker.
8. Download MP4, SRT and copy the stored suggested post text.

TIMING
------

- No voice: 1.5, 1.8 or 2.0 seconds per image.
- Voice enabled: AI proposes roughly 3–7 seconds per image so narration fits.
- Queue validation prevents videos much beyond approximately 90 seconds.
