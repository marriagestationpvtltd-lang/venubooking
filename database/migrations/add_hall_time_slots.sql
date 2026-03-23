-- Migration: Add hall_time_slots table for time-based booking
-- Run this migration to enable per-hall time schedule management

CREATE TABLE IF NOT EXISTS hall_time_slots (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    hall_id INT NOT NULL,
    slot_name VARCHAR(100) NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    price_override DECIMAL(10,2) DEFAULT NULL COMMENT 'NULL = use hall base_price',
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_hts_hall FOREIGN KEY (hall_id) REFERENCES halls(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_hts_hall_status ON hall_time_slots (hall_id, status);
