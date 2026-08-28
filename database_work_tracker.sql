CREATE TABLE IF NOT EXISTS work_jobs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_token CHAR(64) NOT NULL UNIQUE,
    customer_name VARCHAR(150) NOT NULL,
    customer_phone VARCHAR(30) NOT NULL,
    customer_email VARCHAR(190) NULL,
    job_address VARCHAR(255) NOT NULL,
    original_scope TEXT NOT NULL,
    current_scope TEXT NULL,
    unforeseen_conditions TEXT NULL,
    original_estimate_amount DECIMAL(10,2) NULL,
    original_estimate_hours DECIMAL(8,2) NULL,
    agreed_hourly_rate DECIMAL(10,2) NULL,
    payment_mode ENUM('completion','daily','balance_limit','milestone') NOT NULL DEFAULT 'daily',
    unpaid_balance_limit DECIMAL(10,2) NULL,
    work_already_value DECIMAL(10,2) NOT NULL DEFAULT 0,
    materials_already_value DECIMAL(10,2) NOT NULL DEFAULT 0,
    payments_received DECIMAL(10,2) NOT NULL DEFAULT 0,
    revised_forecast_low DECIMAL(10,2) NULL,
    revised_forecast_high DECIMAL(10,2) NULL,
    status ENUM('draft','awaiting_agreement','active','paused','completed','cancelled') NOT NULL DEFAULT 'draft',
    agreement_signed_at DATETIME NULL,
    agreement_signature_path VARCHAR(255) NULL,
    agreement_name VARCHAR(150) NULL,
    agreement_ip VARCHAR(64) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_work_jobs_phone (customer_phone),
    INDEX idx_work_jobs_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS work_workers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_id INT UNSIGNED NOT NULL,
    worker_name VARCHAR(150) NOT NULL,
    hourly_rate DECIMAL(10,2) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_work_workers_job FOREIGN KEY (job_id) REFERENCES work_jobs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS work_sessions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_id INT UNSIGNED NOT NULL,
    worker_id INT UNSIGNED NULL,
    started_at DATETIME NOT NULL,
    ended_at DATETIME NULL,
    category ENUM('onsite','measurement','planning','procurement','travel','loading_setup','demolition','repair','unforeseen','other') NOT NULL DEFAULT 'onsite',
    billable TINYINT(1) NOT NULL DEFAULT 1,
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_work_sessions_job FOREIGN KEY (job_id) REFERENCES work_jobs(id) ON DELETE CASCADE,
    CONSTRAINT fk_work_sessions_worker FOREIGN KEY (worker_id) REFERENCES work_workers(id) ON DELETE SET NULL,
    INDEX idx_work_sessions_job_time (job_id, started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS work_materials (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_id INT UNSIGNED NOT NULL,
    purchased_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    description VARCHAR(255) NOT NULL,
    supplier VARCHAR(150) NULL,
    cost DECIMAL(10,2) NOT NULL DEFAULT 0,
    paid_by ENUM('mike','customer','other') NOT NULL DEFAULT 'mike',
    receipt_path VARCHAR(255) NULL,
    notes TEXT NULL,
    CONSTRAINT fk_work_materials_job FOREIGN KEY (job_id) REFERENCES work_jobs(id) ON DELETE CASCADE,
    INDEX idx_work_materials_job (job_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS work_reports (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_id INT UNSIGNED NOT NULL,
    report_date DATE NOT NULL,
    work_summary TEXT NOT NULL,
    issues_summary TEXT NULL,
    next_steps TEXT NULL,
    labour_total DECIMAL(10,2) NOT NULL DEFAULT 0,
    materials_total DECIMAL(10,2) NOT NULL DEFAULT 0,
    job_total_to_date DECIMAL(10,2) NOT NULL DEFAULT 0,
    payments_to_date DECIMAL(10,2) NOT NULL DEFAULT 0,
    outstanding_balance DECIMAL(10,2) NOT NULL DEFAULT 0,
    customer_acknowledged_at DATETIME NULL,
    customer_comment TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_job_report_date (job_id, report_date),
    CONSTRAINT fk_work_reports_job FOREIGN KEY (job_id) REFERENCES work_jobs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS work_job_photos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_id INT UNSIGNED NOT NULL,
    report_id INT UNSIGNED NULL,
    file_path VARCHAR(255) NOT NULL,
    caption VARCHAR(255) NULL,
    taken_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_work_photos_job FOREIGN KEY (job_id) REFERENCES work_jobs(id) ON DELETE CASCADE,
    CONSTRAINT fk_work_photos_report FOREIGN KEY (report_id) REFERENCES work_reports(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS work_sms_messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_id INT UNSIGNED NULL,
    direction ENUM('outbound','inbound') NOT NULL,
    phone VARCHAR(30) NOT NULL,
    message TEXT NOT NULL,
    purpose VARCHAR(50) NULL,
    local_ref VARCHAR(80) NULL,
    provider_ref VARCHAR(100) NULL,
    delivery_status VARCHAR(50) NULL,
    sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    received_at DATETIME NULL,
    raw_payload TEXT NULL,
    CONSTRAINT fk_work_sms_job FOREIGN KEY (job_id) REFERENCES work_jobs(id) ON DELETE SET NULL,
    INDEX idx_work_sms_job (job_id),
    INDEX idx_work_sms_phone (phone),
    INDEX idx_work_sms_ref (local_ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS work_payments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_id INT UNSIGNED NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_type ENUM('progress','final','deposit','other') NOT NULL DEFAULT 'progress',
    method VARCHAR(50) NULL,
    notes TEXT NULL,
    paid_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_work_payments_job FOREIGN KEY (job_id) REFERENCES work_jobs(id) ON DELETE CASCADE,
    INDEX idx_work_payments_job (job_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
