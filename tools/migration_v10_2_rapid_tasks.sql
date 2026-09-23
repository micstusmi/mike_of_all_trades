CREATE TABLE IF NOT EXISTS work_task_creation_requests (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    request_token VARCHAR(64) NOT NULL,
    job_id INT UNSIGNED NOT NULL,
    created_by_user_id INT UNSIGNED NULL,
    task_ids_json LONGTEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_work_task_creation_request_token (request_token),
    KEY idx_work_task_creation_request_job (job_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
