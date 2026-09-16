-- Mike of All Trades Work Tracker V8.12N
-- Platform-specific social photo variants and posted tracking.

ALTER TABLE work_task_photos
  ADD COLUMN IF NOT EXISTS instagram_relative_path VARCHAR(500) NULL AFTER social_sha256,
  ADD COLUMN IF NOT EXISTS instagram_mime_type VARCHAR(100) NULL AFTER instagram_relative_path,
  ADD COLUMN IF NOT EXISTS instagram_file_size INT NULL AFTER instagram_mime_type,
  ADD COLUMN IF NOT EXISTS instagram_sha256 CHAR(64) NULL AFTER instagram_file_size,
  ADD COLUMN IF NOT EXISTS instagram_posted_at DATETIME NULL AFTER instagram_sha256,
  ADD COLUMN IF NOT EXISTS tiktok_relative_path VARCHAR(500) NULL AFTER instagram_posted_at,
  ADD COLUMN IF NOT EXISTS tiktok_mime_type VARCHAR(100) NULL AFTER tiktok_relative_path,
  ADD COLUMN IF NOT EXISTS tiktok_file_size INT NULL AFTER tiktok_mime_type,
  ADD COLUMN IF NOT EXISTS tiktok_sha256 CHAR(64) NULL AFTER tiktok_file_size,
  ADD COLUMN IF NOT EXISTS tiktok_posted_at DATETIME NULL AFTER tiktok_sha256,
  ADD COLUMN IF NOT EXISTS facebook_relative_path VARCHAR(500) NULL AFTER tiktok_posted_at,
  ADD COLUMN IF NOT EXISTS facebook_mime_type VARCHAR(100) NULL AFTER facebook_relative_path,
  ADD COLUMN IF NOT EXISTS facebook_file_size INT NULL AFTER facebook_mime_type,
  ADD COLUMN IF NOT EXISTS facebook_sha256 CHAR(64) NULL AFTER facebook_file_size,
  ADD COLUMN IF NOT EXISTS facebook_posted_at DATETIME NULL AFTER facebook_sha256;

ALTER TABLE work_social_drafts
  ADD COLUMN IF NOT EXISTS posted_at DATETIME NULL AFTER selected_photo_ids,
  ADD COLUMN IF NOT EXISTS do_not_post_at DATETIME NULL AFTER posted_at,
  ADD COLUMN IF NOT EXISTS post_notes VARCHAR(500) NULL AFTER do_not_post_at;
