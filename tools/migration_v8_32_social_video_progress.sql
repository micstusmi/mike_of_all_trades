-- V8.32: observable social-video rendering progress and worker heartbeat.
-- Run once on an existing installation.

ALTER TABLE work_social_videos
  ADD COLUMN progress_percent TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER completed_at,
  ADD COLUMN progress_stage VARCHAR(60) NULL AFTER progress_percent,
  ADD COLUMN progress_detail VARCHAR(255) NULL AFTER progress_stage,
  ADD COLUMN progress_current INT UNSIGNED NULL AFTER progress_detail,
  ADD COLUMN progress_total INT UNSIGNED NULL AFTER progress_current,
  ADD COLUMN estimated_seconds INT UNSIGNED NULL AFTER progress_total,
  ADD COLUMN heartbeat_at DATETIME NULL AFTER estimated_seconds;
