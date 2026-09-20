-- Work Tracker v8.43
-- Idempotent compatibility repair for older databases before spreadsheet approval.

ALTER TABLE work_materials
    ADD COLUMN IF NOT EXISTS financial_treatment
        ENUM('charge_customer','included_in_price','goodwill','rectification')
        NOT NULL DEFAULT 'charge_customer' AFTER reimbursement_status,
    ADD COLUMN IF NOT EXISTS payment_card_last4 CHAR(4) NULL AFTER paid_by,
    ADD INDEX IF NOT EXISTS idx_work_materials_card_last4 (job_id, payment_card_last4);

ALTER TABLE work_receipts
    ADD COLUMN IF NOT EXISTS financial_treatment
        ENUM('charge_customer','included_in_price','goodwill','rectification')
        NOT NULL DEFAULT 'charge_customer' AFTER reimbursement_status,
    ADD COLUMN IF NOT EXISTS payment_card_last4 CHAR(4) NULL AFTER paid_by;
