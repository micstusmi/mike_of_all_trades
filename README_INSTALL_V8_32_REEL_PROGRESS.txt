MIKE OF ALL TRADES — V8.32 REEL PROGRESS TRACKING
==================================================

WHAT THIS FIXES
- Shows the current render stage instead of only "processing".
- Shows percentage, elapsed time, rough remaining time and last worker update.
- Shows photo-by-photo preparation progress.
- Warns when a queued/processing reel has had no worker activity for 3 minutes.
- Reliably reloads when a reel completes or fails, revealing the player,
  download link, captions and error message.
- Rendering remains server-side. You can leave the page and return later.

FILES IN THIS PATCH
admin/work/social_video_builder.php
api/work/social_video_status.php
tools/process_social_video_queue.php
tools/migration_v8_32_social_video_progress.sql

LIGHTSAIL INSTALL
1. Upload and unzip this patch over /var/www/html, preserving folders.

2. From /var/www/html run the database migration once:

   mysql -u YOUR_DB_USER -p YOUR_DB_NAME < tools/migration_v8_32_social_video_progress.sql

   Use the same database username and database name already configured for
   the website. Do not run the migration twice.

3. Check PHP syntax:

   php -l admin/work/social_video_builder.php
   php -l api/work/social_video_status.php
   php -l tools/process_social_video_queue.php

4. Queue a NEW test reel. Old completed/failed reels still display normally,
   but only newly rendered reels will contain detailed progress telemetry.

EXPECTED PROGRESS STAGES
Waiting for renderer -> Preparing photo X of Y -> Generating AI voice-over
-> Timing slides and closed captions -> Encoding the slideshow video
-> Selecting and mixing voice-over and music -> Saving files -> Video is ready

IF A REEL WARNS THAT IT MAY BE STUCK
Run:

   sudo tail -80 /tmp/mot_social_video_worker.log

Also confirm the worker process and FFmpeg are available:

   pgrep -af process_social_video_queue.php
   command -v ffmpeg
   command -v ffprobe

The warning does not delete or abort the reel. It gives enough information to
decide whether to wait or investigate without blindly refreshing the page.
