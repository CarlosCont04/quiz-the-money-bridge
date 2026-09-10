-- MySQL 8+ / MariaDB 10.4+ (XAMPP). Ejecutar en la base quiz_money_bridge.
CREATE TABLE IF NOT EXISTS quiz_submissions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    request_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(254) NOT NULL,
    total_score TINYINT UNSIGNED NOT NULL,
    result_key ENUM('red', 'yellow', 'green') NOT NULL,
    result_json JSON NOT NULL,
    quiz_version VARCHAR(40) NOT NULL,
    consent_version VARCHAR(40) NOT NULL,
    consent_at DATETIME NOT NULL,
    payload_hash CHAR(64) CHARACTER SET ascii NOT NULL,
    session_hash CHAR(64) CHARACTER SET ascii NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY unique_request (request_id),
    KEY by_email (email),
    KEY by_date (created_at),
    CONSTRAINT valid_score CHECK (total_score BETWEEN 0 AND 24),
    CONSTRAINT valid_result CHECK ((total_score BETWEEN 0 AND 8 AND result_key = 'red') OR (total_score BETWEEN 9 AND 16 AND result_key = 'yellow') OR (total_score BETWEEN 17 AND 24 AND result_key = 'green'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS quiz_answers (
    submission_id BIGINT UNSIGNED NOT NULL,
    question_id TINYINT UNSIGNED NOT NULL,
    answer ENUM('A', 'B', 'C') NOT NULL,
    points TINYINT UNSIGNED NOT NULL,
    PRIMARY KEY (submission_id, question_id),
    CONSTRAINT answers_submission FOREIGN KEY (submission_id) REFERENCES quiz_submissions(id) ON DELETE CASCADE,
    CONSTRAINT valid_question CHECK (question_id BETWEEN 1 AND 12),
    CONSTRAINT valid_points CHECK ((answer = 'A' AND points = 0) OR (answer = 'B' AND points = 1) OR (answer = 'C' AND points = 2))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
