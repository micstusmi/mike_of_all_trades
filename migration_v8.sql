ALTER TABLE work_jobs
  ADD COLUMN customer_request_text LONGTEXT NULL AFTER original_scope,
  ADD COLUMN customer_request_updated_at DATETIME NULL AFTER customer_request_text,
  ADD COLUMN ai_breakdown_status ENUM('not_requested','pending','running','complete','failed') NOT NULL DEFAULT 'not_requested' AFTER customer_request_updated_at,
  ADD COLUMN ai_breakdown_generated_at DATETIME NULL AFTER ai_breakdown_status,
  ADD COLUMN ai_breakdown_error TEXT NULL AFTER ai_breakdown_generated_at;

CREATE TABLE work_job_request_revisions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  job_id INT UNSIGNED NOT NULL,
  source ENUM('intake','customer','mike') NOT NULL DEFAULT 'customer',
  previous_text LONGTEXT NULL,
  new_text LONGTEXT NOT NULL,
  note VARCHAR(255) NULL,
  requires_review TINYINT(1) NOT NULL DEFAULT 1,
  reviewed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_work_job_request_revisions_job_review (job_id,requires_review,reviewed_at,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE work_tasks
  ADD COLUMN ai_batch_key VARCHAR(64) NULL AFTER task_origin,
  ADD COLUMN source_request_revision_id BIGINT UNSIGNED NULL AFTER ai_batch_key,
  ADD KEY idx_work_tasks_ai_batch (job_id,ai_batch_key);

UPDATE work_jobs
SET customer_request_text = original_scope,
    customer_request_updated_at = COALESCE(updated_at, created_at)
WHERE customer_request_text IS NULL OR customer_request_text='';
