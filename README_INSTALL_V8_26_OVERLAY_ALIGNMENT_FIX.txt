V8.26 - SOCIAL PHOTO OVERLAY ALIGNMENT FIX
===========================================

WHAT THIS FIXES

- The stage label is centred wholly inside the top of the final cropped photo.
- The transparent wireframe face/logo is anchored wholly inside the bottom-right.
- Overlay coordinates are calculated from the finished image, never the card.
- Regeneration can no longer use an old branded/platform derivative as its source.
  That was capable of baking old white padding and misplaced overlays into a new image.
- No dedicated Social Media Test Job/Sandbox is required. Any existing dummy job is fine.

INSTALL ON LIGHTSAIL

From /var/www/html after copying/replacing the supplied files:

  php -l includes/work_media.php
  php -l tools/regenerate_v8_12n_social_variants.php
  php -l admin/work/social_drafts.php

Regenerate one existing dummy job first (replace 6 if using another job ID):

  php tools/regenerate_v8_12n_social_variants.php 6 --verbose

Then hard-refresh its Social drafts page. Existing generated JPEGs must be
regenerated because changing PHP cannot move overlays already baked into them.

When satisfied, regenerate all jobs that still have their clean stored sources:

  php tools/regenerate_v8_12n_social_variants.php --verbose

Old records whose clean source file has expired are safely skipped. Their current
variants are not overwritten from an already-branded image.
