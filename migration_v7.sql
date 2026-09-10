-- Mike of All Trades Work Tracker V7 — task management + job source

ALTER TABLE work_jobs
  ADD COLUMN job_source VARCHAR(50) NULL AFTER customer_phone,
  ADD COLUMN job_source_detail VARCHAR(255) NULL AFTER job_source,
  ADD COLUMN original_contact_notes TEXT NULL AFTER job_source_detail;

CREATE TABLE work_tasks (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  job_id INT NOT NULL,
  task_order INT NOT NULL DEFAULT 0,
  title VARCHAR(255) NOT NULL,
  description TEXT NULL,
  status ENUM('not_started','in_progress','blocked','completed','cancelled') NOT NULL DEFAULT 'not_started',
  task_origin ENUM('original','customer_requested','mike_added','ai_suggested','unforeseen') NOT NULL DEFAULT 'original',
  ai_estimate_low DECIMAL(7,2) NULL,
  ai_estimate_high DECIMAL(7,2) NULL,
  ai_reasoning TEXT NULL,
  mike_estimate_low DECIMAL(7,2) NULL,
  mike_estimate_high DECIMAL(7,2) NULL,
  mike_reasoning TEXT NULL,
  actual_adjusted_hours DECIMAL(7,2) NULL,
  actual_reasoning TEXT NULL,
  customer_visible TINYINT(1) NOT NULL DEFAULT 1,
  completed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_work_tasks_job_status_order (job_id,status,task_order,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE work_sessions
  ADD COLUMN task_id INT UNSIGNED NULL AFTER worker_id,
  ADD KEY idx_work_sessions_task (task_id);
