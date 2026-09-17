MIKE OF ALL TRADES — V8.33 SLIDESHOW BUILDER SHORTCUTS
======================================================

Changes
-------
- Adds Generate AI slideshow plan beside the top Select all photos controls.
- Keeps the existing generate button below the photo grid.
- Both generate buttons run the same action and show the same loading state.
- When music already exists, the music button says Upload more music.
- An empty music library continues to show Upload music.

File changed
------------
admin/work/social_video_builder.php

This update has no database migration.

After installation, run:

php -l admin/work/social_video_builder.php

Then open a job with many photos and confirm:
1. Select all photos can be pressed near the top.
2. Generate AI slideshow plan can be pressed without scrolling down.
3. Both generate buttons temporarily display the generating state.
4. The music button reads Upload more music when tracks already exist.
