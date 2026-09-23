-- Apply after 001_foundation.sql to an empty, dedicated alpha database.
-- No provider credentials or customer media are stored in these tables.
ALTER TABLE alpha_businesses
  ADD COLUMN accounting_provider ENUM('none','zoho','xero','myob','quickbooks') NOT NULL DEFAULT 'none';

CREATE TABLE alpha_tester_profiles (
  business_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  internal_label VARCHAR(190) NOT NULL DEFAULT '',
  notes TEXT NULL,
  FOREIGN KEY (business_id) REFERENCES alpha_businesses(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE alpha_auth_tokens (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  business_id BIGINT UNSIGNED NOT NULL,
  email VARCHAR(190) NOT NULL,
  role ENUM('owner','admin','staff') NOT NULL,
  purpose ENUM('invite','reset') NOT NULL,
  token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  expires_at DATETIME NOT NULL,
  consumed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_alpha_auth_token (token_hash),
  KEY idx_alpha_auth_email (email,purpose),
  FOREIGN KEY (business_id) REFERENCES alpha_businesses(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE alpha_login_attempts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL,
  business_id BIGINT UNSIGNED NOT NULL,
  attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_alpha_attempts (email,business_id,attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE alpha_feature_requests (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  business_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(190) NOT NULL,
  detail TEXT NOT NULL,
  area VARCHAR(80) NOT NULL,
  status ENUM('submitted','assessing','planned','in_progress','testing','released','declined') NOT NULL DEFAULT 'submitted',
  priority ENUM('unset','low','medium','high') NOT NULL DEFAULT 'unset',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_alpha_request_owner (business_id,id),
  KEY idx_alpha_request_author (business_id,user_id),
  FOREIGN KEY (business_id,user_id) REFERENCES alpha_memberships(business_id,user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE alpha_feature_updates (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  business_id BIGINT UNSIGNED NOT NULL,
  request_id BIGINT UNSIGNED NOT NULL,
  author_id BIGINT UNSIGNED NULL,
  author_label VARCHAR(80) NOT NULL DEFAULT 'ezTradie team',
  message TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (business_id,request_id) REFERENCES alpha_feature_requests(business_id,id),
  FOREIGN KEY (business_id,author_id) REFERENCES alpha_memberships(business_id,user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Prices are AUD cents charged to the business; provider cost is tracked separately.
-- No automatic payment collection is enabled by this ledger.
CREATE TABLE alpha_ai_budgets (
  business_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  monthly_limit_cents INT UNSIGNED NOT NULL DEFAULT 0,
  monthly_provider_limit_microusd BIGINT UNSIGNED NOT NULL DEFAULT 0,
  FOREIGN KEY (business_id) REFERENCES alpha_businesses(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE alpha_ai_usage (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  business_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  feature ENUM('voice','slideshow_plan','narration','video_render','other') NOT NULL,
  event_key VARCHAR(100) NOT NULL,
  units DECIMAL(12,3) NOT NULL,
  unit_name VARCHAR(40) NOT NULL,
  charge_cents INT UNSIGNED NOT NULL,
  provider_cost_microusd BIGINT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_alpha_usage_event (business_id,event_key),
  KEY idx_alpha_usage_month (business_id,created_at),
  FOREIGN KEY (business_id,user_id) REFERENCES alpha_memberships(business_id,user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
