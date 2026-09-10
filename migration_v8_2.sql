ALTER TABLE work_jobs
  ADD COLUMN photo_social_permission ENUM('not_set','allowed','ask_first','not_allowed') NOT NULL DEFAULT 'ask_first' AFTER ai_breakdown_error;

CREATE TABLE work_task_photos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  job_id INT UNSIGNED NOT NULL,
  task_id INT UNSIGNED NOT NULL,
  photo_type ENUM('before','after') NOT NULL,
  uploader_type ENUM('customer','mike') NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  stored_name VARCHAR(255) NOT NULL,
  relative_path VARCHAR(500) NOT NULL,
  mime_type VARCHAR(100) NOT NULL,
  file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
  sha256 CHAR(64) NULL,
  note VARCHAR(500) NULL,
  keep_permanent TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_work_task_photos_task_type (task_id,photo_type,id),
  KEY idx_work_task_photos_job (job_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
