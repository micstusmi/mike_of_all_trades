-- Reviewable scheduling drafts only; no calendar provider connection or customer notification.
CREATE TABLE alpha_calendar_drafts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  business_id BIGINT UNSIGNED NOT NULL,
  created_by BIGINT UNSIGNED NOT NULL,
  source ENUM('manual','calendar') NOT NULL,
  provider VARCHAR(40) NULL,
  calendar_id VARCHAR(190) NULL,
  event_id VARCHAR(190) NULL,
  title VARCHAR(190) NOT NULL,
  notes TEXT NULL,
  starts_at_utc DATETIME NOT NULL,
  ends_at_utc DATETIME NOT NULL,
  timezone VARCHAR(80) NOT NULL,
  state ENUM('pending','confirmed','cancelled') NOT NULL DEFAULT 'pending',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_alpha_calendar_event (business_id,provider,calendar_id,event_id),
  KEY idx_alpha_calendar_business (business_id,starts_at_utc),
  FOREIGN KEY (business_id,created_by) REFERENCES alpha_memberships(business_id,user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
