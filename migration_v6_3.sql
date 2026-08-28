-- V6.3 retrospective entry simplification. Run once after migration_v6.sql.
ALTER TABLE work_sessions
  ADD COLUMN retrospective_entry_basis ENUM('hours','exact_times') NULL AFTER retrospective_entered_at,
  ADD COLUMN retrospective_hours DECIMAL(7,4) NULL AFTER retrospective_entry_basis;
