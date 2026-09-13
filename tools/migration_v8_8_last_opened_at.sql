/*
 * Work Tracker V8.8
 *
 * Record the most recently opened admin job without treating the
 * action as a content edit.
 */

ALTER TABLE work_jobs
    ADD COLUMN IF NOT EXISTS last_opened_at DATETIME NULL
        AFTER updated_at;

CREATE INDEX IF NOT EXISTS idx_work_jobs_last_opened
    ON work_jobs (last_opened_at);
