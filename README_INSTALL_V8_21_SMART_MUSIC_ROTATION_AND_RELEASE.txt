MIKE OF ALL TRADES — V8.21 SMART MUSIC ROTATION + RELEASE
=========================================================

This is cumulative for V8.19, V8.20 and V8.21. Installing it is safe whether
or not V8.19/V8.20 were already installed.

CHANGES
-------
- Keeps slideshow duration matching as the first music priority.
- Treats near-identical track lengths as an equally suitable pool.
- Chooses the least-used suitable song from the last 100 completed videos.
- When usage is tied, chooses the song that has gone longest without use.
- Records the chosen filename so future rotations remain reliable.
- Includes V8.19 duration display, trimming and clean music fade-out.
- Includes V8.20 Manage Job icon, toggle and SMS-policy improvements.

LOCAL INSTALL
-------------
cd /Applications/XAMPP/xamppfiles/htdocs/mike_of_all_trades

PATCH_ZIP="/Users/macbook/Downloads/mike-work-tracker-v8_21-smart-rotation-release-20260917.zip"
PATCH_TEMP="$(mktemp -d /tmp/mot_v8_21.XXXXXX)"
PATCH_BACKUP="backups/v8_21_before_$(date +%Y%m%d_%H%M%S)"

unzip -q "$PATCH_ZIP" -d "$PATCH_TEMP"

mkdir -p \
    "$PATCH_BACKUP/includes" \
    "$PATCH_BACKUP/admin/work" \
    "$PATCH_BACKUP/api/work" \
    "$PATCH_BACKUP/tools"

cp includes/work_social_video.php "$PATCH_BACKUP/includes/"
cp admin/work/social_video_builder.php admin/work/manage_job.php "$PATCH_BACKUP/admin/work/"
cp api/work/quick_action.php api/work/save_update_mode.php "$PATCH_BACKUP/api/work/"
cp tools/process_social_video_queue.php "$PATCH_BACKUP/tools/"

cp "$PATCH_TEMP/includes/work_social_video.php" includes/
cp "$PATCH_TEMP/admin/work/social_video_builder.php" "$PATCH_TEMP/admin/work/manage_job.php" admin/work/
cp "$PATCH_TEMP/api/work/quick_action.php" "$PATCH_TEMP/api/work/save_update_mode.php" api/work/
cp "$PATCH_TEMP/tools/process_social_video_queue.php" tools/
cp "$PATCH_TEMP/README_INSTALL_V8_21_SMART_MUSIC_ROTATION_AND_RELEASE.txt" .

echo
echo "V8.21 INSTALLED"
echo "Backup saved at: $PATCH_BACKUP"

LOCAL LINT
----------
PHP_BIN="/Applications/XAMPP/xamppfiles/bin/php"

for FILE in \
    includes/work_social_video.php \
    admin/work/social_video_builder.php \
    admin/work/manage_job.php \
    api/work/quick_action.php \
    api/work/save_update_mode.php \
    tools/process_social_video_queue.php
do
    "$PHP_BIN" -l "$FILE"
done

git diff --check

GIT RELEASE — SAFE EXACT FILES
------------------------------
These commands deliberately do not add storage/, backups/, secrets, uploaded
photos, uploaded songs or every untracked file.

