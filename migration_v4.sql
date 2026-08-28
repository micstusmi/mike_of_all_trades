ALTER TABLE work_sessions
    ADD COLUMN start_location VARCHAR(40) NULL AFTER category,
    ADD COLUMN location_detail VARCHAR(150) NULL AFTER start_location,
    ADD COLUMN stop_reason VARCHAR(60) NULL AFTER notes,
    ADD COLUMN stop_note TEXT NULL AFTER stop_reason,
    ADD COLUMN expected_return VARCHAR(150) NULL AFTER stop_note;
