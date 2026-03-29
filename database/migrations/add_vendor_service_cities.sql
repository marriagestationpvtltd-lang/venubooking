-- Migration: Add vendor_service_cities junction table
-- Vendors can now be associated with multiple service cities (cities where they operate).
-- Existing city_id data is migrated to the new table so no data is lost.

CREATE TABLE IF NOT EXISTS vendor_service_cities (
    id INT PRIMARY KEY AUTO_INCREMENT,
    vendor_id INT NOT NULL,
    city_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_vendor_city (vendor_id, city_id),
    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE,
    FOREIGN KEY (city_id) REFERENCES cities(id) ON DELETE CASCADE,
    INDEX idx_vsc_vendor_id (vendor_id),
    INDEX idx_vsc_city_id (city_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Migrate existing single city_id values into the new junction table
INSERT IGNORE INTO vendor_service_cities (vendor_id, city_id)
SELECT id, city_id FROM vendors WHERE city_id IS NOT NULL;
