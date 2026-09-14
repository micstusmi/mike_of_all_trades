-- Work Tracker V8.9
-- Separate goodwill/free extras from rectification/rework,
-- and separate labour from materials.
--
-- Existing records are preserved.
-- Existing labour/material values are copied into the matching component.
-- Existing records remain 'unclassified' until Mike reviews them.

ALTER TABLE work_complimentary_items
    ADD COLUMN no_charge_reason ENUM(
        'unclassified',
        'goodwill',
        'rectification',
        'other'
    ) NOT NULL DEFAULT 'unclassified'
    AFTER item_type,

    ADD COLUMN labour_hours DECIMAL(8,2) NULL
    AFTER no_charge_reason,

    ADD COLUMN labour_value DECIMAL(10,2) NOT NULL DEFAULT 0.00
    AFTER labour_hours,

    ADD COLUMN material_value DECIMAL(10,2) NOT NULL DEFAULT 0.00
    AFTER labour_value,

    ADD COLUMN material_details TEXT NULL
    AFTER material_value,

    ADD COLUMN updated_at DATETIME NULL
    AFTER note;

UPDATE work_complimentary_items
SET labour_value = estimated_value
WHERE item_type = 'labour'
  AND labour_value = 0;

UPDATE work_complimentary_items
SET material_value = estimated_value
WHERE item_type = 'material'
  AND material_value = 0;

UPDATE work_complimentary_items
SET updated_at = NOW()
WHERE updated_at IS NULL;
