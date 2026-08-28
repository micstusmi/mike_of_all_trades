ALTER TABLE work_jobs
    ADD COLUMN original_pricing_type ENUM('fixed_price','estimate','hourly','no_price','unspecified')
        NOT NULL DEFAULT 'unspecified'
        AFTER original_scope,
    ADD COLUMN agreement_version VARCHAR(20) NULL AFTER agreement_ip,
    ADD COLUMN acknowledged_current_balance TINYINT(1) NOT NULL DEFAULT 0 AFTER agreement_version,
    ADD COLUMN authorised_continuation TINYINT(1) NOT NULL DEFAULT 0 AFTER acknowledged_current_balance,
    ADD COLUMN agreement_snapshot_json LONGTEXT NULL AFTER authorised_continuation;
