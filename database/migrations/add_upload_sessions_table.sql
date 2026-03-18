-- Migration: add_upload_sessions_table.sql
-- Tracks chunked / resumable upload sessions for folder file uploads.
-- Each row represents one file being uploaded in chunks.

CREATE TABLE IF NOT EXISTS upload_sessions (
    id           VARCHAR(64)  NOT NULL,
    folder_id    INT          NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    file_size    BIGINT       NOT NULL DEFAULT 0,
    total_chunks INT          NOT NULL DEFAULT 1,
    chunks_received TEXT      DEFAULT '[]' COMMENT 'JSON array of received chunk indices',
    temp_path    VARCHAR(500) DEFAULT NULL COMMENT 'Absolute path to temp chunk directory',
    status       ENUM('pending','uploading','assembling','complete','failed') NOT NULL DEFAULT 'pending',
    created_by   INT          DEFAULT NULL,
    created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    INDEX idx_us_folder  (folder_id),
    INDEX idx_us_status  (status),
    INDEX idx_us_created (created_at),

    CONSTRAINT fk_us_folder FOREIGN KEY (folder_id)
        REFERENCES shared_folders(id) ON DELETE CASCADE,
    CONSTRAINT fk_us_user FOREIGN KEY (created_by)
        REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Clean up stale sessions older than 7 days (run as maintenance)
-- DELETE FROM upload_sessions WHERE status != 'complete' AND created_at < NOW() - INTERVAL 7 DAY;
