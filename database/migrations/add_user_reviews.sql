-- Migration: Add user_reviews table for token-based review submission
-- Run this script on existing installations to enable the user review feature.

CREATE TABLE IF NOT EXISTS user_reviews (
    id INT PRIMARY KEY AUTO_INCREMENT,
    booking_id INT NULL COMMENT 'FK → bookings.id; nullable for non-booking reviews',
    token VARCHAR(64) NOT NULL UNIQUE COMMENT 'Secure random token for review link',
    reviewer_name VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'Name entered by reviewer',
    reviewer_email VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'Email entered by reviewer',
    rating TINYINT NOT NULL DEFAULT 5 COMMENT '1–5 star rating',
    review_text TEXT NOT NULL DEFAULT '' COMMENT 'Review body text',
    submitted TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0 = token issued only; 1 = review submitted',
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending' COMMENT 'Admin moderation status',
    admin_note TEXT COMMENT 'Internal note from admin (not shown publicly)',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_token (token),
    INDEX idx_booking_id (booking_id),
    INDEX idx_status (status),
    INDEX idx_submitted (submitted),
    CONSTRAINT fk_user_reviews_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='User-submitted reviews via token links';
