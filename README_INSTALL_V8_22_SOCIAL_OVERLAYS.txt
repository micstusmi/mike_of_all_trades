MIKE OF ALL TRADES — V8.22 SOCIAL OVERLAY FIX
================================================

Purpose
-------
- Makes Instagram, TikTok and Facebook variants full-bleed.
- Keeps the stage label inside each platform's safe area.
- Raises the watermark away from platform controls and captions.
- Improves watermark visibility on pale photographs.
- Includes the Linux HEIC conversion fallback added after live testing.

Files
-----
includes/work_media.php

Install locally
---------------
cd /Applications/XAMPP/xamppfiles/htdocs/mike_of_all_trades

PATCH_ZIP="/Users/macbook/Downloads/mike-work-tracker-v8_22-social-overlays-20260916.zip"
PATCH_TEMP="$(mktemp -d /tmp/mot_v8_22.XXXXXX)"
PATCH_BACKUP="backups/v8_22_before_$(date +%Y%m%d_%H%M%S)"

unzip -q "$PATCH_ZIP" -d "$PATCH_TEMP"
mkdir -p "$PATCH_BACKUP/includes"
cp includes/work_media.php "$PATCH_BACKUP/includes/work_media.php"
cp "$PATCH_TEMP/includes/work_media.php" includes/work_media.php
cp "$PATCH_TEMP/README_INSTALL_V8_22_SOCIAL_OVERLAYS.txt" .

/Applications/XAMPP/xamppfiles/bin/php -l includes/work_media.php
git diff --check

git add includes/work_media.php README_INSTALL_V8_22_SOCIAL_OVERLAYS.txt
git commit -m "Fix social photo crops and overlay safe areas v8.22"
git push origin main

Deploy on Lightsail
-------------------

cd /var/www/html
git status --short
git pull --ff-only origin main
/usr/bin/php -l includes/work_media.php

Regenerate the affected job as the web-server user:

sudo -u www-data /usr/bin/php tools/regenerate_v8_12n_social_variants.php 6

Then hard-refresh Social Drafts (Command + Shift + R).
