MIKE OF ALL TRADES — V8.24 REEL QUEUE VISIBILITY
==================================================

Fixes the confusing slideshow-builder flow where approving a reel refreshed the
page back to the top and made it appear that nothing had happened.

- Redirects directly to the newly queued video instead of the page top.
- Shows an unmistakable queued-success message and highlights the new video.
- Keeps checking queued/rendering status automatically.
- Reloads back to the same video when rendering completes or fails.
- Shows an inline player for the finished reel, plus Open, Download and SRT links.
- Adds a top-of-page shortcut to existing videos.
- Includes the V8.23 static-image overlay placement refinement.

Files
-----
admin/work/social_video_builder.php
includes/work_media.php

No database migration is required.

After deploying, hard-refresh the slideshow builder with Command + Shift + R.

If the earlier reel still seems missing, check job 6 on the live server:

cd /var/www/html
sudo -u www-data /usr/bin/php -r '
require "includes/work_tracker.php";
$q=$pdo->prepare("SELECT id,status,title,duration_seconds,error_message,created_at,completed_at FROM work_social_videos WHERE job_id=? ORDER BY id DESC LIMIT 10");
$q->execute([6]);
print_r($q->fetchAll(PDO::FETCH_ASSOC));
'
sudo tail -40 storage/logs/social_video_queue.log

