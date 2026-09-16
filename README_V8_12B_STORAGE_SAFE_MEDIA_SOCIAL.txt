Mike of All Trades Work Tracker V8.12B

Storage-safe photo/social patch.

What changed:
- Task/job photos are saved as reduced web copies, not full-size phone originals.
- Default web copy size is max 1600px on the longest side.
- Branded social copies are generated separately with BEFORE / IN PROGRESS / AFTER and logo watermark.
- Original uploaded filename, original upload size, job, task, note, stored filename and hashes stay in the database.
- Server files get expiry metadata so old files can be cleaned up while the job/photo record remains.
- Social drafts can be generated per job using OPENAI_API_KEY.

Recommended archive workflow:
- Keep full-size originals in iPhone Photos / Google Photos.
- Use the website for working copies, customer/job evidence, and social copies.
- Run tools/cleanup_expired_task_photos.php periodically once you are comfortable with the retention window.

Cleanup:
php tools/cleanup_expired_task_photos.php --dry-run
php tools/cleanup_expired_task_photos.php

The cleanup deletes expired server files only. It leaves the database record behind so you can cross-reference the job, task, original filename and notes later.
