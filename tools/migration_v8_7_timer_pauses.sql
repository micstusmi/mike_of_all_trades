CREATE TABLE IF NOT EXISTS work_session_breaks (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    session_id BIGINT UNSIGNED NOT NULL,
    started_at DATETIME NOT NULL,
    ended_at DATETIME NULL,
    reason VARCHAR(50) NOT NULL DEFAULT 'personal_break',
    note VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_wsb_session (session_id),
    KEY idx_wsb_open (session_id, ended_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
