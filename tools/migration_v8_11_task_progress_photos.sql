-- Mike of All Trades Work Tracker V8.11
-- Allow photos to document every intermediate task stage, not only before/after.
ALTER TABLE work_task_photos
  MODIFY photo_type ENUM('before','progress','after') NOT NULL;
