CREATE TABLE IF NOT EXISTS work_invoice_ledger (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  customer_id INT UNSIGNED NOT NULL,
  zoho_invoice_id VARCHAR(100) NOT NULL,
  invoice_number VARCHAR(100) NOT NULL,
  invoice_date DATE NULL,
  due_date DATE NULL,
  status VARCHAR(40) NOT NULL DEFAULT 'imported',
  total_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  balance_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  tax_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  source VARCHAR(30) NOT NULL DEFAULT 'historical',
  zoho_json LONGTEXT NULL,
  reconciled_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY(id), UNIQUE KEY uq_work_invoice_zoho(zoho_invoice_id),
  KEY idx_work_invoice_customer(customer_id), KEY idx_work_invoice_number(invoice_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS work_invoice_jobs (
  invoice_id BIGINT UNSIGNED NOT NULL, job_id INT UNSIGNED NOT NULL,
  PRIMARY KEY(invoice_id,job_id), KEY idx_work_invoice_jobs_job(job_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS work_invoice_sessions (
  invoice_id BIGINT UNSIGNED NOT NULL, session_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY(invoice_id,session_id), UNIQUE KEY uq_work_billed_session(session_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS work_invoice_materials (
  invoice_id BIGINT UNSIGNED NOT NULL, material_id INT UNSIGNED NOT NULL,
  PRIMARY KEY(invoice_id,material_id), UNIQUE KEY uq_work_billed_material(material_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
