MIKE OF ALL TRADES — V8.39 IPHONE SHARE ACTIVATION FIX
======================================================

V8.39 replaces the one-tap iPhone share attempt with a reliable two-step flow:

1. The first tap downloads and prepares the MP4.
2. The button changes to "Ready — tap to save".
3. The second tap opens the iOS share sheet immediately while iOS still
   recognises the user's tap. The user can then choose "Save Video".

This fixes the iOS rejection caused when a slow video download consumed the
temporary user-activation window before navigator.share was called.

If the current iPhone browser cannot share MP4 files, the page now explicitly
asks the user to open it in Safari rather than showing a misleading fallback.

FILE CHANGED
------------
admin/work/social_video_builder.php

No database migration or Apache restart is required.
