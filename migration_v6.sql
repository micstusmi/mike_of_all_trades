-- Work Tracker V6: retrospective sessions + travel/ETA tracking
-- Run once after deploying the V6 PHP files.

ALTER TABLE work_sessions
    ADD COLUMN session_source ENUM('live','retrospective') NOT NULL DEFAULT 'live' AFTER ended_at,
    ADD COLUMN retrospective_entered_at DATETIME NULL AFTER session_source,
    ADD COLUMN travel_eta DATETIME NULL AFTER retrospective_entered_at;

CREATE INDEX idx_work_sessions_job_source_started
    ON work_sessions (job_id, session_source, started_at);
