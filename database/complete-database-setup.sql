-- ============================================================================
-- COMPLETE DATABASE SETUP FOR VENUE BOOKING SYSTEM (A to Z)
-- ============================================================================
-- This script provides a COMPLETE A-Z implementation of the database
-- It includes:
--   1. All required tables with proper relationships
--   2. Default admin user (username: admin, password: Admin@123)
--   3. Essential settings and configuration
--   4. Sample data for venues, halls, menus, and services
--   5. Sample bookings including booking #23 for testing
--   6. Payment methods and payment tracking tables
--
-- SAFE TO RE-RUN: This script uses CREATE TABLE IF NOT EXISTS and
-- INSERT IGNORE so it will NOT destroy any existing data. Re-running
-- it on an existing database will only add missing tables and skip
-- rows that already exist.
-- 
-- HOW TO USE:
-- 1. Create a database in your MySQL server or cPanel
-- 2. Select that database before importing this file
-- 3. For command line: mysql -u username -p database_name < database/complete-database-setup.sql
-- 4. For phpMyAdmin: Select your database, then Import → Choose File → complete-database-setup.sql
-- 5. Access admin panel at: /admin/ (username: admin, password: Admin@123)
-- 6. IMPORTANT: Change admin password after first login!
-- ============================================================================

-- NOTE: Make sure you have selected your database before running this script
-- This script does NOT create a database - you must create/select one first


