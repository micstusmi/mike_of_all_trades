ALTER TABLE alpha_legacy_links MODIFY entity ENUM('customer','property','job','task','session','break','material','payment') NOT NULL;

CREATE TABLE alpha_tasks (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  business_id BIGINT UNSIGNED NOT NULL,
  job_id BIGINT UNSIGNED NOT NULL,
  task_order INT NOT NULL DEFAULT 0,
  title VARCHAR(255) NOT NULL,
  description TEXT NULL,
  status ENUM('not_started','in_progress','blocked','completed','cancelled') NOT NULL,
  customer_visible TINYINT(1) NOT NULL DEFAULT 1,
  completed_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_alpha_task_owner (business_id,id),
  UNIQUE KEY uq_alpha_task_job_owner (business_id,job_id,id),
  KEY idx_alpha_task_job (business_id,job_id,task_order),
  FOREIGN KEY (business_id,job_id) REFERENCES alpha_jobs(business_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE alpha_work_sessions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  business_id BIGINT UNSIGNED NOT NULL,
  job_id BIGINT UNSIGNED NOT NULL,
  task_id BIGINT UNSIGNED NULL,
  started_at DATETIME NOT NULL,
  ended_at DATETIME NULL,
  category VARCHAR(40) NOT NULL,
  billable TINYINT(1) NOT NULL,
  notes TEXT NULL,
  session_source VARCHAR(40) NOT NULL DEFAULT 'live',
  retrospective_hours DECIMAL(12,3) NULL,
  UNIQUE KEY uq_alpha_session_owner (business_id,id),
  KEY idx_alpha_session_job (business_id,job_id,started_at),
  FOREIGN KEY (business_id,job_id) REFERENCES alpha_jobs(business_id,id),
  FOREIGN KEY (business_id,job_id,task_id) REFERENCES alpha_tasks(business_id,job_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE alpha_session_breaks (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  business_id BIGINT UNSIGNED NOT NULL,
  session_id BIGINT UNSIGNED NOT NULL,
  started_at DATETIME NOT NULL,
  ended_at DATETIME NULL,
  reason VARCHAR(50) NOT NULL,
  note VARCHAR(500) NULL,
  KEY idx_alpha_break_session (business_id,session_id),
  FOREIGN KEY (business_id,session_id) REFERENCES alpha_work_sessions(business_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE alpha_materials (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  business_id BIGINT UNSIGNED NOT NULL,
  job_id BIGINT UNSIGNED NOT NULL,
  purchased_at DATETIME NOT NULL,
  description VARCHAR(255) NOT NULL,
  supplier VARCHAR(150) NULL,
  cost DECIMAL(12,2) NOT NULL,
  paid_by VARCHAR(40) NOT NULL,
  notes TEXT NULL,
  receipt_path_reference VARCHAR(500) NULL,
  KEY idx_alpha_material_job (business_id,job_id),
  FOREIGN KEY (business_id,job_id) REFERENCES alpha_jobs(business_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE alpha_payments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  business_id BIGINT UNSIGNED NOT NULL,
  job_id BIGINT UNSIGNED NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  payment_type VARCHAR(40) NOT NULL,
  method VARCHAR(50) NULL,
  notes TEXT NULL,
  paid_at DATETIME NOT NULL,
  KEY idx_alpha_payment_job (business_id,job_id),
  FOREIGN KEY (business_id,job_id) REFERENCES alpha_jobs(business_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
