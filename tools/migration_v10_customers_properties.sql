CREATE TABLE IF NOT EXISTS work_customers (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  display_name VARCHAR(190) NOT NULL,
  source_alias VARCHAR(190) NULL,
  organisation VARCHAR(190) NULL,
  email VARCHAR(190) NULL,
  phone VARCHAR(30) NULL,
  billing_address VARCHAR(500) NULL,
  payment_terms_days SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  zoho_contact_id VARCHAR(50) NULL,
  zoho_contact_name VARCHAR(190) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_work_customers_zoho (zoho_contact_id),
  KEY idx_work_customers_email (email),
  KEY idx_work_customers_phone (phone),
  KEY idx_work_customers_name (display_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS work_properties (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  customer_id INT UNSIGNED NOT NULL,
  label VARCHAR(190) NULL,
  address VARCHAR(500) NOT NULL,
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_work_properties_customer (customer_id),
  CONSTRAINT fk_work_properties_customer FOREIGN KEY (customer_id) REFERENCES work_customers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE work_jobs ADD COLUMN customer_id INT UNSIGNED NULL AFTER id;
ALTER TABLE work_jobs ADD COLUMN property_id INT UNSIGNED NULL AFTER customer_id;
ALTER TABLE work_jobs ADD KEY idx_work_jobs_customer (customer_id);
ALTER TABLE work_jobs ADD KEY idx_work_jobs_property (property_id);
