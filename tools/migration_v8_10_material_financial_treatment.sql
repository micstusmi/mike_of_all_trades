-- Work Tracker v8.10
-- Separate who paid for a material from how it is financially treated.

ALTER TABLE work_materials
ADD COLUMN financial_treatment
ENUM(
    'charge_customer',
    'included_in_price',
    'goodwill',
    'rectification'
)
NOT NULL DEFAULT 'charge_customer'
AFTER reimbursement_status;
