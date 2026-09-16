-- Mike of All Trades Work Tracker V8.14: durable thumbnails and complete retention tracking.
ALTER TABLE work_task_photos
  ADD COLUMN IF NOT EXISTS thumbnail_relative_path VARCHAR(500) NULL AFTER sha256,
  ADD COLUMN IF NOT EXISTS thumbnail_mime_type VARCHAR(100) NULL AFTER thumbnail_relative_path,
  ADD COLUMN IF NOT EXISTS thumbnail_file_size INT NULL AFTER thumbnail_mime_type,
  ADD COLUMN IF NOT EXISTS thumbnail_sha256 CHAR(64) NULL AFTER thumbnail_file_size,
  ADD COLUMN IF NOT EXISTS instagram_deleted_at DATETIME NULL AFTER instagram_posted_at,
  ADD COLUMN IF NOT EXISTS tiktok_deleted_at DATETIME NULL AFTER tiktok_posted_at,
  ADD COLUMN IF NOT EXISTS facebook_deleted_at DATETIME NULL AFTER facebook_posted_at;
ALTER TABLE work_social_videos
  ADD COLUMN IF NOT EXISTS expires_at DATETIME NULL AFTER completed_at,
  ADD COLUMN IF NOT EXISTS file_deleted_at DATETIME NULL AFTER expires_at;

UPDATE work_social_videos
SET expires_at=DATE_ADD(COALESCE(completed_at,created_at),INTERVAL 3 MONTH)
WHERE status='complete' AND expires_at IS NULL;