git add \
    includes/work_media.php \
    includes/work_social_video.php \
    admin/work/import_customer_job.php \
    admin/work/manage_job.php \
    admin/work/materials.php \
    admin/work/social_drafts.php \
    admin/work/social_video_admin_view.php \
    admin/work/social_video_builder.php \
    admin/work/task_photo_admin_view.php \
    admin/work/task_photo_inline_panel.php \
    admin/work/task_photos.php \
    api/work/bulk_upload_task_photos.php \
    api/work/generate_social_draft.php \
    api/work/generate_social_video_plan.php \
    api/work/queue_social_video.php \
    api/work/quick_action.php \
    api/work/save_update_mode.php \
    api/work/social_video_status.php \
    api/work/update_social_photo_posted.php \
    api/work/upload_social_music.php \
    api/work/upload_task_photo.php \
    api/work/upload_task_photo_admin.php \
    api/work/upload_task_photo_inline_admin.php \
    work/task_photo.php \
    assets/logos/mike_of_all_trades_logo_wireframe.png \
    tools/backfill_v8_14_task_photo_thumbnails.php \
    tools/cleanup_expired_social_videos.php \
    tools/cleanup_expired_task_photos.php \
    tools/migration_v8_12n_platform_social_photos.sql \
    tools/migration_v8_13_social_video.sql \
    tools/migration_v8_14_media_retention.sql \
    tools/process_social_video_queue.php \
    tools/regenerate_v8_12n_social_variants.php \
    README_INSTALL_V8_13_SOCIAL_VIDEO.txt \
    README_INSTALL_V8_14_MEDIA_HARDENING.txt \
    README_INSTALL_V8_15_SOCIAL_STORYTELLING.txt \
    README_INSTALL_V8_16_LARGE_SLIDESHOWS.txt \
    README_INSTALL_V8_17_VISUAL_STORYTELLING.txt \
    README_INSTALL_V8_18_VISUAL_WORKER_FIX.txt \
    README_INSTALL_V8_19_SMART_MUSIC.txt \
    README_INSTALL_V8_20_QUICK_ACTION_DASHBOARD.txt \
    README_INSTALL_V8_21_SMART_MUSIC_ROTATION_AND_RELEASE.txt

git diff --cached --check
git diff --cached --stat
git status --short

Review the staged list. It must not contain config/secrets, storage/, backups/
or private customer uploads. Then:

git commit -m "Release Work Tracker social media and quick action updates v8.21"
git push origin main

LIVE LIGHTSAIL DEPLOYMENT
-------------------------
Connect to Lightsail, then:

cd /var/www/html

git status --short

Do not pull over unexpected tracked live edits. If there are no unexpected
tracked modifications:

git pull --ff-only origin main

PHP_BIN="$(command -v php)"

for FILE in \
    includes/work_media.php \
    includes/work_social_video.php \
    admin/work/manage_job.php \
    admin/work/social_drafts.php \
    admin/work/social_video_builder.php \
    api/work/quick_action.php \
    api/work/generate_social_video_plan.php \
    api/work/queue_social_video.php \
    tools/process_social_video_queue.php
do
    "$PHP_BIN" -l "$FILE" || exit 1
done

MYSQL_BIN="$(command -v mysql)"
"$MYSQL_BIN" -u root mike_of_all_trades < tools/migration_v8_12n_platform_social_photos.sql
"$MYSQL_BIN" -u root mike_of_all_trades < tools/migration_v8_13_social_video.sql
"$MYSQL_BIN" -u root mike_of_all_trades < tools/migration_v8_14_media_retention.sql

command -v ffmpeg
command -v ffprobe

If either FFmpeg command is missing on the Ubuntu Lightsail server:

sudo apt-get update
sudo apt-get install -y ffmpeg

Identify the non-root Apache worker user before setting storage ownership:

WEB_USER="$(ps -eo user,comm | awk '$2 ~ /^(apache2|httpd)$/ && $1 != "root" {print $1; exit}')"
echo "Apache worker user: $WEB_USER"
test -n "$WEB_USER" || { echo "STOP: Apache worker user was not detected"; exit 1; }

sudo mkdir -p storage/private/social_music storage/private/social_videos storage/logs
sudo chown -R "$WEB_USER":"$WEB_USER" storage/private/social_music storage/private/social_videos storage/logs
sudo chmod -R u+rwX,g+rwX storage/private/social_music storage/private/social_videos storage/logs

Run one queue check as the web user:

sudo -u "$WEB_USER" env PATH="/usr/local/bin:/usr/bin:/bin" \
    "$PHP_BIN" tools/process_social_video_queue.php 1

LIVE BROWSER CHECK
------------------
1. Manage Job: test toggles and SMS policy using your own phone/test job.
2. Social drafts: confirm HEIC upload and platform variants on a fresh image.
3. Slideshow builder: confirm "Smart rotation — length + variety" appears.
4. Generate two similarly sized test videos and confirm different suitable songs.
5. Confirm rendered video, audio, fade, captions and download links.

IMPORTANT
---------
- Uploaded local music files are deliberately not committed to Git.
- Upload licensed music separately on the live slideshow builder.
- This release does not copy your local private photos to Lightsail.
- Configure the queue worker and retention cleanup cron on Lightsail after the
  first successful manual render if they are not already configured.
