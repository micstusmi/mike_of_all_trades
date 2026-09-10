ALTER TABLE work_tasks
  ADD COLUMN customer_summary TEXT NULL AFTER description,
  ADD COLUMN detailed_procedure TEXT NULL AFTER customer_summary,
  ADD COLUMN time_drivers TEXT NULL AFTER detailed_procedure,
  ADD COLUMN waiting_curing_notes TEXT NULL AFTER time_drivers,
  ADD COLUMN suggested_materials TEXT NULL AFTER waiting_curing_notes;
