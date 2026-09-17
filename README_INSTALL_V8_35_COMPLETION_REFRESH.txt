MIKE OF ALL TRADES — V8.35 REEL COMPLETION REFRESH
==================================================

Problem
-------
When live polling detected that a reel had completed or failed, the page tried
to navigate to the exact URL that was already open. Chrome could treat that as
no navigation, leaving the status changed to complete but without the newly
server-rendered player, download link, SRT link or failure details.

Fix
---
The terminal status transition now performs a genuine page reload. The refreshed
server HTML immediately contains the completed reel player and links, or the
full failure message.

AI voice-over is now the default for new slideshow plans. The selector still
offers "No voice-over" whenever a silent reel is preferred.

File changed
------------
admin/work/social_video_builder.php

No database migration is required.
