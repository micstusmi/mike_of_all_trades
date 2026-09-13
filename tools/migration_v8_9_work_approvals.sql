CREATE TABLE IF NOT EXISTS work_approval_requests (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    job_id INT UNSIGNED NOT NULL,
    task_id BIGINT UNSIGNED NULL,
    source_change_request_id BIGINT UNSIGNED NULL,

    request_type ENUM(
        'job_agreement',
        'variation',
        'additional_work',
        'acknowledgement'
    ) NOT NULL DEFAULT 'additional_work',

    approval_required TINYINT(1) NOT NULL DEFAULT 1,
    work_hold_required TINYINT(1) NOT NULL DEFAULT 1,

    status ENUM(
        'draft',
        'awaiting',
        'sent',
        'snoozed',
        'approved',
        'declined',
        'expired',
        'cancelled'
    ) NOT NULL DEFAULT 'draft',

    subject VARCHAR(255) NOT NULL,
    request_text TEXT NOT NULL,

    estimated_hours_low DECIMAL(10,2) NULL,
    estimated_hours_high DECIMAL(10,2) NULL,

    estimated_amount_low DECIMAL(10,2) NULL,
    estimated_amount_high DECIMAL(10,2) NULL,

    customer_name_snapshot VARCHAR(180) NULL,
    customer_phone_snapshot VARCHAR(40) NULL,

    terms_version VARCHAR(40) NULL,
    terms_url VARCHAR(500) NULL,

    urgency ENUM(
        'normal',
        'time_sensitive'
    ) NOT NULL DEFAULT 'normal',

    reminder_schedule_json TEXT NULL,
    reminder_count INT UNSIGNED NOT NULL DEFAULT 0,
    max_reminders INT UNSIGNED NOT NULL DEFAULT 3,

    first_sent_at DATETIME NULL,
    last_sent_at DATETIME NULL,
    next_reminder_at DATETIME NULL,
    snoozed_until DATETIME NULL,
    deadline_at DATETIME NULL,

    approved_at DATETIME NULL,
    declined_at DATETIME NULL,
    expired_at DATETIME NULL,
    cancelled_at DATETIME NULL,

    response_source ENUM(
        'sms',
        'web',
        'admin'
    ) NULL,

    response_text TEXT NULL,

    mike_attention_required TINYINT(1) NOT NULL DEFAULT 0,
    mike_notified_at DATETIME NULL,

    approval_snapshot_json LONGTEXT NULL,

    created_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    KEY idx_work_approval_job (
        job_id,
        status,
        id
    ),

    KEY idx_work_approval_task (
        task_id,
        status
    ),

    KEY idx_work_approval_reminder (
        status,
        next_reminder_at
    ),

    KEY idx_work_approval_attention (
        mike_attention_required,
        status
    ),

    CONSTRAINT fk_work_approval_job
        FOREIGN KEY (job_id)
        REFERENCES work_jobs(id)
        ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_general_ci;


CREATE TABLE IF NOT EXISTS work_approval_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    approval_request_id BIGINT UNSIGNED NOT NULL,
    job_id INT UNSIGNED NOT NULL,

    event_type VARCHAR(80) NOT NULL,

    actor_type ENUM(
        'system',
        'customer',
        'mike'
    ) NOT NULL DEFAULT 'system',

    channel ENUM(
        'sms',
        'web',
        'admin',
        'system'
    ) NOT NULL DEFAULT 'system',

    direction ENUM(
        'inbound',
        'outbound',
        'internal'
    ) NOT NULL DEFAULT 'internal',

    message TEXT NULL,
    raw_payload LONGTEXT NULL,
    metadata_json LONGTEXT NULL,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    KEY idx_work_approval_event_request (
        approval_request_id,
        id
    ),

    KEY idx_work_approval_event_job (
        job_id,
        id
    ),

    CONSTRAINT fk_work_approval_event_request
        FOREIGN KEY (approval_request_id)
        REFERENCES work_approval_requests(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_work_approval_event_job
        FOREIGN KEY (job_id)
        REFERENCES work_jobs(id)
        ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_general_ci;
