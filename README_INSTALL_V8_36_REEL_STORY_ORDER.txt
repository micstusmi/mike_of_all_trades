MIKE OF ALL TRADES — V8.36 REEL STORY ORDER AND MATCHING CAPTIONS
=================================================================

V8.36 changes newly generated slideshow plans in four ways:

1. Photos are automatically grouped in chronological stage order:
   BEFORE, then IN PROGRESS, then AFTER.
2. The AI narration explains each available stage using the job records and
   visible photo evidence.
3. For voice-over reels, each narrated scene's visible on-video caption is
   forced to exactly match the words supplied to the AI voice.
4. The final narrated scene includes an invitation to contact Mike of All
   Trades through the website or mobile phone.

The original selection order is preserved within each stage. Missing stages
are skipped rather than invented.

FILE CHANGED
------------
api/work/generate_social_video_plan.php

This affects new plans only. Existing videos, including video #7, are not
rewritten. No database migration or Apache restart is required.
