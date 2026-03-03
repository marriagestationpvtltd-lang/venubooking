-- Migration: Add vendor_types table and change vendors.type to VARCHAR
-- This allows vendor types to be managed from the admin panel instead of being hardcoded.

-- 1. Create the vendor_types table
CREATE TABLE IF NOT EXISTS vendor_types (
    id INT PRIMARY KEY AUTO_INCREMENT,
    slug VARCHAR(100) NOT NULL UNIQUE COMMENT 'Stored in vendors.type column',
    label VARCHAR(255) NOT NULL COMMENT 'Human-readable display name',
    status ENUM('active', 'inactive') DEFAULT 'active',
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_display_order (display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Seed with the existing hardcoded types
INSERT IGNORE INTO vendor_types (slug, label, display_order) VALUES
    ('pandit',        'Pandit',           1),
    ('photographer',  'Photographer',     2),
    ('videographer',  'Videographer',     3),
    ('baje',          'Baje (Music/Band)', 4),
    ('decoration',    'Decoration',       5),
    ('catering',      'Catering',         6),
    ('other',         'Other',            7);

-- 3. Change vendors.type from ENUM to VARCHAR(100)
ALTER TABLE vendors MODIFY COLUMN type VARCHAR(100) NOT NULL DEFAULT 'other';
