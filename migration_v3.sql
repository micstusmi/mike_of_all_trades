ALTER TABLE work_jobs
    ADD COLUMN variation_required TINYINT(1) NOT NULL DEFAULT 0 AFTER unforeseen_conditions,
    ADD COLUMN variation_description TEXT NULL AFTER variation_required,
    ADD COLUMN variation_pricing_method ENUM('fixed_amount','hourly','estimate','not_applicable')
        NOT NULL DEFAULT 'not_applicable' AFTER variation_description,
    ADD COLUMN variation_fixed_amount DECIMAL(10,2) NULL AFTER variation_pricing_method,
    ADD COLUMN variation_hourly_rate DECIMAL(10,2) NULL AFTER variation_fixed_amount,
    ADD COLUMN variation_forecast_low DECIMAL(10,2) NULL AFTER variation_hourly_rate,
    ADD COLUMN variation_forecast_high DECIMAL(10,2) NULL AFTER variation_forecast_low,
    ADD COLUMN variation_authorised TINYINT(1) NOT NULL DEFAULT 0 AFTER authorised_continuation,
    ADD COLUMN variation_authorised_at DATETIME NULL AFTER variation_authorised;
