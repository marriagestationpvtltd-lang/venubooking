-- Migration: Add site_images table for dynamic image management
-- This allows admin to upload images and assign them to different sections

CREATE TABLE IF NOT EXISTS site_images (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    post_group VARCHAR(255) NULL DEFAULT NULL COMMENT 'Groups work_photos into posts; photos sharing the same post_group appear in one card',
    image_path VARCHAR(255) NOT NULL,
    section VARCHAR(100) NOT NULL,
    display_order INT DEFAULT 0,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_section (section),
    INDEX idx_status (status),
    INDEX idx_display_order (display_order),
    INDEX idx_post_group (post_group)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert some example sections for reference
-- Sections can be: banner, venue, package, gallery, testimonial, etc.
