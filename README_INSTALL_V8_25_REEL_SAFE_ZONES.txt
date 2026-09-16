MIKE OF ALL TRADES — V8.25 REEL SAFE ZONES
===========================================

Separates vertical-reel overlay placement from static social-photo placement.

Static carousel photos remain unchanged:
- Stage label centred near the top.
- Transparent wireframe logo at bottom-right.

Vertical slideshow reels now use a safer interior layout:
- Stage label around the upper quarter, below platform header controls.
- Embedded captions centred around the lower-quarter line, above bottom UI.
- Wireframe watermark at middle-left, away from captions and right-side controls.
- Original stored source images remain untouched.

Files
-----
includes/work_social_video.php
includes/work_media.php

Existing completed videos are unchanged. Queue a new video to use the new reel
layout. An abandoned/failed video may be reset and rendered again after deploy.

