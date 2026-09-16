MIKE OF ALL TRADES — V8.19 SMART SLIDESHOW MUSIC
=================================================

WHAT THIS PATCH CHANGES
-----------------------
- Measures every uploaded music track with FFprobe.
- Shows each track length in the slideshow music dropdown.
- Makes "Automatically choose best duration" the default when music exists.
- Chooses the closest suitable track for the finished slideshow duration.
- Prefers a song long enough to avoid an audible loop when the choices are close.
- Trims the chosen song to the exact video duration and adds a clean fade-out.
- Records the actual chosen filename on the queued video for troubleshooting.

INSTALL
-------
cd /Applications/XAMPP/xamppfiles/htdocs/mike_of_all_trades

PATCH_ZIP="/Users/macbook/Downloads/mike-work-tracker-v8_19-smart-music-20260917.zip"
PATCH_TEMP="$(mktemp -d /tmp/mot_v8_19.XXXXXX)"
PATCH_BACKUP="backups/v8_19_before_$(date +%Y%m%d_%H%M%S)"

unzip -q "$PATCH_ZIP" -d "$PATCH_TEMP"

mkdir -p \
    "$PATCH_BACKUP/includes" \
    "$PATCH_BACKUP/admin/work" \
    "$PATCH_BACKUP/tools"

cp includes/work_social_video.php \
    "$PATCH_BACKUP/includes/work_social_video.php"

cp admin/work/social_video_builder.php \
    "$PATCH_BACKUP/admin/work/social_video_builder.php"

cp tools/process_social_video_queue.php \
    "$PATCH_BACKUP/tools/process_social_video_queue.php"

cp "$PATCH_TEMP/includes/work_social_video.php" \
    includes/work_social_video.php

cp "$PATCH_TEMP/admin/work/social_video_builder.php" \
    admin/work/social_video_builder.php

cp "$PATCH_TEMP/tools/process_social_video_queue.php" \
    tools/process_social_video_queue.php

cp "$PATCH_TEMP/README_INSTALL_V8_19_SMART_MUSIC.txt" .

echo
echo "V8.19 INSTALLED"
echo "Backup saved at: $PATCH_BACKUP"

LINT
----
cd /Applications/XAMPP/xamppfiles/htdocs/mike_of_all_trades

PHP_BIN="/Applications/XAMPP/xamppfiles/bin/php"

"$PHP_BIN" -l includes/work_social_video.php
"$PHP_BIN" -l admin/work/social_video_builder.php
"$PHP_BIN" -l tools/process_social_video_queue.php

git diff --check

TEST THE ALREADY-QUEUED VIDEO #5
--------------------------------
First check what music choice video #5 saved:

MYSQL_BIN="/Applications/XAMPP/xamppfiles/bin/mysql"

"$MYSQL_BIN" -u root mike_of_all_trades -e "
SELECT id,status,music_file,title
FROM work_social_videos
WHERE id=5;
"

If music_file is blank, switch this queued test video to automatic music:

"$MYSQL_BIN" -u root mike_of_all_trades -e "
UPDATE work_social_videos
SET music_file='__auto__'
WHERE id=5 AND status='queued' AND (music_file IS NULL OR music_file='');
"

Render it as the same XAMPP daemon user that successfully rendered video #4:

sudo -u daemon env \
    PATH="/usr/local/bin:/usr/bin:/bin" \
    /Applications/XAMPP/xamppfiles/bin/php \
    tools/process_social_video_queue.php 1

The terminal output now names the selected song. Refresh the slideshow builder,
open/download video #5, and confirm that music is audible and fades cleanly.

SHORT BROWSER CHECKLIST
-----------------------
1. Refresh the Social slideshow builder.
2. Confirm music filenames now show durations such as 1:01, 1:13 and 1:40.
3. Confirm "Automatically choose best duration" is selected by default.
4. Queue a slideshow with automatic music.
5. Run the daemon worker if the local web server cannot start it automatically.
6. Confirm the selected song fits the video and fades during the last 2 seconds.

NOTES
-----
- Automatic selection can only choose among music you uploaded and are licensed
  to use commercially.
- A shorter song can still be looped when that is a much closer fit than every
  available longer song.
- With the current library, a 66.6-second video should select the 73-second song,
  not the 61-second or three-minute song.
