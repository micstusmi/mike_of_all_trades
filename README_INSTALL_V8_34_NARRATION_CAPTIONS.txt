MIKE OF ALL TRADES — V8.34 NARRATION CLOSED CAPTIONS
====================================================

Problem
-------
Voiceover reels used the short photographic overlay headlines as their SRT
closed captions. Those headlines are deliberately different from the richer
AI narration, so the closed captions did not represent the spoken voiceover.

Fix
---
- Photo overlays remain short, punchy visual headlines.
- Voiceover SRT captions now contain the actual narration words.
- Narration is divided into short readable caption chunks.
- Caption timing is distributed across the measured narration duration.
- Reels without voiceover continue using their photo-overlay text in the SRT.
- Long FFmpeg encoding and audio-mixing stages now refresh the worker heartbeat
  every 10 seconds, preventing false three-minute "render may be stuck" alerts.
- H.264 encoding now uses the veryfast preset instead of medium. Resolution
  remains 1080x1920, frame rate remains 30 fps and CRF remains 20. This reduces
  CPU time and memory pressure on the 512 MB Lightsail instance, with a modest
  possible increase in MP4 size and little expected visual difference for a
  photographic slideshow.

File changed
------------
tools/process_social_video_queue.php

No database migration is required. Existing reels are not rewritten. Queue a
new voiceover reel to produce narration-based closed captions.

After installation run:

php -l tools/process_social_video_queue.php
