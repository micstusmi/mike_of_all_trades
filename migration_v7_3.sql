CREATE TABLE work_task_change_requests (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  job_id INT NOT NULL,
  task_id INT UNSIGNED NOT NULL,
  request_type ENUM('instructions','measurement','colour_finish','product_material','positioning','quantity','scheduling','other') NOT NULL DEFAULT 'instructions',
  customer_message TEXT NOT NULL,
  original_task_snapshot_json LONGTEXT NULL,
  status ENUM('awaiting_review','accepted','amended','declined','question_sent','withdrawn') NOT NULL DEFAULT 'awaiting_review',
  mike_response TEXT NULL,
  affects_estimate ENUM('unknown','no','yes') NOT NULL DEFAULT 'unknown',
  estimate_delta_low DECIMAL(7,2) NULL,
  estimate_delta_high DECIMAL(7,2) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reviewed_at DATETIME NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_work_task_changes_job_task_status (job_id,task_id,status,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
