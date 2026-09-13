/*
 * Work Tracker V8.8
 *
 * Materials/receipts accounting detail
 * Payments/source references
 * Persistent admin workspace preferences
 * Job close-out snapshots
 *
 * IMPORTANT:
 * receipt_gst_amount records GST appearing on a third-party source receipt.
 * It does NOT mean Mike Of All Trades charges GST.
 */

ALTER TABLE work_materials
    ADD COLUMN IF NOT EXISTS receipt_gst_amount DECIMAL(10,2) NULL
        AFTER receipt_path,
    ADD COLUMN IF NOT EXISTS receipt_number VARCHAR(100) NULL
        AFTER receipt_gst_amount,
    ADD COLUMN IF NOT EXISTS purchase_date DATE NULL
        AFTER receipt_number;

ALTER TABLE work_payments
    ADD COLUMN IF NOT EXISTS source_reference VARCHAR(150) NULL
        AFTER method;

CREATE TABLE IF NOT EXISTS work_admin_job_preferences (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_id INT UNSIGNED NOT NULL,
    admin_user_id INT UNSIGNED NOT NULL,

    section_order_json LONGTEXT NULL,
    open_sections_json LONGTEXT NULL,
    task_filter VARCHAR(30) NOT NULL DEFAULT 'all',

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_work_admin_job_preferences (job_id, admin_user_id),
    KEY idx_work_admin_job_preferences_job (job_id)
);

CREATE TABLE IF NOT EXISTS work_closeout_snapshots (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_id INT UNSIGNED NOT NULL,

    status ENUM(
        'draft',
        'reviewed',
        'approved',
        'sent_to_zoho',
        'superseded'
    ) NOT NULL DEFAULT 'draft',

    labour_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    materials_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    other_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    gross_job_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    payments_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    outstanding_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,

    snapshot_json LONGTEXT NOT NULL,

    correction_notes TEXT NULL,

    zoho_invoice_id VARCHAR(100) NULL,
    zoho_response_json LONGTEXT NULL,

    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_at DATETIME NULL,
    approved_at DATETIME NULL,
    sent_to_zoho_at DATETIME NULL,

    PRIMARY KEY (id),
    KEY idx_work_closeout_job (job_id),
    KEY idx_work_closeout_status (status)
);
