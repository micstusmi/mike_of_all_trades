-- V8.41: private multi-receipt spreadsheet imports.
-- The original spreadsheet remains private.
-- Parsed receipt groups remain drafts until Mike approves them.

CREATE TABLE IF NOT EXISTS work_receipt_imports (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_id INT UNSIGNED NOT NULL,
    task_id INT UNSIGNED NULL,
    session_id INT UNSIGNED NULL,

    source_type ENUM(
        'spreadsheet',
        'google_drive_spreadsheet'
    ) NOT NULL DEFAULT 'spreadsheet',

    original_name VARCHAR(255) NOT NULL,
    relative_path VARCHAR(500) NOT NULL,
    mime_type VARCHAR(150) NOT NULL,
    file_size BIGINT UNSIGNED NOT NULL,
    sha256 CHAR(64) NOT NULL,

    status ENUM(
        'processing',
        'ready',
        'applied',
        'duplicate',
        'failed',
        'cancelled'
    ) NOT NULL DEFAULT 'processing',

    sheet_count INT UNSIGNED NOT NULL DEFAULT 0,
    receipt_count INT UNSIGNED NOT NULL DEFAULT 0,
    line_count INT UNSIGNED NOT NULL DEFAULT 0,

    spreadsheet_gross_total DECIMAL(12,2) NULL,
    parsed_gross_total DECIMAL(12,2) NULL,
    parsed_gst_total DECIMAL(12,2) NULL,
    parsed_net_total DECIMAL(12,2) NULL,
    reconciliation_difference DECIMAL(12,2) NULL,

    confidence_note TEXT NULL,
    reconciliation_warnings LONGTEXT NULL,
    extracted_text LONGTEXT NULL,
    proposal_json LONGTEXT NULL,
    approved_json LONGTEXT NULL,
    applied_material_ids_json LONGTEXT NULL,
    error_message TEXT NULL,

    progress_percent TINYINT UNSIGNED NOT NULL DEFAULT 0,
    processing_stage VARCHAR(100) NULL,
    processing_detail VARCHAR(255) NULL,
    started_at DATETIME NULL,
    heartbeat_at DATETIME NULL,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,
    reviewed_at DATETIME NULL,
    reviewed_by VARCHAR(150) NULL,

    CONSTRAINT fk_receipt_import_job
        FOREIGN KEY (job_id)
        REFERENCES work_jobs(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_receipt_import_task
        FOREIGN KEY (task_id)
        REFERENCES work_tasks(id)
        ON DELETE SET NULL,

    CONSTRAINT fk_receipt_import_session
        FOREIGN KEY (session_id)
        REFERENCES work_sessions(id)
        ON DELETE SET NULL,

    UNIQUE KEY uq_receipt_import_job_sha (job_id, sha256),

    INDEX idx_receipt_import_job_status (
        job_id,
        status,
        created_at
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE work_receipt_imports
    ADD COLUMN IF NOT EXISTS progress_percent TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER error_message,
    ADD COLUMN IF NOT EXISTS processing_stage VARCHAR(100) NULL AFTER progress_percent,
    ADD COLUMN IF NOT EXISTS processing_detail VARCHAR(255) NULL AFTER processing_stage,
    ADD COLUMN IF NOT EXISTS started_at DATETIME NULL AFTER processing_detail,
    ADD COLUMN IF NOT EXISTS heartbeat_at DATETIME NULL AFTER started_at;

ALTER TABLE work_materials
    ADD COLUMN IF NOT EXISTS financial_treatment ENUM('charge_customer','included_in_price','goodwill','rectification') NOT NULL DEFAULT 'charge_customer' AFTER reimbursement_status,
    ADD COLUMN IF NOT EXISTS payment_card_last4 CHAR(4) NULL AFTER paid_by,
    ADD INDEX IF NOT EXISTS idx_work_materials_card_last4 (job_id, payment_card_last4);

ALTER TABLE work_receipts
    ADD COLUMN IF NOT EXISTS payment_card_last4 CHAR(4) NULL AFTER paid_by;
