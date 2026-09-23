-- SMS is disabled for every business. This migration stores reviewable drafts only.
-- No provider credentials, sender routes or external callbacks are configured.
CREATE TABLE alpha_sms_settings (
  business_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  enabled TINYINT(1) NOT NULL DEFAULT 0,
  monthly_limit_cents INT UNSIGNED NOT NULL DEFAULT 0,
  sender_label VARCHAR(40) NOT NULL DEFAULT '',
  FOREIGN KEY (business_id) REFERENCES alpha_businesses(id),
  CONSTRAINT chk_alpha_sms_disabled CHECK (enabled = 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE alpha_sms_drafts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  business_id BIGINT UNSIGNED NOT NULL,
  author_id BIGINT UNSIGNED NOT NULL,
  customer_id BIGINT UNSIGNED NOT NULL,
  job_id BIGINT UNSIGNED NULL,
  message TEXT NOT NULL,
  state ENUM('draft','discarded') NOT NULL DEFAULT 'draft',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_alpha_sms_draft_owner (business_id,id),
  KEY idx_alpha_sms_drafts (business_id,customer_id,created_at),
  FOREIGN KEY (business_id,author_id) REFERENCES alpha_memberships(business_id,user_id),
  FOREIGN KEY (business_id,customer_id) REFERENCES alpha_customers(business_id,id),
  FOREIGN KEY (business_id,job_id) REFERENCES alpha_jobs(business_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
