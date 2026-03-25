-- New tables for custom menu structure
CREATE TABLE IF NOT EXISTS menu_sections (
    id INT PRIMARY KEY AUTO_INCREMENT,
    menu_id INT NOT NULL,
    section_name VARCHAR(255) NOT NULL,
    choose_limit INT DEFAULT NULL COMMENT 'NULL = no section-level limit, use group limits',
    display_order INT NOT NULL DEFAULT 0,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (menu_id) REFERENCES menus(id) ON DELETE CASCADE,
    INDEX idx_menu_sections_menu_id (menu_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS menu_groups (
    id INT PRIMARY KEY AUTO_INCREMENT,
    menu_section_id INT NOT NULL,
    group_name VARCHAR(255) NOT NULL,
    choose_limit INT DEFAULT NULL COMMENT 'NULL = inherit from section, >0 = per-group limit',
    display_order INT NOT NULL DEFAULT 0,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (menu_section_id) REFERENCES menu_sections(id) ON DELETE CASCADE,
    INDEX idx_menu_groups_section_id (menu_section_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS menu_group_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    menu_group_id INT NOT NULL,
    item_name VARCHAR(255) NOT NULL,
    sub_category VARCHAR(255) DEFAULT NULL COMMENT 'Display-only label, e.g. Paneer Snacks',
    extra_charge DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '0 = included in base price',
    display_order INT NOT NULL DEFAULT 0,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (menu_group_id) REFERENCES menu_groups(id) ON DELETE CASCADE,
    INDEX idx_menu_group_items_group_id (menu_group_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Snapshot of selected menu items at booking time (NEVER changed after booking)
CREATE TABLE IF NOT EXISTS booking_menu_item_selections (
    id INT PRIMARY KEY AUTO_INCREMENT,
    booking_id INT NOT NULL,
    menu_id INT NOT NULL,
    menu_name VARCHAR(255) NOT NULL,
    section_name VARCHAR(255) NOT NULL,
    group_name VARCHAR(255) NOT NULL,
    item_name VARCHAR(255) NOT NULL,
    sub_category VARCHAR(255) DEFAULT NULL,
    extra_charge DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    menu_section_id INT DEFAULT NULL,
    menu_group_id INT DEFAULT NULL,
    menu_item_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    INDEX idx_bmis_booking_id (booking_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add extra_total to booking_menus for custom item extra charges
ALTER TABLE booking_menus
    ADD COLUMN IF NOT EXISTS extra_total DECIMAL(10,2) NOT NULL DEFAULT 0.00
        COMMENT 'Sum of extra_charge values from custom item selections for this menu'
    AFTER total_price;

-- Add menu_special_instructions to bookings
ALTER TABLE bookings
    ADD COLUMN IF NOT EXISTS menu_special_instructions TEXT DEFAULT NULL
        COMMENT 'Special instructions for the menu entered during booking'
    AFTER special_requests;
