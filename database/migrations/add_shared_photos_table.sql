-- Migration: Add shared_photos table for photo sharing feature
-- This allows admin to upload photos that users can download via unique links

CREATE TABLE IF NOT EXISTS shared_photos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    image_path VARCHAR(255) NOT NULL,
    download_token VARCHAR(64) NOT NULL UNIQUE COMMENT 'Unique token for download link',
    download_count INT DEFAULT 0 COMMENT 'Number of times this photo has been downloaded',
    max_downloads INT DEFAULT NULL COMMENT 'Maximum allowed downloads, NULL for unlimited',
    expires_at DATETIME DEFAULT NULL COMMENT 'Expiration date for the download link, NULL for never',
    status ENUM('active', 'inactive', 'expired') DEFAULT 'active',
    created_by INT NULL COMMENT 'Admin user who uploaded the photo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_download_token (download_token),
    INDEX idx_status (status),
    INDEX idx_expires_at (expires_at),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
