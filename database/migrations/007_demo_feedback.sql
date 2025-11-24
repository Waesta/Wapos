-- =====================================================
-- WAPOS Migration 007: Demo Feedback table
-- Captures tester comments during focus-group sessions.
-- =====================================================

CREATE TABLE IF NOT EXISTS demo_feedback (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    context_page VARCHAR(255) NULL,
    rating TINYINT UNSIGNED NULL,
    contact VARCHAR(120) NULL,
    comments TEXT NOT NULL,
    metadata JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created_at (created_at),
    INDEX idx_user (user_id),
    CONSTRAINT fk_demo_feedback_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO migrations (migration_name) VALUES ('007_demo_feedback');
