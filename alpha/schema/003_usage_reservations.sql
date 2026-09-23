-- Apply after 002_alpha_services.sql to the dedicated alpha database.
-- Reservations are required before any billable provider request.
ALTER TABLE alpha_ai_usage
  ADD COLUMN state ENUM('reserved','completed','cancelled') NOT NULL DEFAULT 'completed',
  ADD COLUMN expires_at DATETIME NULL,
  ADD COLUMN finished_at DATETIME NULL,
  ADD KEY idx_alpha_usage_reservations (business_id,state,expires_at);
