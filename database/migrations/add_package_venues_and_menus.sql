-- Migration: Add package_venues and package_menus tables
-- These tables allow admins to associate specific halls (venues) and menus
-- with a service package, enabling a direct package booking flow.

-- Table: package_venues
-- Links a service package to one or more halls (with their parent venues).
CREATE TABLE IF NOT EXISTS package_venues (
    id         INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    package_id INT          NOT NULL,
    hall_id    INT          NOT NULL,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_package_hall (package_id, hall_id),
    INDEX  idx_pv_package_id (package_id),
    INDEX  idx_pv_hall_id    (hall_id),
    CONSTRAINT fk_pv_package FOREIGN KEY (package_id) REFERENCES service_packages(id) ON DELETE CASCADE,
    CONSTRAINT fk_pv_hall    FOREIGN KEY (hall_id)    REFERENCES halls(id)            ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: package_menus
-- Links a service package to one or more menus.
CREATE TABLE IF NOT EXISTS package_menus (
    id         INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    package_id INT          NOT NULL,
    menu_id    INT          NOT NULL,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_package_menu (package_id, menu_id),
    INDEX  idx_pm_package_id (package_id),
    INDEX  idx_pm_menu_id    (menu_id),
    CONSTRAINT fk_pm_package FOREIGN KEY (package_id) REFERENCES service_packages(id) ON DELETE CASCADE,
    CONSTRAINT fk_pm_menu    FOREIGN KEY (menu_id)    REFERENCES menus(id)            ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
