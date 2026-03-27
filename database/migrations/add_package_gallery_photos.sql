-- Migration: Link general gallery photos (site_images) to service packages
-- Allows admin to associate existing gallery photos with a package so users
-- can see those photos on the package detail and listing pages.

CREATE TABLE IF NOT EXISTS package_gallery_photos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    package_id INT NOT NULL,
    site_image_id INT NOT NULL,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_package_image (package_id, site_image_id),
    FOREIGN KEY (package_id) REFERENCES service_packages(id) ON DELETE CASCADE,
    FOREIGN KEY (site_image_id) REFERENCES site_images(id) ON DELETE CASCADE,
    INDEX idx_pgp_package_id (package_id),
    INDEX idx_pgp_site_image_id (site_image_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
