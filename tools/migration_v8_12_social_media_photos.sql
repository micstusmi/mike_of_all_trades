-- Mike of All Trades Work Tracker V8.12
-- Branded task photo copies and saved social-media draft posts.

ALTER TABLE work_task_photos
  ADD COLUMN IF NOT EXISTS social_relative_path VARCHAR(500) NULL AFTER relative_path,
  ADD COLUMN IF NOT EXISTS social_mime_type VARCHAR(100) NULL AFTER mime_type,
  ADD COLUMN IF NOT EXISTS social_file_size INT NULL AFTER file_size,
  ADD COLUMN IF NOT EXISTS social_sha256 CHAR(64) NULL AFTER sha256,
  ADD COLUMN IF NOT EXISTS original_file_size INT NULL AFTER file_size,
  ADD COLUMN IF NOT EXISTS server_expires_at DATETIME NULL AFTER keep_permanent,
  ADD COLUMN IF NOT EXISTS social_expires_at DATETIME NULL AFTER server_expires_at,
  ADD COLUMN IF NOT EXISTS file_deleted_at DATETIME NULL AFTER social_expires_at,
  ADD COLUMN IF NOT EXISTS social_deleted_at DATETIME NULL AFTER file_deleted_at,
  ADD COLUMN IF NOT EXISTS archive_reference VARCHAR(500) NULL AFTER social_deleted_at,
  ADD COLUMN IF NOT EXISTS photo_taken_at DATETIME NULL AFTER archive_reference,
  ADD COLUMN IF NOT EXISTS assignment_method VARCHAR(80) NULL AFTER photo_taken_at;

CREATE TABLE IF NOT EXISTS work_social_drafts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  job_id INT NOT NULL,
  draft_date DATE NOT NULL,
  platform VARCHAR(40) NOT NULL DEFAULT 'general',
  tone VARCHAR(40) NOT NULL DEFAULT 'friendly',
  title VARCHAR(255) NOT NULL,
  caption TEXT NOT NULL,
  short_caption TEXT NULL,
  hashtags TEXT NULL,
  photo_plan TEXT NULL,
  selected_photo_ids TEXT NULL,
  ai_model VARCHAR(80) NULL,
  raw_ai LONGTEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  KEY idx_work_social_drafts_job_date (job_id, draft_date, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