-- ============================================================================
-- TABLE: cities
-- ============================================================================
CREATE TABLE IF NOT EXISTS cities (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- TABLE: venues
-- ============================================================================
CREATE TABLE IF NOT EXISTS venues (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    location VARCHAR(255) NOT NULL,
    city_id INT NULL,
    address TEXT,
    description TEXT,
    image VARCHAR(255),
    contact_phone VARCHAR(20),
    contact_email VARCHAR(100),
    map_link VARCHAR(500) DEFAULT NULL,
    pano_image VARCHAR(255) DEFAULT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (city_id) REFERENCES cities(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- TABLE: venue_images
-- ============================================================================
CREATE TABLE IF NOT EXISTS venue_images (
    id INT PRIMARY KEY AUTO_INCREMENT,
    venue_id INT NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    is_primary BOOLEAN DEFAULT 0,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (venue_id) REFERENCES venues(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- TABLE: halls
-- ============================================================================
CREATE TABLE IF NOT EXISTS halls (
    id INT PRIMARY KEY AUTO_INCREMENT,
    venue_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    capacity INT NOT NULL,
    hall_type ENUM('single', 'multiple') DEFAULT 'single',
    indoor_outdoor ENUM('indoor', 'outdoor', 'both') DEFAULT 'indoor',
    base_price DECIMAL(10, 2) NOT NULL,
    description TEXT,
    features TEXT,
    pano_image VARCHAR(255) DEFAULT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (venue_id) REFERENCES venues(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- TABLE: hall_images
-- ============================================================================
CREATE TABLE IF NOT EXISTS hall_images (
    id INT PRIMARY KEY AUTO_INCREMENT,
    hall_id INT NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    is_primary BOOLEAN DEFAULT 0,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (hall_id) REFERENCES halls(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- TABLE: menus
-- ============================================================================
CREATE TABLE IF NOT EXISTS menus (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    price_per_person DECIMAL(10, 2) NOT NULL,
    image VARCHAR(255),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- TABLE: menu_items
-- ============================================================================
CREATE TABLE IF NOT EXISTS menu_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    menu_id INT NOT NULL,
    item_name VARCHAR(255) NOT NULL,
    category VARCHAR(100),
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (menu_id) REFERENCES menus(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- TABLE: hall_menus (many-to-many relationship)
-- ============================================================================
CREATE TABLE IF NOT EXISTS hall_menus (
    id INT PRIMARY KEY AUTO_INCREMENT,
    hall_id INT NOT NULL,
    menu_id INT NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (hall_id) REFERENCES halls(id) ON DELETE CASCADE,
    FOREIGN KEY (menu_id) REFERENCES menus(id) ON DELETE CASCADE,
    UNIQUE KEY unique_hall_menu (hall_id, menu_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- TABLE: additional_services
-- ============================================================================
CREATE TABLE IF NOT EXISTS additional_services (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL,
    category VARCHAR(100),
    vendor_type_id INT DEFAULT NULL COMMENT 'FK → vendor_types.id; replaces free-text category field',
    photo VARCHAR(255) DEFAULT NULL COMMENT 'Optional service photo filename in uploads/ directory',
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_additional_services_vendor_type_id (vendor_type_id),
    FOREIGN KEY (vendor_type_id) REFERENCES vendor_types(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- TABLE: service_sub_services (sub-services under additional_services for visual selection flow)
-- ============================================================================
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- TABLE: service_designs (design photos and prices for a service or sub-service)
-- ============================================================================
CREATE TABLE IF NOT EXISTS service_designs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    sub_service_id INT DEFAULT NULL COMMENT 'References service_sub_services.id (legacy sub-service flow)',
    service_id INT DEFAULT NULL COMMENT 'References additional_services.id (direct service design flow)',
    name VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    photo VARCHAR(255) COMMENT 'Filename in uploads/ directory',
    display_order INT DEFAULT 0,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (sub_service_id) REFERENCES service_sub_services(id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES additional_services(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- TABLE: service_categories (event types for service packages, e.g. Wedding, Birthday)
-- ============================================================================
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

-- ============================================================================
-- TABLE: service_packages (packages offered under each service category)
-- ============================================================================
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

-- ============================================================================
-- TABLE: service_package_features (feature bullet points per package)
-- ============================================================================
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

-- ============================================================================
-- TABLE: service_package_photos (multiple photos per service package)
-- ============================================================================
CREATE TABLE IF NOT EXISTS service_package_photos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    package_id INT NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (package_id) REFERENCES service_packages(id) ON DELETE CASCADE,
    INDEX idx_package_id (package_id),
    INDEX idx_display_order (display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- TABLE: customers
-- ============================================================================
CREATE TABLE IF NOT EXISTS customers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    full_name VARCHAR(255) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(100),
    address TEXT,
    city VARCHAR(100) NULL,
    loyalty_points INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_phone (phone),
    INDEX idx_email (email),
    INDEX idx_city (city)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- TABLE: bookings
-- ============================================================================
CREATE TABLE IF NOT EXISTS bookings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    booking_number VARCHAR(50) UNIQUE NOT NULL,
    customer_id INT NOT NULL,
    hall_id INT DEFAULT NULL,
    custom_venue_name VARCHAR(255) DEFAULT NULL COMMENT 'Venue name when customer brings own venue (hall_id is NULL)',
    custom_hall_name VARCHAR(255) DEFAULT NULL COMMENT 'Hall/location name when customer brings own venue (hall_id is NULL)',
    event_date DATE NOT NULL,
    start_time TIME DEFAULT NULL,
    end_time TIME DEFAULT NULL,
    shift ENUM('morning', 'afternoon', 'evening', 'fullday') NOT NULL,
    event_type VARCHAR(100) NOT NULL,
    number_of_guests INT NOT NULL,
    hall_price DECIMAL(10, 2) NOT NULL,
    menu_total DECIMAL(10, 2) DEFAULT 0,
    services_total DECIMAL(10, 2) DEFAULT 0,
    subtotal DECIMAL(10, 2) NOT NULL,
    tax_amount DECIMAL(10, 2) DEFAULT 0,
    grand_total DECIMAL(10, 2) NOT NULL,
    special_requests TEXT,
    booking_status ENUM('pending', 'payment_submitted', 'confirmed', 'cancelled', 'completed') DEFAULT 'pending',
    payment_status ENUM('pending', 'partial', 'paid', 'cancelled') DEFAULT 'pending',
    advance_payment_received TINYINT(1) DEFAULT 0 COMMENT 'Whether advance payment has been received (0=No, 1=Yes)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (hall_id) REFERENCES halls(id) ON DELETE SET NULL,
    INDEX idx_event_date (event_date),
    INDEX idx_booking_number (booking_number),
    INDEX idx_status (booking_status),
    INDEX idx_advance_payment_received (advance_payment_received)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- TABLE: booking_menus (link bookings with selected menus)
-- ============================================================================
CREATE TABLE IF NOT EXISTS booking_menus (
    id INT PRIMARY KEY AUTO_INCREMENT,
    booking_id INT NOT NULL,
    menu_id INT NOT NULL,
    price_per_person DECIMAL(10, 2) NOT NULL,
    number_of_guests INT NOT NULL,
    total_price DECIMAL(10, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (menu_id) REFERENCES menus(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- TABLE: booking_services (link bookings with selected services)
-- ============================================================================
CREATE TABLE IF NOT EXISTS booking_services (
    id INT PRIMARY KEY AUTO_INCREMENT,
    booking_id INT NOT NULL,
    service_id INT NOT NULL DEFAULT 0 COMMENT '0 for admin services, >0 for user services referencing additional_services',
    service_name VARCHAR(255) NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    description TEXT,
    category VARCHAR(100),
    added_by ENUM('user', 'admin') DEFAULT 'user' COMMENT 'Who added the service: user during booking or admin later',
    quantity INT DEFAULT 1 COMMENT 'Quantity of service',
    sub_service_id INT DEFAULT NULL COMMENT 'References service_sub_services.id if this is a design selection',
    design_id INT DEFAULT NULL COMMENT 'References service_designs.id if this is a design selection',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    INDEX idx_booking_services_added_by (added_by),
    INDEX idx_booking_services_service_id (service_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- TABLE: payment_methods
-- ============================================================================
CREATE TABLE IF NOT EXISTS payment_methods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    qr_code VARCHAR(255),
    bank_details TEXT,
    status ENUM('active','inactive') DEFAULT 'active',
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_display_order (display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- TABLE: booking_payment_methods (junction table)
-- ============================================================================
CREATE TABLE IF NOT EXISTS booking_payment_methods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    payment_method_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (payment_method_id) REFERENCES payment_methods(id) ON DELETE CASCADE,
    UNIQUE KEY unique_booking_payment_method (booking_id, payment_method_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- TABLE: payments (track payment transactions)
-- ============================================================================
CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    payment_method_id INT,
    transaction_id VARCHAR(255),
    paid_amount DECIMAL(10, 2) NOT NULL,
    payment_slip VARCHAR(255),
    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    payment_status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (payment_method_id) REFERENCES payment_methods(id) ON DELETE SET NULL,
    INDEX idx_booking_id (booking_id),
    INDEX idx_payment_status (payment_status),
    INDEX idx_payment_date (payment_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- TABLE: users (admin users)
-- ============================================================================
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(100),
    role ENUM('admin', 'manager', 'staff') DEFAULT 'staff',
    status ENUM('active', 'inactive') DEFAULT 'active',
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- TABLE: settings
-- ============================================================================
CREATE TABLE IF NOT EXISTS settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- TABLE: activity_logs
-- ============================================================================
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(255) NOT NULL,
    table_name VARCHAR(100),
    record_id INT,
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- TABLE: login_attempts (persistent brute-force protection)
-- ============================================================================
CREATE TABLE IF NOT EXISTS login_attempts (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ip_address   VARCHAR(45)     NOT NULL COMMENT 'IPv4 or IPv6 address of the client',
    success      TINYINT(1)      NOT NULL DEFAULT 0 COMMENT '0 = failed, 1 = successful login',
    attempted_at TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_ip_attempted (ip_address, attempted_at),
    INDEX idx_attempted_at (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='Tracks login attempts for IP-based brute-force protection';
CREATE TABLE IF NOT EXISTS site_images (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    image_path VARCHAR(255) NOT NULL,
    section VARCHAR(100) NOT NULL,
    card_id INT NOT NULL DEFAULT 1 COMMENT 'Groups photos into cards of max 10 per section',
    event_category VARCHAR(150) DEFAULT NULL COMMENT 'Event category folder for work_photos section (e.g. Wedding Photos)',
    display_order INT DEFAULT 0,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_section (section),
    INDEX idx_card_id (card_id),
    INDEX idx_event_category (event_category),
    INDEX idx_status (status),
    INDEX idx_display_order (display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- TABLE: vendor_types (admin-managed vendor type definitions)
-- ============================================================================
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

-- ============================================================================
-- TABLE: vendors (service providers assigned to bookings)
-- ============================================================================
CREATE TABLE IF NOT EXISTS vendors (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    type VARCHAR(100) NOT NULL DEFAULT 'other',
    short_description VARCHAR(500) DEFAULT NULL,
    phone VARCHAR(20),
    email VARCHAR(100),
    address TEXT,
    city_id INT NULL,
    photo VARCHAR(255) DEFAULT NULL,
    notes TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (city_id) REFERENCES cities(id) ON DELETE SET NULL,
    INDEX idx_type (type),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- TABLE: vendor_photos (multiple photos per vendor)
-- ============================================================================
CREATE TABLE IF NOT EXISTS vendor_photos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    vendor_id INT NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    is_primary BOOLEAN DEFAULT 0,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE,
    INDEX idx_vendor_id (vendor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- TABLE: booking_vendor_assignments (assigns vendors to specific tasks within a booking)
-- ============================================================================
CREATE TABLE IF NOT EXISTS booking_vendor_assignments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    booking_id INT NOT NULL,
    booking_service_id INT DEFAULT NULL COMMENT 'FK → booking_services.id; links assignment to a specific booking service row',
    vendor_id INT NOT NULL,
    task_description VARCHAR(255) NOT NULL COMMENT 'What the vendor will do for this booking',
    assigned_amount DECIMAL(10, 2) DEFAULT 0.00 COMMENT 'Amount to be paid to vendor',
    notes TEXT,
    status ENUM('assigned', 'confirmed', 'completed', 'cancelled') DEFAULT 'assigned',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE RESTRICT,
    INDEX idx_booking_id (booking_id),
    INDEX idx_bva_booking_service_id (booking_service_id),
    INDEX idx_vendor_id (vendor_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS shared_folders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    folder_name VARCHAR(255) NOT NULL COMMENT 'Display name for the folder',
    description TEXT NULL COMMENT 'Optional description of folder contents',
    download_token VARCHAR(64) NOT NULL UNIQUE COMMENT 'Unique token for shareable folder link',
    photo_count INT DEFAULT 0 COMMENT 'Cached count of photos in folder',
    total_downloads INT DEFAULT 0 COMMENT 'Total download count across all photos',
    max_downloads INT DEFAULT NULL COMMENT 'Maximum allowed downloads per photo, NULL for unlimited',
    expires_at DATETIME DEFAULT NULL COMMENT 'Folder expiration date, NULL for never',
    status ENUM('active', 'inactive', 'expired') DEFAULT 'active',
    allow_zip_download TINYINT(1) DEFAULT 1 COMMENT 'Allow downloading all photos as ZIP',
    show_preview TINYINT(1) DEFAULT 1 COMMENT 'Show photo previews to users. If 0, only ZIP download is shown',
    created_by INT NULL COMMENT 'Admin user who created the folder',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_download_token (download_token),
    INDEX idx_status (status),
    INDEX idx_expires_at (expires_at),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS shared_photos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    folder_id INT NULL COMMENT 'Folder this file belongs to, NULL for standalone file',
    subfolder_name VARCHAR(255) NULL DEFAULT NULL COMMENT 'Album/sub-folder name for grouping photos within a shared folder',
    file_type ENUM('photo', 'video', 'file') DEFAULT 'photo' COMMENT 'Type of file: photo, video, or generic file',
    title VARCHAR(255) NOT NULL,
    description TEXT,
    image_path VARCHAR(255) NOT NULL COMMENT 'Relative path to the file (photo or video)',
    file_size BIGINT UNSIGNED DEFAULT NULL COMMENT 'File size in bytes, important for large video files',
    thumbnail_path VARCHAR(255) DEFAULT NULL COMMENT 'Relative path to auto-generated preview thumbnail, NULL if not generated',
    download_token VARCHAR(64) NOT NULL UNIQUE COMMENT 'Unique token for download link',
    download_count INT DEFAULT 0 COMMENT 'Number of times this file has been downloaded',
    max_downloads INT DEFAULT NULL COMMENT 'Maximum allowed downloads, NULL for unlimited',
    expires_at DATETIME DEFAULT NULL COMMENT 'Expiration date for the download link, NULL for never',
    status ENUM('active', 'inactive', 'expired') DEFAULT 'active',
    created_by INT NULL COMMENT 'Admin user who uploaded the file',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (folder_id) REFERENCES shared_folders(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_folder_id (folder_id),
    INDEX idx_subfolder_name (subfolder_name),
    INDEX idx_file_type (file_type),
    INDEX idx_download_token (download_token),
    INDEX idx_status (status),
    INDEX idx_expires_at (expires_at),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- PLANNER SYSTEM TABLES
-- ============================================================================

CREATE TABLE IF NOT EXISTS event_plans (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    event_type VARCHAR(100) NOT NULL,
    event_date DATE DEFAULT NULL,
    customer_id INT DEFAULT NULL,
    total_budget DECIMAL(12,2) DEFAULT 0,
    description TEXT,
    status ENUM('planning', 'in_progress', 'completed', 'cancelled') DEFAULT 'planning',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_status (status),
    INDEX idx_event_date (event_date),
    INDEX idx_customer_id (customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS plan_tasks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    plan_id INT NOT NULL,
    task_name VARCHAR(255) NOT NULL,
    category VARCHAR(100) DEFAULT 'General',
    description TEXT,
    due_date DATE DEFAULT NULL,
    estimated_cost DECIMAL(10,2) DEFAULT 0,
    actual_cost DECIMAL(10,2) DEFAULT 0,
    status ENUM('pending', 'in_progress', 'completed') DEFAULT 'pending',
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (plan_id) REFERENCES event_plans(id) ON DELETE CASCADE,
    INDEX idx_plan_id (plan_id),
    INDEX idx_status (status),
    INDEX idx_due_date (due_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- TRIGGERS
-- ============================================================================

-- Trigger: Auto-update booking_status and advance_payment_received when payment_status changes
DROP TRIGGER IF EXISTS trg_bookings_payment_status_sync;

DELIMITER $$
CREATE TRIGGER trg_bookings_payment_status_sync
BEFORE UPDATE ON bookings
FOR EACH ROW
BEGIN
    IF NEW.payment_status <> OLD.payment_status THEN
        CASE NEW.payment_status
            WHEN 'pending' THEN
                SET NEW.booking_status          = 'pending';
                SET NEW.advance_payment_received = 0;
            WHEN 'partial' THEN
                SET NEW.booking_status          = 'confirmed';
                SET NEW.advance_payment_received = 1;
            WHEN 'paid' THEN
                SET NEW.booking_status          = 'completed';
                SET NEW.advance_payment_received = 1;
            ELSE
                BEGIN END; -- 'cancelled' or any future status: leave as-is
        END CASE;
    END IF;
END$$
DELIMITER ;

-- Triggers: Keep shared_folders.photo_count accurate when photos are added/removed/moved.
DROP TRIGGER IF EXISTS trg_shared_photos_insert;
DROP TRIGGER IF EXISTS trg_shared_photos_delete;
DROP TRIGGER IF EXISTS trg_shared_photos_update;

DELIMITER $$
CREATE TRIGGER trg_shared_photos_insert
AFTER INSERT ON shared_photos
FOR EACH ROW
BEGIN
    IF NEW.folder_id IS NOT NULL THEN
        UPDATE shared_folders
        SET photo_count = photo_count + 1,
            updated_at  = CURRENT_TIMESTAMP
        WHERE id = NEW.folder_id;
    END IF;
END$$

CREATE TRIGGER trg_shared_photos_delete
AFTER DELETE ON shared_photos
FOR EACH ROW
BEGIN
    IF OLD.folder_id IS NOT NULL THEN
        UPDATE shared_folders
        SET photo_count = GREATEST(0, photo_count - 1),
            updated_at  = CURRENT_TIMESTAMP
        WHERE id = OLD.folder_id;
    END IF;
END$$

CREATE TRIGGER trg_shared_photos_update
AFTER UPDATE ON shared_photos
FOR EACH ROW
BEGIN
    IF OLD.folder_id IS NOT NULL AND (NEW.folder_id IS NULL OR NEW.folder_id != OLD.folder_id) THEN
        UPDATE shared_folders
        SET photo_count = GREATEST(0, photo_count - 1),
            updated_at  = CURRENT_TIMESTAMP
        WHERE id = OLD.folder_id;
    END IF;
    IF NEW.folder_id IS NOT NULL AND (OLD.folder_id IS NULL OR NEW.folder_id != OLD.folder_id) THEN
        UPDATE shared_folders
        SET photo_count = photo_count + 1,
            updated_at  = CURRENT_TIMESTAMP
        WHERE id = NEW.folder_id;
    END IF;
END$$
DELIMITER ;

-- ============================================================================
-- INSERT DEFAULT DATA
-- ============================================================================

-- Insert default admin user (password: Admin@123)
-- ⚠️ SECURITY WARNING: Change this password immediately after installation!
INSERT IGNORE INTO users (username, password, full_name, email, role, status) 
VALUES ('admin', '$2y$10$5sw.gEWePITwobdChuwoRuRT4dtOnxCFf/RMosnL9JVeEeb3teuna', 'System Administrator', 'admin@venubooking.com', 'admin', 'active');
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

-- Insert default vendor types
INSERT IGNORE INTO vendor_types (slug, label, display_order) VALUES
('pandit',       'Pandit',            1),
('photographer', 'Photographer',      2),
('videographer', 'Videographer',      3),
('baje',         'Baje (Music/Band)', 4),
('decoration',   'Decoration',        5),
('catering',     'Catering',          6),
('other',        'Other',             7);

-- Insert default settings
INSERT IGNORE INTO settings (setting_key, setting_value, setting_type) VALUES
('site_name', 'Venue Booking System', 'text'),
('site_logo', '', 'text'),
('contact_email', 'info@venubooking.com', 'text'),
('contact_phone', '+977 1234567890', 'text'),
('contact_address', 'Nepal', 'text'),
('currency', 'NPR', 'text'),
('tax_rate', '13', 'number'),
('advance_payment_percentage', '30', 'number'),
('company_name', 'Venue Booking Company', 'text'),
('company_address', 'Kathmandu, Nepal', 'text'),
('company_phone', '+977 1234567890', 'text'),
('company_email', 'info@venubooking.com', 'text'),
('invoice_title', 'Wedding Booking Confirmation & Partial Payment Receipt', 'text'),
('invoice_package_label', 'Marriage Package', 'text'),
('invoice_additional_items_label', 'Additional Items', 'text'),
('cancellation_policy', 'Advance payment is non-refundable in case of cancellation.
Full payment must be completed 7 days before the event date.
Cancellations made 30 days before the event will receive 50% refund of total amount (excluding advance).
Cancellations made less than 30 days before the event are non-refundable.
Date changes are subject to availability and must be requested at least 15 days in advance.', 'textarea'),
('invoice_disclaimer', 'Note: This is a computer-generated estimate bill. Please create a complete invoice yourself.', 'text'),
('site_favicon', '', 'file'),
('company_logo', '', 'text'),
('footer_about', 'Your perfect venue for every occasion', 'textarea'),
('footer_copyright', '', 'text'),
('meta_title', 'Venue Booking System - Book Your Perfect Event Venue', 'text'),
('meta_description', 'Find and book the ideal venue for your wedding, birthday party, corporate event, or any special occasion.', 'textarea'),
('meta_keywords', 'venue booking, event venue, wedding hall, party hall, corporate events', 'textarea'),
('social_facebook', '', 'url'),
('social_instagram', '', 'url'),
('social_tiktok', '', 'url'),
('social_twitter', '', 'url'),
('social_youtube', '', 'url'),
('social_linkedin', '', 'url'),
('whatsapp_number', '', 'text'),
('contact_map_url', '', 'url'),
('business_hours', '', 'textarea'),
('quick_links', '[{"label":"Home","url":"/index.php","order":1}]', 'json'),
('email_enabled', '1', 'boolean'),
('email_from_name', 'Venue Booking System', 'text'),
('email_from_address', 'noreply@venubooking.com', 'text'),
('admin_email', '', 'text'),
('smtp_enabled', '0', 'boolean'),
('smtp_host', '', 'text'),
('smtp_port', '587', 'number'),
('smtp_username', '', 'text'),
('smtp_password', '', 'password'),
('smtp_encryption', 'tls', 'text'),
('google_review_link', '', 'url'),
('allow_custom_venue', '1', 'boolean'),
-- Booking settings
('booking_min_advance_days', '1', 'number'),
('booking_cancellation_hours', '24', 'number'),
('default_booking_status', 'pending', 'text'),
('enable_online_payment', '0', 'boolean'),
-- Analytics
('google_analytics_id', '', 'text'),
-- Folder page banner ad settings
('folder_banner_a', '', 'image'),
('folder_banner_a_link', '', 'url'),
('folder_banner_a_enabled', '0', 'boolean'),
('folder_banner_b', '', 'image'),
('folder_banner_b_link', '', 'url'),
('folder_banner_b_enabled', '0', 'boolean');

-- Insert Venues
INSERT IGNORE INTO venues (name, location, address, description, image, contact_phone, contact_email) VALUES
('Royal Palace', 'Kathmandu', 'Durbar Marg, Kathmandu', 'Luxury venue in the heart of Kathmandu with traditional architecture and modern amenities.', 'royal-palace.jpg', '+977 1-4234567', 'info@royalpalace.com'),
('Garden View Hall', 'Lalitpur', 'Jawalakhel, Lalitpur', 'Beautiful garden venue perfect for outdoor events with stunning greenery.', 'garden-view.jpg', '+977 1-5234567', 'contact@gardenview.com'),
('City Convention Center', 'Kathmandu', 'Thamel, Kathmandu', 'Modern convention center with state-of-the-art facilities for corporate events.', 'city-convention.jpg', '+977 1-4123456', 'info@cityconvention.com'),
('Lakeside Resort', 'Pokhara', 'Lakeside Road, Pokhara', 'Scenic lakeside venue with breathtaking mountain views.', 'lakeside-resort.jpg', '+977 61-234567', 'booking@lakesideresort.com');

-- Insert Halls
INSERT IGNORE INTO halls (venue_id, name, capacity, hall_type, indoor_outdoor, base_price, description, features) VALUES
(1, 'Sagarmatha Hall', 700, 'single', 'indoor', 150000.00, 'Our flagship hall with capacity of 700 guests. Features premium amenities and elegant decor.', 'Air conditioning, Stage, Sound system, LED screens'),
(1, 'Everest Hall', 500, 'single', 'indoor', 120000.00, 'Mid-sized hall perfect for intimate gatherings with modern facilities.', 'Air conditioning, Stage, Sound system'),
(2, 'Garden Lawn', 1000, 'single', 'outdoor', 180000.00, 'Expansive outdoor lawn with beautiful garden setting, ideal for large weddings.', 'Garden setting, Gazebo, Outdoor lighting'),
(2, 'Rose Hall', 300, 'single', 'indoor', 80000.00, 'Cozy indoor hall with floral themed decor.', 'Air conditioning, Stage, Projector'),
(3, 'Convention Hall A', 800, 'single', 'indoor', 200000.00, 'Large convention hall with modern audio-visual equipment.', 'Air conditioning, Multiple screens, Conference setup, Wi-Fi'),
(3, 'Convention Hall B', 400, 'single', 'indoor', 100000.00, 'Smaller convention space perfect for corporate meetings and seminars.', 'Air conditioning, Projector, Wi-Fi'),
(4, 'Lakeview Terrace', 600, 'single', 'outdoor', 220000.00, 'Premium outdoor terrace with stunning lake and mountain views.', 'Lake view, Mountain view, Outdoor seating'),
(4, 'Sunset Hall', 350, 'single', 'indoor', 90000.00, 'Indoor hall with large windows offering panoramic sunset views.', 'Air conditioning, Stage, Natural lighting');

-- Insert Hall Images
INSERT IGNORE INTO hall_images (hall_id, image_path, is_primary, display_order) VALUES
(1, 'sagarmatha-hall-1.jpg', 1, 1),
(1, 'sagarmatha-hall-2.jpg', 0, 2),
(2, 'everest-hall-1.jpg', 1, 1),
(3, 'garden-lawn-1.jpg', 1, 1),
(3, 'garden-lawn-2.jpg', 0, 2),
(4, 'rose-hall-1.jpg', 1, 1),
(5, 'convention-hall-a-1.jpg', 1, 1),
(6, 'convention-hall-b-1.jpg', 1, 1),
(7, 'lakeview-terrace-1.jpg', 1, 1),
(8, 'sunset-hall-1.jpg', 1, 1);

-- Insert Menus
INSERT IGNORE INTO menus (name, description, price_per_person, image) VALUES
('Royal Gold Menu', 'Premium menu featuring the finest selection of dishes with international and local cuisine.', 2399.00, 'royal-gold-menu.jpg'),
('Silver Deluxe Menu', 'Deluxe menu with a perfect blend of traditional and modern dishes.', 1899.00, 'silver-deluxe-menu.jpg'),
('Bronze Classic Menu', 'Classic menu with popular dishes that satisfy all tastes.', 1499.00, 'bronze-classic-menu.jpg'),
('Vegetarian Special', 'Specially curated vegetarian menu with diverse and flavorful options.', 1299.00, 'vegetarian-special-menu.jpg'),
('Premium Platinum', 'Ultimate luxury menu with exotic dishes and premium ingredients.', 2999.00, 'premium-platinum-menu.jpg');

-- Insert Menu Items for Royal Gold Menu
INSERT IGNORE INTO menu_items (menu_id, item_name, category, display_order) VALUES
(1, 'Welcome Drinks (Mocktails)', 'Beverages', 1),
(1, 'Assorted Salads', 'Appetizers', 2),
(1, 'Paneer Tikka', 'Appetizers', 3),
(1, 'Chicken Tikka', 'Appetizers', 4),
(1, 'Butter Chicken', 'Main Course', 5),
(1, 'Mutton Curry', 'Main Course', 6),
(1, 'Fish Fry', 'Main Course', 7),
(1, 'Vegetable Biryani', 'Main Course', 8),
(1, 'Dal Makhani', 'Main Course', 9),
(1, 'Naan & Roti', 'Breads', 10),
(1, 'Ice Cream & Gulab Jamun', 'Desserts', 11);

-- Insert Menu Items for Silver Deluxe Menu
INSERT IGNORE INTO menu_items (menu_id, item_name, category, display_order) VALUES
(2, 'Fruit Juice', 'Beverages', 1),
(2, 'Green Salad', 'Appetizers', 2),
(2, 'Veg Pakora', 'Appetizers', 3),
(2, 'Chicken Curry', 'Main Course', 4),
(2, 'Mutton Sekuwa', 'Main Course', 5),
(2, 'Mix Vegetables', 'Main Course', 6),
(2, 'Chicken Biryani', 'Main Course', 7),
(2, 'Dal Fry', 'Main Course', 8),
(2, 'Rice & Roti', 'Breads', 9),
(2, 'Rasgulla', 'Desserts', 10);

-- Insert Menu Items for Bronze Classic Menu
INSERT IGNORE INTO menu_items (menu_id, item_name, category, display_order) VALUES
(3, 'Soft Drinks', 'Beverages', 1),
(3, 'Mixed Salad', 'Appetizers', 2),
(3, 'Chicken Curry', 'Main Course', 3),
(3, 'Vegetable Curry', 'Main Course', 4),
(3, 'Pulao Rice', 'Main Course', 5),
(3, 'Dal', 'Main Course', 6),
(3, 'Roti', 'Breads', 7),
(3, 'Seasonal Fruits', 'Desserts', 8);

-- Insert Menu Items for Vegetarian Special
INSERT IGNORE INTO menu_items (menu_id, item_name, category, display_order) VALUES
(4, 'Fresh Juice', 'Beverages', 1),
(4, 'Fruit Salad', 'Appetizers', 2),
(4, 'Paneer Butter Masala', 'Main Course', 3),
(4, 'Mix Veg Curry', 'Main Course', 4),
(4, 'Chana Masala', 'Main Course', 5),
(4, 'Veg Biryani', 'Main Course', 6),
(4, 'Dal Makhani', 'Main Course', 7),
(4, 'Naan & Roti', 'Breads', 8),
(4, 'Kheer', 'Desserts', 9);

-- Insert Menu Items for Premium Platinum
INSERT IGNORE INTO menu_items (menu_id, item_name, category, display_order) VALUES
(5, 'Premium Cocktails/Mocktails', 'Beverages', 1),
(5, 'Caesar Salad', 'Appetizers', 2),
(5, 'Grilled Prawns', 'Appetizers', 3),
(5, 'Tandoori Chicken', 'Appetizers', 4),
(5, 'Butter Chicken', 'Main Course', 5),
(5, 'Lamb Rogan Josh', 'Main Course', 6),
(5, 'Grilled Fish', 'Main Course', 7),
(5, 'Seafood Biryani', 'Main Course', 8),
(5, 'Dal Makhani', 'Main Course', 9),
(5, 'Assorted Breads', 'Breads', 10),
(5, 'Chocolate Mousse & Ice Cream', 'Desserts', 11),
(5, 'Fresh Fruit Platter', 'Desserts', 12);

-- Link Halls with Menus (all halls can offer all menus)
INSERT IGNORE INTO hall_menus (hall_id, menu_id) VALUES
-- Sagarmatha Hall
(1, 1), (1, 2), (1, 3), (1, 4), (1, 5),
-- Everest Hall
(2, 1), (2, 2), (2, 3), (2, 4), (2, 5),
-- Garden Lawn
(3, 1), (3, 2), (3, 3), (3, 4), (3, 5),
-- Rose Hall
(4, 1), (4, 2), (4, 3), (4, 4), (4, 5),
-- Convention Hall A
(5, 1), (5, 2), (5, 3), (5, 4), (5, 5),
-- Convention Hall B
(6, 1), (6, 2), (6, 3), (6, 4), (6, 5),
-- Lakeview Terrace
(7, 1), (7, 2), (7, 3), (7, 4), (7, 5),
-- Sunset Hall
(8, 1), (8, 2), (8, 3), (8, 4), (8, 5);

-- Insert Additional Services
INSERT IGNORE INTO additional_services (name, description, price, category) VALUES
('Flower Decoration', 'Beautiful floral arrangements throughout the venue', 15000.00, 'Decoration'),
('Stage Decoration', 'Professional stage setup with backdrop and lighting', 25000.00, 'Decoration'),
('Photography Package', 'Professional photography services for the entire event', 30000.00, 'Photographer'),
('Videography Package', 'HD video coverage with edited highlights', 40000.00, 'Videographer'),
('DJ Service', 'Professional DJ with sound system and lighting', 20000.00, 'Other'),
('Live Band', 'Live music performance by professional band', 50000.00, 'Baje (Music/Band)'),
('Transportation', 'Guest transportation service with comfortable vehicles', 35000.00, 'Other'),
('Valet Parking', 'Professional valet parking service for guests', 10000.00, 'Other');

-- Populate vendor_type_id for the inserted services
UPDATE additional_services s
JOIN vendor_types vt ON LOWER(TRIM(vt.label)) = LOWER(TRIM(s.category))
SET s.vendor_type_id = vt.id
WHERE s.vendor_type_id IS NULL
  AND s.category IS NOT NULL
  AND s.category <> '';

-- Insert Sample Service Categories
INSERT IGNORE INTO service_categories (name, description, display_order, status) VALUES
('विवाह', 'विवाह समारोहको लागि विशेष प्याकेजहरू', 1, 'active'),
('पास्नी', 'पास्नी (अन्नप्राशन) समारोहको लागि प्याकेजहरू', 2, 'active'),
('व्रतबन्द', 'व्रतबन्द समारोहको लागि प्याकेजहरू', 3, 'active');

-- Insert Sample Service Packages
INSERT IGNORE INTO service_packages (category_id, name, description, price, display_order, status) VALUES
(1, 'Silver Wedding Package', 'Essential wedding package with all key services', 250000.00, 1, 'active'),
(1, 'Gold Wedding Package', 'Premium wedding package with enhanced services', 450000.00, 2, 'active'),
(1, 'Platinum Wedding Package', 'All-inclusive luxury wedding package', 750000.00, 3, 'active'),
(2, 'Basic Pasni Package', 'Simple and elegant Pasni ceremony package', 80000.00, 1, 'active'),
(2, 'Premium Pasni Package', 'Full-service Pasni ceremony with decoration', 150000.00, 2, 'active'),
(3, 'Bratabandha Package', 'Complete Bratabandha ceremony package', 120000.00, 1, 'active');

-- Insert Sample Service Package Features
INSERT IGNORE INTO service_package_features (package_id, feature_text, display_order) VALUES
(1, 'Hall decoration with flowers', 1),
(1, 'Photography (4 hours)', 2),
(1, 'DJ music system', 3),
(1, 'Welcome drinks', 4),
(2, 'Premium hall decoration', 1),
(2, 'Photography & Videography (full day)', 2),
(2, 'Live band / DJ', 3),
(2, 'Welcome drinks & appetizers', 4),
(2, 'Dedicated event coordinator', 5),
(3, 'Luxury hall & stage decoration', 1),
(3, 'Professional photography & videography', 2),
(3, 'Live band & DJ', 3),
(3, 'Full catering coordination', 4),
(3, 'Dedicated event management team', 5),
(3, 'Honeymoon suite arrangement', 6),
(4, 'Basic stage decoration', 1),
(4, 'Photography (2 hours)', 2),
(4, 'Sound system', 3),
(5, 'Full venue decoration', 1),
(5, 'Photography & Videography', 2),
(5, 'DJ music system', 3),
(5, 'Catering coordination', 4),
(6, 'Traditional ceremony decoration', 1),
(6, 'Photography (3 hours)', 2),
(6, 'Sound system', 3),
(6, 'Catering arrangement', 4);

-- Insert Payment Methods
INSERT IGNORE INTO payment_methods (name, bank_details, status, display_order) VALUES
('Bank Transfer', 'Bank: [Your Bank Name]
Account Name: [Account Holder Name]
Account Number: [Account Number]
Branch: [Branch Name]

Note: Please update these details in Admin > Payment Methods', 'inactive', 1),
('eSewa', 'eSewa ID: [Your eSewa ID]
eSewa Number: [Your eSewa Number]

Note: Please update these details in Admin > Payment Methods', 'inactive', 2),
('Khalti', 'Khalti ID: [Your Khalti ID]
Khalti Number: [Your Khalti Number]

Note: Please update these details in Admin > Payment Methods', 'inactive', 3),
('Cash Payment', 'Cash payment can be made at our office during business hours.
Please bring your booking reference number.', 'active', 4);

-- Insert Sample Customers
INSERT IGNORE INTO customers (full_name, phone, email, address) VALUES
('Ramesh Sharma', '+977 9841234567', 'ramesh.sharma@example.com', 'Kathmandu, Nepal'),
('Sita Thapa', '+977 9851234567', 'sita.thapa@example.com', 'Lalitpur, Nepal'),
('Bijay Kumar', '+977 9861234567', 'bijay.kumar@example.com', 'Bhaktapur, Nepal'),
('Anil Gurung', '+977 9871234567', 'anil.gurung@example.com', 'Pokhara, Nepal'),
('Maya Rai', '+977 9881234567', 'maya.rai@example.com', 'Chitwan, Nepal'),
('Prakash Shrestha', '+977 9891234567', 'prakash.shrestha@example.com', 'Bhaktapur, Nepal'),
('Uttam Acharya', '+977 9801234567', 'uttam.acharya@example.com', 'Kathmandu, Nepal');

-- Insert Sample Bookings (Including booking with ID=37 and ID=23 for testing)
INSERT IGNORE INTO bookings (id, booking_number, customer_id, hall_id, event_date, shift, event_type, number_of_guests, hall_price, menu_total, services_total, subtotal, tax_amount, grand_total, special_requests, booking_status, payment_status) VALUES
(1, 'BK-20260115-0001', 1, 1, '2026-02-15', 'evening', 'Wedding', 500, 150000.00, 1199500.00, 65000.00, 1414500.00, 183885.00, 1598385.00, 'Please arrange for vegetarian options separately', 'confirmed', 'partial'),
(2, 'BK-20260120-0002', 2, 3, '2026-03-20', 'fullday', 'Birthday Party', 200, 180000.00, 299800.00, 45000.00, 524800.00, 68224.00, 593024.00, NULL, 'pending', 'pending'),
(23, 'BK-20260125-0023', 7, 4, '2026-04-10', 'evening', 'Wedding Reception', 250, 80000.00, 374750.00, 80000.00, 534750.00, 69517.50, 604267.50, 'Please provide separate dining area for elderly guests', 'confirmed', 'partial'),
(37, 'BK-20260130-0037', 3, 1, '2026-05-20', 'evening', 'Wedding Ceremony', 600, 150000.00, 1139400.00, 100000.00, 1389400.00, 180622.00, 1570022.00, 'Need special lighting arrangements for photography. Also require separate arrangements for kids.', 'confirmed', 'partial');

-- Insert booking menus for booking #1
INSERT IGNORE INTO booking_menus (booking_id, menu_id, price_per_person, number_of_guests, total_price) VALUES
(1, 1, 2399.00, 500, 1199500.00);

-- Insert booking menus for booking #2
INSERT IGNORE INTO booking_menus (booking_id, menu_id, price_per_person, number_of_guests, total_price) VALUES
(2, 4, 1499.00, 200, 299800.00);

-- Insert booking menus for booking #23
INSERT IGNORE INTO booking_menus (booking_id, menu_id, price_per_person, number_of_guests, total_price) VALUES
(23, 2, 1499.00, 250, 374750.00);

-- Insert booking menus for booking #37
INSERT IGNORE INTO booking_menus (booking_id, menu_id, price_per_person, number_of_guests, total_price) VALUES
(37, 1, 1899.00, 600, 1139400.00);

-- Insert booking services for booking #1
INSERT IGNORE INTO booking_services (booking_id, service_id, service_name, price, description, category, added_by, quantity) VALUES
(1, 1, 'Flower Decoration', 15000.00, 'Beautiful floral arrangements throughout the venue', 'Decoration', 'user', 1),
(1, 3, 'Photography Package', 30000.00, 'Professional photography services for the entire event', 'Photography', 'user', 1),
(1, 5, 'DJ Service', 20000.00, 'Professional DJ with sound system and lighting', 'Entertainment', 'user', 1);

-- Insert booking services for booking #2
INSERT IGNORE INTO booking_services (booking_id, service_id, service_name, price, description, category, added_by, quantity) VALUES
(2, 1, 'Flower Decoration', 15000.00, 'Beautiful floral arrangements throughout the venue', 'Decoration', 'user', 1),
(2, 3, 'Photography Package', 30000.00, 'Professional photography services for the entire event', 'Photography', 'user', 1);

-- Insert booking services for booking #23
INSERT IGNORE INTO booking_services (booking_id, service_id, service_name, price, description, category, added_by, quantity) VALUES
(23, 1, 'Flower Decoration', 15000.00, 'Beautiful floral arrangements throughout the venue', 'Decoration', 'user', 1),
(23, 2, 'Stage Decoration', 25000.00, 'Professional stage setup with backdrop and lighting', 'Decoration', 'user', 1),
(23, 3, 'Photography Package', 30000.00, 'Professional photography services for the entire event', 'Photography', 'user', 1),
(23, 8, 'Valet Parking', 10000.00, 'Professional valet parking service for guests', 'Logistics', 'user', 1);

-- Insert booking services for booking #37
INSERT IGNORE INTO booking_services (booking_id, service_id, service_name, price, description, category, added_by, quantity) VALUES
(37, 1, 'Flower Decoration', 15000.00, 'Beautiful floral arrangements throughout the venue', 'Decoration', 'user', 1),
(37, 2, 'Stage Decoration', 25000.00, 'Professional stage setup with backdrop and lighting', 'Decoration', 'user', 1),
(37, 3, 'Photography Package', 30000.00, 'Professional photography services for the entire event', 'Photography', 'user', 1),
(37, 4, 'Videography Package', 40000.00, 'HD video coverage with edited highlights', 'Videography', 'user', 1);

-- Insert payment methods for bookings (link active payment methods to bookings)
INSERT IGNORE INTO booking_payment_methods (booking_id, payment_method_id) VALUES
(1, 4),
(23, 1),
(23, 4),
(37, 1),
(37, 2);

-- Insert sample payment transactions
INSERT IGNORE INTO payments (booking_id, payment_method_id, transaction_id, paid_amount, payment_date, payment_status, notes) VALUES
(1, 4, 'CASH-2026-0001', 479515.50, '2026-01-15 14:30:00', 'verified', 'Advance payment received in cash'),
(23, 1, 'BT-2026-0001', 181280.25, '2026-01-25 10:15:00', 'verified', 'Advance payment via bank transfer'),
(37, 1, 'BT-2026-0037', 471006.60, '2026-01-30 11:00:00', 'verified', 'Advance payment of 30% received');

-- Insert Sample Venue Images
INSERT IGNORE INTO venue_images (venue_id, image_path, is_primary, display_order) VALUES
(1, 'royal-palace-main.jpg', 1, 1),
(1, 'royal-palace-hall.jpg', 0, 2),
(1, 'royal-palace-garden.jpg', 0, 3),
(2, 'garden-view-hall-main.jpg', 1, 1),
(2, 'garden-view-hall-lawn.jpg', 0, 2),
(3, 'city-convention-main.jpg', 1, 1),
(3, 'city-convention-interior.jpg', 0, 2),
(4, 'lakeside-resort-main.jpg', 1, 1),
(4, 'lakeside-resort-terrace.jpg', 0, 2);

-- Insert Sample Vendors
-- City IDs: 1=Kathmandu, 2=Pokhara, 3=Lalitpur (Patan), 4=Bhaktapur
INSERT IGNORE INTO vendors (name, type, short_description, phone, email, address, city_id, notes, status) VALUES
('Pandit Ram Prasad Sharma', 'pandit', 'Expert Vedic pandit for Hindu ceremonies', '+977 9841001001', 'ramprasad@example.com', 'Pashupatinath Road, Kathmandu', 1, 'Experienced pandit for Hindu wedding ceremonies', 'active'),
('Shree Photography Studio', 'photographer', 'Professional wedding and event photography', '+977 9851002002', 'shree.photo@example.com', 'New Road, Kathmandu', 1, 'Professional wedding and event photography', 'active'),
('Pokhara Lens Creations', 'photographer', 'Scenic and artistic photography for all events', '+977 9861003003', 'pokharalens@example.com', 'Lakeside, Pokhara', 2, 'Scenic and artistic photography for all events', 'active'),
('Memory Films Pvt. Ltd.', 'videographer', 'HD and 4K videography with drone coverage', '+977 9871004004', 'memoryfilms@example.com', 'Jawalakhel, Lalitpur', 3, 'HD and 4K videography with drone coverage', 'active'),
('Swarnakar Baje Party', 'baje', 'Traditional Newar music ensemble for weddings', '+977 9881005005', NULL, 'Bhaktapur Durbar Square Area, Bhaktapur', 4, 'Traditional Newar music ensemble for weddings', 'active'),
('Royal Decoration House', 'decoration', 'Premium floral and stage decoration services', '+977 9801006006', 'royaldecor@example.com', 'Thamel, Kathmandu', 1, 'Premium floral and stage decoration services', 'active'),
('Taste of Nepal Catering', 'catering', 'Authentic Nepali and multi-cuisine catering', '+977 9841007007', 'taste.nepal@example.com', 'Boudha Road, Kathmandu', 1, 'Authentic Nepali and multi-cuisine catering', 'active'),
('Himalayan Event Decor', 'decoration', 'Elegant decoration with mountain-inspired themes', '+977 9851008008', 'himalayanevents@example.com', 'Lakeside Road, Pokhara', 2, 'Elegant decoration with mountain-inspired themes', 'active');

-- Insert Sample Vendor Photos
INSERT IGNORE INTO vendor_photos (vendor_id, image_path, is_primary, display_order) VALUES
(1, 'pandit-ram-prasad-1.jpg', 1, 1),
(2, 'shree-photography-1.jpg', 1, 1),
(2, 'shree-photography-2.jpg', 0, 2),
(3, 'pokhara-lens-1.jpg', 1, 1),
(4, 'memory-films-1.jpg', 1, 1),
(5, 'swarnakar-baje-1.jpg', 1, 1),
(6, 'royal-decoration-1.jpg', 1, 1),
(6, 'royal-decoration-2.jpg', 0, 2),
(7, 'taste-nepal-catering-1.jpg', 1, 1),
(8, 'himalayan-event-decor-1.jpg', 1, 1);

-- Insert Sample Booking Vendor Assignments
-- Booking #1 (Wedding - Sagarmatha Hall)
INSERT IGNORE INTO booking_vendor_assignments (booking_id, vendor_id, task_description, assigned_amount, notes, status) VALUES
(1, 1, 'Conduct wedding rituals and puja ceremonies', 25000.00, 'Full day ceremony including havan and pheras', 'confirmed'),
(1, 2, 'Photography coverage for the wedding', 30000.00, 'Full day photography including pre-wedding shoot', 'confirmed'),
(1, 6, 'Stage and venue decoration', 40000.00, 'Premium floral decoration for stage and entire hall', 'confirmed');

-- Booking #23 (Wedding Reception - Rose Hall)
INSERT IGNORE INTO booking_vendor_assignments (booking_id, vendor_id, task_description, assigned_amount, notes, status) VALUES
(23, 1, 'Conduct reception rituals and blessings', 15000.00, 'Evening ceremony rituals', 'confirmed'),
(23, 2, 'Photography for the reception', 30000.00, 'Event photography and portrait sessions', 'confirmed'),
(23, 4, 'Videography coverage', 35000.00, 'HD video coverage with highlights reel', 'confirmed');

-- Booking #37 (Wedding Ceremony - Sagarmatha Hall)
INSERT IGNORE INTO booking_vendor_assignments (booking_id, vendor_id, task_description, assigned_amount, notes, status) VALUES
(37, 1, 'Conduct full wedding ceremony', 30000.00, 'Complete Vedic wedding ceremony', 'assigned'),
(37, 5, 'Traditional music performance', 20000.00, 'Baje party for baraat and ceremony', 'assigned'),
(37, 6, 'Stage and hall decoration', 50000.00, 'Luxury decoration for wedding ceremony', 'assigned'),
(37, 4, 'Videography with drone shots', 40000.00, 'Full day 4K videography with drone footage', 'assigned');

-- ============================================================================
-- SAMPLE DATA: Service Sub-Services and Designs (for booking-step4 visual flow)
-- ============================================================================

-- Sub-services under Flower Decoration (service_id=1)
INSERT IGNORE INTO service_sub_services (service_id, name, description, display_order, status) VALUES
(1, 'Stage Flowers', 'Floral arrangements specifically for the stage backdrop', 1, 'active'),
(1, 'Table Centerpieces', 'Individual table floral centerpiece arrangements', 2, 'active'),
(1, 'Entrance Arch', 'Grand floral arch for the venue entrance', 3, 'active');

-- Sub-services under Stage Decoration (service_id=2)
INSERT IGNORE INTO service_sub_services (service_id, name, description, display_order, status) VALUES
(2, 'Traditional Theme', 'Classic Nepali traditional stage decoration', 1, 'active'),
(2, 'Modern Minimalist', 'Clean, contemporary stage backdrop', 2, 'active'),
(2, 'Luxury Royal', 'Opulent, royal-themed stage decoration', 3, 'active');

-- Sub-services under Photography Package (service_id=3)
INSERT IGNORE INTO service_sub_services (service_id, name, description, display_order, status) VALUES
(3, 'Indoor Photography', 'Professional indoor event photography', 1, 'active'),
(3, 'Outdoor Shoot', 'Outdoor location photography session', 2, 'active'),
(3, 'Candid Album', 'Candid moments photography with premium album', 3, 'active');

-- Designs directly linked to Flower Decoration service (service_id=1, no sub-service)
INSERT IGNORE INTO service_designs (service_id, name, description, price, photo, display_order, status) VALUES
(1, 'Classic Rose Arrangement', 'Elegant arrangement with red and white roses', 8000.00, NULL, 1, 'active'),
(1, 'Marigold Festival Style', 'Traditional Nepali marigold and marigold-leaf garland decoration', 6000.00, NULL, 2, 'active'),
(1, 'Orchid Premium', 'Exotic orchid and lily mixed floral display', 12000.00, NULL, 3, 'active'),
(1, 'Pastel Garden Theme', 'Soft pastel flowers — peach, lavender, cream — for a romantic look', 10000.00, NULL, 4, 'active');

-- Designs directly linked to Stage Decoration service (service_id=2, no sub-service)
INSERT IGNORE INTO service_designs (service_id, name, description, price, photo, display_order, status) VALUES
(2, 'Traditional Nepali Backdrop', 'Hand-painted traditional motifs with dhaka fabric and marigold drapes', 20000.00, NULL, 1, 'active'),
(2, 'Modern Geometric Backdrop', 'Clean geometric panels with LED accent lighting', 25000.00, NULL, 2, 'active'),
(2, 'Royal Gold Throne Setup', 'Gold-framed throne chairs, red carpet, pillar arrangements', 35000.00, NULL, 3, 'active'),
(2, 'Floral Fantasy Stage', 'Stage fully wrapped in fresh flower panels — roses and hydrangeas', 30000.00, NULL, 4, 'active');

-- Designs directly linked to Photography Package (service_id=3, no sub-service)
INSERT IGNORE INTO service_designs (service_id, name, description, price, photo, display_order, status) VALUES
(3, 'Classic Wedding Album (100 pages)', 'Professionally designed 100-page photo album in leather binding', 15000.00, NULL, 1, 'active'),
(3, 'Digital Package', 'High-resolution digital photos delivered via USB and cloud link', 8000.00, NULL, 2, 'active'),
(3, 'Drone Aerial Shots Add-on', 'Aerial photography with drone for wide-angle venue coverage', 12000.00, NULL, 3, 'active');

-- ============================================================================
-- SAMPLE DATA: Additional Bookings for Testing All Statuses
-- ============================================================================

-- Booking #50: Completed / Paid (past event)
INSERT IGNORE INTO bookings (id, booking_number, customer_id, hall_id,
    event_date, start_time, end_time, shift, event_type, number_of_guests,
    hall_price, menu_total, services_total, subtotal, tax_amount, grand_total,
    special_requests, booking_status, payment_status, advance_payment_received)
VALUES
(50, 'BK-20251210-0050', 4, 5, '2025-12-10', '18:00:00', '23:00:00', 'evening',
 'Anniversary Party', 150,
 200000.00, 299850.00, 50000.00, 549850.00, 71480.50, 621330.50,
 NULL, 'completed', 'paid', 1);

-- Booking #51: Pending / No Payment
INSERT IGNORE INTO bookings (id, booking_number, customer_id, hall_id,
    event_date, start_time, end_time, shift, event_type, number_of_guests,
    hall_price, menu_total, services_total, subtotal, tax_amount, grand_total,
    special_requests, booking_status, payment_status, advance_payment_received)
VALUES
(51, 'BK-20260410-0051', 5, 2, '2026-04-10', '12:00:00', '18:00:00', 'afternoon',
 'Corporate Event', 300,
 120000.00, 449700.00, 70000.00, 639700.00, 83161.00, 722861.00,
 'Require projector and presentation equipment', 'pending', 'pending', 0);

-- Booking #52: Cancelled
INSERT IGNORE INTO bookings (id, booking_number, customer_id, hall_id,
    event_date, start_time, end_time, shift, event_type, number_of_guests,
    hall_price, menu_total, services_total, subtotal, tax_amount, grand_total,
    special_requests, booking_status, payment_status, advance_payment_received)
VALUES
(52, 'BK-20260501-0052', 6, 7, '2026-05-01', '06:00:00', '23:00:00', 'fullday',
 'Wedding', 450,
 220000.00, 674550.00, 90000.00, 984550.00, 127991.50, 1112541.50,
 NULL, 'cancelled', 'pending', 0);

-- Booking #53: Custom Venue (customer's own venue — hall_id is NULL)
INSERT IGNORE INTO bookings (id, booking_number, customer_id, hall_id,
    custom_venue_name, custom_hall_name,
    event_date, start_time, end_time, shift, event_type, number_of_guests,
    hall_price, menu_total, services_total, subtotal, tax_amount, grand_total,
    special_requests, booking_status, payment_status, advance_payment_received)
VALUES
(53, 'BK-20260615-0053', 7, NULL,
 'Sharma Niwas', 'Home Garden Courtyard',
 '2026-06-15', '18:00:00', '23:00:00', 'evening',
 'Engagement Ceremony', 100,
 0.00, 149900.00, 55000.00, 204900.00, 26637.00, 231537.00,
 'Event at customer own residence — no hall charge', 'confirmed', 'partial', 1);

-- Insert menus for new bookings
INSERT IGNORE INTO booking_menus (booking_id, menu_id, price_per_person, number_of_guests, total_price) VALUES
(50, 2, 1999.00, 150, 299850.00),
(51, 1, 1499.00, 300, 449700.00),
(52, 1, 1499.00, 450, 674550.00),
(53, 3, 1499.00, 100, 149900.00);

-- Insert services for new bookings
INSERT IGNORE INTO booking_services (booking_id, service_id, service_name, price, description, category, added_by, quantity) VALUES
(50, 2, 'Stage Decoration', 25000.00, 'Professional stage setup', 'Decoration', 'user', 1),
(50, 3, 'Photography Package', 30000.00, 'Professional photography', 'Photography', 'user', 1);

INSERT IGNORE INTO booking_services (booking_id, service_id, service_name, price, description, category, added_by, quantity) VALUES
(51, 1, 'Flower Decoration', 15000.00, 'Floral arrangements', 'Decoration', 'user', 1),
(51, 3, 'Photography Package', 30000.00, 'Professional photography', 'Photography', 'user', 1),
(51, 5, 'DJ Service', 20000.00, 'Professional DJ', 'Entertainment', 'user', 1);

INSERT IGNORE INTO booking_services (booking_id, service_id, service_name, price, description, category, added_by, quantity) VALUES
(52, 1, 'Flower Decoration', 15000.00, 'Floral arrangements', 'Decoration', 'user', 1),
(52, 2, 'Stage Decoration', 25000.00, 'Stage setup', 'Decoration', 'user', 1),
(52, 4, 'Videography Package', 40000.00, 'HD video coverage', 'Videography', 'user', 1);

INSERT IGNORE INTO booking_services (booking_id, service_id, service_name, price, description, category, added_by, quantity) VALUES
(53, 1, 'Flower Decoration', 15000.00, 'Floral arrangements', 'Decoration', 'user', 1),
(53, 3, 'Photography Package', 30000.00, 'Professional photography', 'Photography', 'user', 1),
(53, 5, 'DJ Service', 20000.00, 'DJ with sound system', 'Entertainment', 'user', 1);

-- Insert payment transactions for new bookings
INSERT IGNORE INTO payments (booking_id, payment_method_id, transaction_id, paid_amount, payment_date, payment_status, notes) VALUES
(50, 4, 'CASH-2025-0050', 621330.50, '2025-12-05 10:00:00', 'verified', 'Full payment received before event'),
(53, 2, 'ESEWA-2026-0053', 57884.25, '2026-05-20 09:30:00', 'verified', 'Advance 25% via eSewa');

-- ============================================================================
-- SAMPLE DATA: Shared Folders and Photos (for photo sharing feature)
-- ============================================================================

-- Shared folders (access via download_token, e.g. /folder.php?token=RAMESH2026)
INSERT IGNORE INTO shared_folders (id, folder_name, description, download_token, show_preview, status, created_by, created_at) VALUES
(1, 'Wedding Album - BK-0001',      'Official wedding photos for Ramesh & Party',        'RAMESH2026',  1, 'active', 1, '2026-02-16 10:00:00'),
(2, 'Reception Album - BK-0023',    'Wedding reception photos for booking #23',           'RECEP2026',   1, 'active', 1, '2026-04-11 09:00:00'),
(3, 'Event Album - BK-0037',        'Wedding ceremony photos for booking #37',            'WEDCEREM37',  1, 'active', 1, '2026-05-21 08:00:00'),
(4, 'Anniversary Album - BK-0050',  'Anniversary party photos for booking #50',           'ANNIV50',     1, 'active', 1, '2025-12-11 11:00:00');

-- Sample photos in shared folders (file paths are sample references; actual files are not required for DB testing)
INSERT IGNORE INTO shared_photos (id, folder_id, title, description, image_path, file_size, file_type, download_token, status, created_by, created_at) VALUES
(1, 1, 'Wedding Ceremony 01',    NULL, 'shared-folders/1/wedding-ceremony-01.jpg',  2048000, 'photo', 'DL-RAMESH-001', 'active', 1, '2026-02-17 10:00:00'),
(2, 1, 'Wedding Ceremony 02',    NULL, 'shared-folders/1/wedding-ceremony-02.jpg',  1920000, 'photo', 'DL-RAMESH-002', 'active', 1, '2026-02-17 10:01:00'),
(3, 1, 'Wedding Couple Portrait',NULL, 'shared-folders/1/wedding-couple-01.jpg',    2500000, 'photo', 'DL-RAMESH-003', 'active', 1, '2026-02-17 10:02:00'),
(4, 2, 'Reception Stage',        NULL, 'shared-folders/2/reception-stage-01.jpg',   2100000, 'photo', 'DL-RECEP-001',  'active', 1, '2026-04-12 09:00:00'),
(5, 2, 'Reception Guests',       NULL, 'shared-folders/2/reception-guests-01.jpg',  1800000, 'photo', 'DL-RECEP-002',  'active', 1, '2026-04-12 09:01:00'),
(6, 3, 'Ceremony Pheras',        NULL, 'shared-folders/3/ceremony-pheras-01.jpg',   2300000, 'photo', 'DL-WCED-001',   'active', 1, '2026-05-22 08:00:00'),
(7, 3, 'Ceremony Couple',        NULL, 'shared-folders/3/ceremony-couple-01.jpg',   2200000, 'photo', 'DL-WCED-002',   'active', 1, '2026-05-22 08:01:00'),
(8, 4, 'Anniversary Cake',       NULL, 'shared-folders/4/anniversary-cake-01.jpg',  1700000, 'photo', 'DL-ANNIV-001',  'active', 1, '2025-12-12 11:00:00'),
(9, 4, 'Anniversary Dance',      NULL, 'shared-folders/4/anniversary-dance-01.jpg', 1900000, 'photo', 'DL-ANNIV-002',  'active', 1, '2025-12-12 11:01:00');

-- Update photo counts on shared_folders to reflect the inserts above
UPDATE shared_folders SET photo_count = 3 WHERE id = 1;
UPDATE shared_folders SET photo_count = 2 WHERE id = 2;
UPDATE shared_folders SET photo_count = 2 WHERE id = 3;
UPDATE shared_folders SET photo_count = 2 WHERE id = 4;

-- ============================================================================
-- VERIFICATION QUERIES
-- ============================================================================

SELECT '============================================' as '';
SELECT 'DATABASE SETUP COMPLETED SUCCESSFULLY!' as 'Status';
SELECT '============================================' as '';
SELECT '' as '';

SELECT 'SYSTEM INFORMATION' as '';
SELECT '-------------------' as '';
SELECT DATABASE() as 'Database Name';
SELECT COUNT(*) as 'Total Tables' FROM information_schema.tables WHERE table_schema = DATABASE();
SELECT '' as '';

SELECT 'DATA SUMMARY' as '';
SELECT '-------------' as '';
SELECT COUNT(*) as 'Total Venues' FROM venues;
SELECT COUNT(*) as 'Total Halls' FROM halls;
SELECT COUNT(*) as 'Total Menus' FROM menus;
SELECT COUNT(*) as 'Total Services' FROM additional_services;
SELECT COUNT(*) as 'Total Payment Methods' FROM payment_methods;
SELECT COUNT(*) as 'Total Customers' FROM customers;
SELECT COUNT(*) as 'Total Bookings' FROM bookings;
SELECT COUNT(*) as 'Total Vendors' FROM vendors;
SELECT COUNT(*) as 'Total Admin Users' FROM users;
SELECT '' as '';

SELECT 'TEST BOOKINGS' as '';
SELECT '--------------' as '';
SELECT id as 'Booking ID', booking_number as 'Booking Number', event_type as 'Event Type', 
       booking_status as 'Status', payment_status as 'Payment' 
FROM bookings WHERE id IN (23, 37, 50, 51, 52, 53);
SELECT '' as '';

SELECT 'DESIGN / SUB-SERVICE DATA' as '';
SELECT '--------------------------' as '';
SELECT COUNT(*) as 'Service Sub-Services' FROM service_sub_services;
SELECT COUNT(*) as 'Service Designs' FROM service_designs;
SELECT '' as '';

SELECT 'SHARED FOLDERS' as '';
SELECT '---------------' as '';
SELECT id as 'Folder ID', folder_name as 'Folder Name', photo_count as 'Photos', status as 'Status'
FROM shared_folders;
SELECT '' as '';

SELECT 'ADMIN LOGIN CREDENTIALS' as '';
SELECT '------------------------' as '';
SELECT 'Username: admin' as 'Login Info';
SELECT 'Password: Admin@123' as '';
SELECT 'URL: /admin/' as '';
SELECT '⚠️ IMPORTANT: Change password after first login!' as 'Security Warning';
SELECT '' as '';

SELECT '============================================' as '';
SELECT 'SETUP COMPLETE - System Ready to Use!' as 'Final Status';
SELECT '============================================' as '';
