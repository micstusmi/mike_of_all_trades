MIKE OF ALL TRADES — V8.31 ASYNCHRONOUS PHOTO UPLOAD QUEUE

What changes
------------
The Catch-up bulk photo uploader no longer sends the whole batch through one
large, long-running PHP request.

1. Each original is uploaded separately, avoiding the combined batch-size
   failure that occurred around 10 or 11 iPhone photos.
2. The page shows exact original-transfer progress and retries an individual
   failed transfer up to two additional times.
3. Idempotent file keys prevent a network retry from creating a duplicate.
4. Once every original reaches Lightsail, the page clearly says it is safe to
   leave.
5. HEIC/JPEG conversion, resizing, assignment, thumbnails, branded images,
   platform versions and social-draft seeding then run in a background worker.
6. Returning to the Task Photos page resumes status monitoring for the current
   batch.
7. The worker is locked so only one copy processes the queue, retries a failed
   processing attempt once, and recovers work interrupted for over 20 minutes.

Important browser boundary
--------------------------
The upload tab must remain open while the original bytes are travelling from
the device to Lightsail. Navigating away at that stage would remove the browser's
access to the selected local files. After the green "Originals safely saved"
message appears, the tab may be closed and all remaining work continues on the
server.

Database
--------
The work_photo_upload_queue table is created automatically on first use. The
matching SQL migration is included for reference and manual administration.

Files
-----
admin/work/task_photos.php
api/work/queue_task_photo_upload.php
api/work/task_photo_upload_status.php
includes/work_photo_queue.php
tools/process_photo_upload_queue.php
tools/migration_v8_31_async_photo_queue.sql

Scope
-----
This patch does not replace includes/work_media.php and does not alter the V8.30
slideshow voiceover renderer or the existing overlay/cropping implementation.
