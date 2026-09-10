-- Mike of All Trades Work Tracker V8.4A
-- Run ONCE.
ALTER TABLE work_jobs
  ADD COLUMN materials_responsibility ENUM(
    'mike_all',
    'customer_all',
    'shared',
    'labour_only',
    'mike_advise'
  ) NULL AFTER customer_request_updated_at,
  ADD COLUMN materials_notes TEXT NULL AFTER materials_responsibility;
