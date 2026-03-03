-- Migration: Add venue_images table and map_link column to venues
-- Run this migration to enable multi-photo upload for venues and Google Map links.

-- Create venue_images table (mirrors hall_images)
CREATE TABLE IF NOT EXISTS venue_images (
    id INT PRIMARY KEY AUTO_INCREMENT,
    venue_id INT NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    is_primary BOOLEAN DEFAULT 0,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (venue_id) REFERENCES venues(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add Google Map link column to venues (optional field)
ALTER TABLE venues ADD COLUMN IF NOT EXISTS map_link VARCHAR(500) DEFAULT NULL AFTER contact_email;
