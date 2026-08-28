CREATE TABLE IF NOT EXISTS work_daily_plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_id INT NOT NULL,
    plan_date DATE NOT NULL,
    planned_start_time TIME NULL,
    planned_finish_time TIME NULL,
    anticipated_job_hours_low DECIMAL(5,2) NULL,
    anticipated_job_hours_high DECIMAL(5,2) NULL,
    expected_worker_count INT NOT NULL DEFAULT 1,
    expected_workers_text VARCHAR(255) NULL,
    helper_roles VARCHAR(255) NULL,
    planned_interruptions TEXT NULL,
    overall_plan_note TEXT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_work_daily_plan_job_date (job_id, plan_date),
    KEY idx_work_daily_plans_job (job_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS work_complimentary_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_id INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    item_type ENUM('labour','material','repair','improvement','other') NOT NULL DEFAULT 'other',
    description TEXT NOT NULL,
    estimated_value DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    note VARCHAR(255) NULL,
    KEY idx_work_complimentary_job (job_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
