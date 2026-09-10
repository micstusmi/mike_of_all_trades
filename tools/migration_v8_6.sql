ALTER TABLE work_jobs
  ADD COLUMN planned_start_at DATETIME NULL AFTER status,
  ADD COLUMN planned_finish_at DATETIME NULL AFTER planned_start_at,
  ADD COLUMN parking_notes TEXT NULL AFTER planned_finish_at,
  ADD COLUMN access_notes TEXT NULL AFTER parking_notes,
  ADD COLUMN customer_confirmation_status
    ENUM('not_requested','awaiting','confirmed','needs_change')
    NOT NULL DEFAULT 'not_requested'
    AFTER access_notes,
  ADD COLUMN confirmation_requested_at DATETIME NULL AFTER customer_confirmation_status,
  ADD COLUMN confirmation_received_at DATETIME NULL AFTER confirmation_requested_at,
  ADD INDEX idx_work_jobs_planned_start (planned_start_at),
  ADD INDEX idx_work_jobs_confirmation (customer_confirmation_status);
