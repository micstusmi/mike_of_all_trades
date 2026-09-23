-- First-pass Mike-only import. Source IDs are retained for reconciliation.
ALTER TABLE alpha_jobs MODIFY status ENUM('draft','awaiting_agreement','active','paused','completed','cancelled') NOT NULL DEFAULT 'draft';

CREATE TABLE alpha_legacy_links (
  business_id BIGINT UNSIGNED NOT NULL,
  entity ENUM('customer','property','job') NOT NULL,
  source_id BIGINT UNSIGNED NOT NULL,
  target_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (business_id,entity,source_id),
  UNIQUE KEY uq_alpha_import_target (business_id,entity,target_id),
  FOREIGN KEY (business_id) REFERENCES alpha_businesses(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE alpha_job_legacy_details (
  business_id BIGINT UNSIGNED NOT NULL,
  job_id BIGINT UNSIGNED NOT NULL,
  original_scope LONGTEXT NOT NULL,
  current_scope LONGTEXT NULL,
  original_estimate_amount DECIMAL(12,2) NULL,
  original_estimate_hours DECIMAL(10,2) NULL,
  agreed_hourly_rate DECIMAL(12,2) NULL,
  payment_mode VARCHAR(40) NOT NULL,
  unpaid_balance_limit DECIMAL(12,2) NULL,
  work_already_value DECIMAL(12,2) NOT NULL DEFAULT 0,
  materials_already_value DECIMAL(12,2) NOT NULL DEFAULT 0,
  payments_received DECIMAL(12,2) NOT NULL DEFAULT 0,
  planned_start_at DATETIME NULL,
  planned_finish_at DATETIME NULL,
  media_archive_url VARCHAR(2048) NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (business_id,job_id),
  FOREIGN KEY (business_id,job_id) REFERENCES alpha_jobs(business_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE alpha_import_runs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  business_id BIGINT UNSIGNED NOT NULL,
  source_name VARCHAR(190) NOT NULL,
  imported_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  inventory_json JSON NOT NULL,
  FOREIGN KEY (business_id) REFERENCES alpha_businesses(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
