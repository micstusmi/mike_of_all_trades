MIKE OF ALL TRADES — V8.30 VOICEOVER TIMING FIX

Problem confirmed
-----------------
AI voiceover audio was generated after the slideshow timing had already been
fixed. When narration was more than 35% longer than the photo sequence, the
worker deliberately failed with:

The narration is too long for the approved slide timing.

Fix
---
The worker now:

1. prepares the slideshow frames;
2. generates and measures the actual AI narration;
3. proportionally lengthens the photo slides when narration needs more time;
4. rebuilds the SRT caption timings from those final slide durations;
5. renders the slideshow and mixes the natural-speed voiceover.

The old narration speed-up and 35% failure threshold have been removed.

Scope
-----
Only tools/process_social_video_queue.php is replaced. No photo crop, static
overlay, logo, task, or historical-image code is included in this patch.

Testing
-------
After deployment, leave failed video #2 as it is and queue a new slideshow with
AI voiceover. The new video should take longer than a non-voiceover slideshow
because its slide durations will match the narration.
