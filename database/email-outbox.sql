-- Migración aditiva para instalaciones existentes; no modifica participaciones previas.
CREATE TABLE IF NOT EXISTS quiz_email_outbox (
    submission_id BIGINT UNSIGNED NOT NULL,
    recipient VARCHAR(254) NOT NULL,
    payload_json JSON NOT NULL,
    message_id VARCHAR(160) CHARACTER SET ascii NOT NULL,
    status ENUM('pending','sending','sent','failed','captured') NOT NULL DEFAULT 'pending',
    attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    next_attempt_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    locked_at DATETIME NULL,
    lock_token CHAR(32) CHARACTER SET ascii NULL,
    last_error_code VARCHAR(64) NULL,
    sent_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (submission_id),
    UNIQUE KEY unique_message (message_id),
    KEY by_pending (status, next_attempt_at),
    CONSTRAINT outbox_submission FOREIGN KEY (submission_id) REFERENCES quiz_submissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
