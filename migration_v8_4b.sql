-- Mike of All Trades Work Tracker V8.4B
-- Corrected for existing work_materials schema.
-- Run ONCE.

ALTER TABLE work_materials
  ADD COLUMN task_id INT NULL AFTER job_id,
  ADD COLUMN material_status ENUM(
    'already_on_site',
    'customer_will_supply',
    'mike_to_purchase',
    'mike_has_it',
    'maybe_required',
    'not_required'
  ) NOT NULL DEFAULT 'mike_to_purchase' AFTER description,
  ADD COLUMN estimated_cost DECIMAL(10,2) NULL AFTER supplier,
  ADD COLUMN actual_cost DECIMAL(10,2) NULL AFTER estimated_cost,
  ADD COLUMN source_type ENUM(
    'supplier_purchase',
    'mike_vehicle_stock',
    'customer_supplied',
    'already_on_site',
    'other'
  ) NOT NULL DEFAULT 'supplier_purchase' AFTER actual_cost,
  ADD COLUMN reimbursement_status ENUM(
    'not_applicable',
    'reimbursement_due',
    'reimbursed',
    'no_reimbursement_due'
  ) NOT NULL DEFAULT 'not_applicable' AFTER paid_by,
  ADD COLUMN updated_at DATETIME NULL AFTER purchased_at,
  ADD INDEX idx_work_materials_task (task_id),
  ADD INDEX idx_work_materials_status (job_id, material_status);

UPDATE work_materials
SET actual_cost = cost
WHERE actual_cost IS NULL AND cost IS NOT NULL;
