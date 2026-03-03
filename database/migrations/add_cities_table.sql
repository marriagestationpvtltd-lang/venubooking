-- Migration: Add predefined cities table and link venues to cities
-- Run this migration to enable the predefined city list feature

-- Table: cities
CREATE TABLE IF NOT EXISTS cities (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add city_id to venues (nullable so existing venues are not broken)
ALTER TABLE venues
    ADD COLUMN city_id INT NULL AFTER location,
    ADD CONSTRAINT fk_venues_city FOREIGN KEY (city_id) REFERENCES cities(id) ON DELETE SET NULL;

-- Insert default cities (Nepal)
INSERT IGNORE INTO cities (name, status) VALUES
('Kathmandu', 'active'),
('Pokhara', 'active'),
('Lalitpur (Patan)', 'active'),
('Bhaktapur', 'active'),
('Biratnagar', 'active'),
('Birgunj', 'active'),
('Butwal', 'active'),
('Dharan', 'active'),
('Hetauda', 'active'),
('Itahari', 'active'),
('Janakpur', 'active'),
('Nepalgunj', 'active'),
('Bharatpur', 'active'),
('Dhangadhi', 'active'),
('Tulsipur', 'active');
