MIKE OF ALL TRADES — V8.14 MEDIA RETENTION + IPHONE SAVE
========================================================

What this patch fixes
---------------------
* Keeps a small permanent thumbnail and the photo database record/reference.
* Deletes expired legacy, Instagram, TikTok and Facebook social copies.
* Expires completed slideshow MP4/SRT files after 3 months by default.
* Keeps source photos for 12 months and social images for 6 months by default.
* Adds explicit source downloads and real file-sharing to the iPhone share sheet.
* Keeps AI slideshow generation manual. No AI request occurs merely by viewing a job.

Retention can be overridden with environment values:
WORKTRACKER_PHOTO_RETENTION_MONTHS=12
WORKTRACKER_SOCIAL_RETENTION_MONTHS=6
WORKTRACKER_VIDEO_RETENTION_MONTHS=3

IMPORTANT
---------
Install V8.13 first. Apply migration_v8_14_media_retention.sql before testing
new uploads because the PHP code expects the new thumbnail columns.

The browser cannot silently write into Apple Photos. On iPhone, tap
"Save to iPhone", then choose "Save Image" in Apple's share sheet.

The cleanup scripts only delete files when run. Configure cron on Lightsail:
17 3 * * * /usr/bin/php /var/www/html/tools/cleanup_expired_task_photos.php >> /var/log/mot-media-cleanup.log 2>&1
27 3 * * * /usr/bin/php /var/www/html/tools/cleanup_expired_social_videos.php >> /var/log/mot-media-cleanup.log 2>&1
