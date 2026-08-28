ALTER TABLE work_jobs
    ADD COLUMN customer_update_mode ENUM(
        'full_transparency',
        'important_only',
        'daily_only',
        'none'
    ) NOT NULL DEFAULT 'full_transparency'
    AFTER payment_mode;
