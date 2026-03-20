-- ============================================================================
-- Migration: Add service sub-services and designs for visual selection flow
-- Description: Supports step-by-step photo-based design selection within
--              additional services (e.g., Decoration → Mandap → Design Photo)
-- ============================================================================

-- TABLE: service_sub_services
-- Sub-services grouped under an additional service (e.g., Mandap, Stage under Decoration)
CREATE TABLE IF NOT EXISTS service_sub_services (
    id INT PRIMARY KEY AUTO_INCREMENT,
    service_id INT NOT NULL COMMENT 'References additional_services.id',
    name VARCHAR(255) NOT NULL,
    description TEXT,
    display_order INT DEFAULT 0,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (service_id) REFERENCES additional_services(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Sub-services within an additional service for visual selection flow';

-- TABLE: service_designs
-- Design options for each sub-service, each with a photo, name, and price
CREATE TABLE IF NOT EXISTS service_designs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    sub_service_id INT NOT NULL COMMENT 'References service_sub_services.id',
    name VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    photo VARCHAR(255) COMMENT 'Filename in uploads/ directory',
    display_order INT DEFAULT 0,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (sub_service_id) REFERENCES service_sub_services(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Design photos and prices for each sub-service';

-- Add columns to booking_services to track which sub-service design was chosen.
-- Run each ALTER TABLE only if the column does not already exist (upgrade safety).
SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'booking_services'
      AND COLUMN_NAME = 'sub_service_id'
);
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE booking_services ADD COLUMN sub_service_id INT DEFAULT NULL COMMENT ''Sub-service this design belongs to''',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists2 = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'booking_services'
      AND COLUMN_NAME = 'design_id'
);
SET @sql2 = IF(@col_exists2 = 0,
    'ALTER TABLE booking_services ADD COLUMN design_id INT DEFAULT NULL COMMENT ''Selected design from service_designs''',
    'SELECT 1'
);
PREPARE stmt2 FROM @sql2; EXECUTE stmt2; DEALLOCATE PREPARE stmt2;
