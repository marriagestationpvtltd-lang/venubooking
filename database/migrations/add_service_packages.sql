-- Migration: Add service_categories, service_packages, and service_package_features tables
-- This supports the fully dynamic, admin-controlled service package system

-- Table: service_categories (e.g., विवाह, पास्नी, व्रतबन्द)
CREATE TABLE IF NOT EXISTS service_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    display_order INT DEFAULT 0,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_display_order (display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: service_packages (packages under each category)
CREATE TABLE IF NOT EXISTS service_packages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    category_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    display_order INT DEFAULT 0,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES service_categories(id) ON DELETE CASCADE,
    INDEX idx_category_id (category_id),
    INDEX idx_status (status),
    INDEX idx_display_order (display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: service_package_features (feature list items per package)
CREATE TABLE IF NOT EXISTS service_package_features (
    id INT PRIMARY KEY AUTO_INCREMENT,
    package_id INT NOT NULL,
    feature_text VARCHAR(500) NOT NULL,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (package_id) REFERENCES service_packages(id) ON DELETE CASCADE,
    INDEX idx_package_id (package_id),
    INDEX idx_display_order (display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Sample service categories
INSERT IGNORE INTO service_categories (name, description, display_order, status) VALUES
('विवाह', 'विवाह समारोहको लागि विशेष प्याकेजहरू', 1, 'active'),
('पास्नी', 'पास्नी (अन्नप्राशन) समारोहको लागि प्याकेजहरू', 2, 'active'),
('व्रतबन्द', 'व्रतबन्द समारोहको लागि प्याकेजहरू', 3, 'active');
