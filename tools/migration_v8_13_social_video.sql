-- Mike of All Trades Work Tracker V8.13
-- Queued social slideshow videos, captions, voice-over and music.

CREATE TABLE IF NOT EXISTS work_social_videos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  job_id INT NOT NULL,
  platform VARCHAR(30) NOT NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'draft',
  title VARCHAR(255) NOT NULL,
  post_caption TEXT NOT NULL,
  hashtags TEXT NULL,
  voiceover_enabled TINYINT(1) NOT NULL DEFAULT 0,
  voice_name VARCHAR(40) NOT NULL DEFAULT 'alloy',
  music_file VARCHAR(500) NULL,
  image_seconds DECIMAL(4,2) NOT NULL DEFAULT 1.80,
  output_relative_path VARCHAR(500) NULL,
  subtitle_relative_path VARCHAR(500) NULL,
  duration_seconds DECIMAL(8,2) NULL,
  error_message TEXT NULL,
  ai_model VARCHAR(100) NULL,
  raw_plan LONGTEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  started_at DATETIME NULL,
  completed_at DATETIME NULL,
  KEY idx_social_video_job (job_id,id),
  KEY idx_social_video_status (status,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS work_social_video_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  video_id INT NOT NULL,
  photo_id INT NOT NULL,
  sort_order INT NOT NULL,
  screen_caption VARCHAR(220) NOT NULL,
  narration_text VARCHAR(500) NULL,
  duration_seconds DECIMAL(5,2) NOT NULL DEFAULT 1.80,
  KEY idx_social_video_items (video_id,sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
