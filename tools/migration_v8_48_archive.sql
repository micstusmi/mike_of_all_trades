ALTER TABLE work_jobs
  ADD COLUMN media_archive_url VARCHAR(2048) NULL AFTER materials_notes,
  ADD COLUMN media_archive_description TEXT NULL AFTER media_archive_url,
  ADD COLUMN media_archive_verified_at DATE NULL AFTER media_archive_description,
  ADD COLUMN media_archive_customer_visible TINYINT(1) NOT NULL DEFAULT 0 AFTER media_archive_verified_at,
  ADD COLUMN media_archive_updated_at DATETIME NULL AFTER media_archive_customer_visible;
