-- ============================================================================
-- PRODUCTION DATABASE FOR SHARED HOSTING (LIVE DEPLOYMENT)
-- ============================================================================
-- 
-- This is a CLEAN production database script with NO sample data.
-- It includes:
--   1. All required tables with proper relationships (34 tables)
--   2. Default admin user (username: admin, password: Admin@123)
--   3. Essential system settings
--   4. Placeholder payment methods (INACTIVE by default)
--   5. Default vendor types
--   6. Database triggers for data integrity
-- 
-- ⚠️ IMPORTANT SECURITY NOTES:
--   - Change the default admin password IMMEDIATELY after installation!
--   - Access admin panel at: /admin/
--   - Update payment method details before activating them
--   - Configure email settings in Admin Panel → Settings
--   - Update company information in Settings
-- 
-- ============================================================================
-- HOW TO IMPORT IN SHARED HOSTING (phpMyAdmin):
-- ============================================================================
-- 
-- STEP 1: CREATE DATABASE IN cPANEL
--    1. Log into your cPanel
--    2. Go to "MySQL Databases"
--    3. Database should already be created: digitallami_partybooking
--    4. User should already exist: digitallami_partybooking
--    5. User should be assigned to the database with ALL PRIVILEGES
-- 
-- STEP 2: IMPORT THIS SQL FILE
--    1. Open phpMyAdmin from cPanel
--    2. Click on "digitallami_partybooking" database in left sidebar
--    3. Click "Import" tab at the top
--    4. Click "Choose File" and select this file: production-shared-hosting.sql
--    5. Scroll down and click "Go" button
--    6. Wait for import to complete (should show success message)
-- 
-- STEP 3: CONFIGURE APPLICATION
--    1. Upload all website files to public_html (or subdirectory)
--    2. Create/edit .env file in root directory with your DB credentials:
--       
--       DB_HOST=localhost
--       DB_NAME=your_database_name
--       DB_USER=your_database_user
--       DB_PASS=your_database_password
--    
--    3. Ensure uploads/ directory has write permissions (755 or 777)
-- 
-- STEP 4: FIRST LOGIN & SECURITY
--    1. Access admin panel: https://yoursite.com/admin/
--    2. Login with: admin / Admin@123
--    3. IMMEDIATELY change password: Settings → Change Password
--    4. Update company information in Settings
--    5. Configure payment methods with your real details
-- 
-- ============================================================================

-- NOTE: This script does NOT create a database - it only creates tables.
-- Make sure your database is selected before importing.
-- SAFE TO RE-RUN: Uses CREATE TABLE IF NOT EXISTS and INSERT IGNORE so it
-- will NOT destroy any existing data.


-- ============================================================================
-- TABLE: cities (predefined city list for venue filtering)
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
    map_link VARCHAR(500) NULL,
    pano_image VARCHAR(255) DEFAULT NULL COMMENT '360° equirectangular panoramic image filename (stored in uploads/)',
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_venues_city FOREIGN KEY (city_id) REFERENCES cities(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- TABLE: venue_images (multiple images per venue)
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
    pano_image VARCHAR(255) DEFAULT NULL COMMENT '360° equirectangular panoramic image filename (stored in uploads/)',
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

-- ============================================================================
-- TABLE: site_images (for dynamic image management)
-- ============================================================================
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

-- ============================================================================
-- TABLE: shared_folders (for folder-based photo sharing like Google Drive)
-- ============================================================================
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

-- ============================================================================
-- TABLE: shared_photos (for photo and video sharing feature)
-- ============================================================================
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
-- INSERT DEFAULT DATA
-- ============================================================================

-- Insert default admin user (password: Admin@123)
-- ⚠️ SECURITY WARNING: Change this password immediately after installation!
INSERT IGNORE INTO users (username, password, full_name, email, role, status) 
VALUES ('admin', '$2y$10$5sw.gEWePITwobdChuwoRuRT4dtOnxCFf/RMosnL9JVeEeb3teuna', 'System Administrator', 'admin@venubooking.com', 'admin', 'active');

-- Insert default cities
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
('Nepalgunj', 'active'),
('Bharatpur', 'active');

-- Insert default vendor types
INSERT IGNORE INTO vendor_types (slug, label, display_order) VALUES
('photographer', 'Photographer', 1),
('videographer', 'Videographer', 2),
('decorator', 'Decorator / Florist', 3),
('catering', 'Catering Service', 4),
('music', 'Music / DJ / Band', 5),
('lighting', 'Lighting & Sound', 6),
('makeup', 'Makeup Artist', 7),
('transport', 'Transportation', 8),
('security', 'Security Service', 9),
('other', 'Other', 10);

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

-- Insert placeholder payment methods (INACTIVE by default)
-- ⚠️ IMPORTANT: Update these details in Admin Panel → Payment Methods before activating!
INSERT IGNORE INTO payment_methods (name, bank_details, status, display_order) VALUES
('Bank Transfer', 'Bank: [Your Bank Name]
Account Name: [Account Holder Name]
Account Number: [Your Account Number]
Branch: [Branch Name]
Swift Code: [If applicable]

⚠️ IMPORTANT: Update these details in Admin Panel → Payment Methods before activating this payment method.', 'inactive', 1),

('eSewa', 'eSewa ID: [Your eSewa ID]
eSewa Number: [Your eSewa Number]
eSewa Name: [Account Name]

⚠️ IMPORTANT: Update these details and upload QR code in Admin Panel → Payment Methods before activating this payment method.', 'inactive', 2),

('Khalti', 'Khalti ID: [Your Khalti ID]
Khalti Number: [Your Khalti Number]
Khalti Name: [Account Name]

⚠️ IMPORTANT: Update these details and upload QR code in Admin Panel → Payment Methods before activating this payment method.', 'inactive', 3),

('Cash Payment', 'Cash payment can be made at our office during business hours.

Office Address: [Your Office Address]
Business Hours: [Your Business Hours]
Contact: [Your Phone Number]

⚠️ IMPORTANT: Update the address and contact details in Admin Panel → Payment Methods.', 'active', 4);

-- ============================================================================
-- INSTALLATION COMPLETE
-- ============================================================================

SELECT '============================================' as '';
SELECT 'PRODUCTION DATABASE SETUP COMPLETED!' as 'Status';
SELECT '============================================' as '';
SELECT '' as '';

SELECT 'SYSTEM INFORMATION' as '';
SELECT '-------------------' as '';
SELECT DATABASE() as 'Database Name';
SELECT COUNT(*) as 'Total Tables Created' FROM information_schema.tables WHERE table_schema = DATABASE();
SELECT '' as '';

SELECT 'ADMIN LOGIN CREDENTIALS' as '';
SELECT '------------------------' as '';
SELECT 'Username: admin' as 'Login Info';
SELECT 'Password: Admin@123' as '';
SELECT 'Admin Panel URL: /admin/' as '';
SELECT '' as '';
SELECT '⚠️  CRITICAL SECURITY WARNING ⚠️' as '';
SELECT 'CHANGE THE DEFAULT ADMIN PASSWORD IMMEDIATELY!' as '';
SELECT '' as '';

SELECT 'NEXT STEPS' as '';
SELECT '-----------' as '';
SELECT '1. Login to admin panel at /admin/' as 'Step';
SELECT '2. Change admin password immediately' as '';
SELECT '3. Update company information in Settings' as '';
SELECT '4. Configure email settings (if using email notifications)' as '';
SELECT '5. Update payment method details in Payment Methods' as '';
SELECT '6. Activate payment methods after updating details' as '';
SELECT '7. Add your venues, halls, menus, and services' as '';
SELECT '8. Test the booking flow from the frontend' as '';
SELECT '' as '';

SELECT '============================================' as '';
SELECT 'READY FOR PRODUCTION USE!' as 'Final Status';
SELECT '============================================' as '';

-- ============================================================================
-- TRIGGERS
-- ============================================================================

-- Trigger: Keep booking_status and advance_payment_received in sync with payment_status.
-- Logic:
--   payment_status = 'pending'           → booking_status = 'pending',   advance_payment_received = 0
--   payment_status = 'partial'           → booking_status = 'confirmed', advance_payment_received = 1
--   payment_status = 'paid'              → booking_status = 'completed', advance_payment_received = 1
--   payment_status = 'cancelled'         → no automatic change
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
-- SCHEMA UPGRADES: Add any missing columns to existing tables
-- Safe to run on both fresh and existing databases (MySQL 5.7+ compatible)
-- ============================================================================

DROP PROCEDURE IF EXISTS add_pano_image_columns;

DELIMITER $$
CREATE PROCEDURE add_pano_image_columns()
BEGIN
    -- Add pano_image to venues if it doesn't exist
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'venues'
          AND column_name = 'pano_image'
    ) THEN
        ALTER TABLE venues ADD COLUMN pano_image VARCHAR(255) DEFAULT NULL
            COMMENT '360° equirectangular panoramic image filename (stored in uploads/)'
            AFTER map_link;
    END IF;

    -- Add pano_image to halls if it doesn't exist
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'halls'
          AND column_name = 'pano_image'
    ) THEN
        ALTER TABLE halls ADD COLUMN pano_image VARCHAR(255) DEFAULT NULL
            COMMENT '360° equirectangular panoramic image filename (stored in uploads/)'
            AFTER features;
    END IF;
END$$

DELIMITER ;

CALL add_pano_image_columns();
DROP PROCEDURE IF EXISTS add_pano_image_columns;

-- ============================================================================
-- SCHEMA UPGRADES: Add any missing columns to existing tables
-- Safe to run on both fresh and existing databases (idempotent).
-- These procedures are required to keep older installations in sync with the
-- current codebase.  createBooking() in includes/functions.php inserts all of
-- these columns; if any are missing the entire booking is rolled back.
-- ============================================================================

-- ---- bookings table ---------------------------------------------------------

DROP PROCEDURE IF EXISTS upgrade_bookings_columns;

DELIMITER $$
CREATE PROCEDURE upgrade_bookings_columns()
BEGIN
    -- Add custom_venue_name to bookings if it doesn't exist
    -- (required by createBooking() for custom/own-venue bookings)
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'bookings'
          AND column_name = 'custom_venue_name'
    ) THEN
        ALTER TABLE bookings
            ADD COLUMN custom_venue_name VARCHAR(255) DEFAULT NULL
                COMMENT 'Venue name when customer brings own venue (hall_id is NULL)'
            AFTER hall_id;
    END IF;

    -- Add custom_hall_name to bookings if it doesn't exist
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'bookings'
          AND column_name = 'custom_hall_name'
    ) THEN
        ALTER TABLE bookings
            ADD COLUMN custom_hall_name VARCHAR(255) DEFAULT NULL
                COMMENT 'Hall/location name when customer brings own venue (hall_id is NULL)'
            AFTER custom_venue_name;
    END IF;

    -- Make hall_id nullable if it was created NOT NULL in an older version
    -- (custom-venue bookings set hall_id to NULL)
    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'bookings'
          AND column_name = 'hall_id'
          AND is_nullable = 'NO'
    ) THEN
        SET @fk_hall = NULL;
        SELECT CONSTRAINT_NAME INTO @fk_hall
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'bookings'
          AND COLUMN_NAME = 'hall_id'
          AND REFERENCED_TABLE_NAME = 'halls'
        LIMIT 1;

        IF @fk_hall IS NOT NULL THEN
            SET @drop_fk_hall = CONCAT('ALTER TABLE bookings DROP FOREIGN KEY `', @fk_hall, '`');
            PREPARE stmt FROM @drop_fk_hall;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
        END IF;

        ALTER TABLE bookings MODIFY COLUMN hall_id INT DEFAULT NULL;

        -- Re-add the FK as nullable (ON DELETE SET NULL preserves the booking)
        ALTER TABLE bookings
            ADD CONSTRAINT fk_bookings_hall_id
                FOREIGN KEY (hall_id) REFERENCES halls(id) ON DELETE SET NULL;
    END IF;

    -- Add start_time to bookings if it doesn't exist
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'bookings'
          AND column_name = 'start_time'
    ) THEN
        ALTER TABLE bookings ADD COLUMN start_time TIME DEFAULT NULL AFTER event_date;
        UPDATE bookings SET start_time = '06:00:00' WHERE shift = 'morning'   AND start_time IS NULL;
        UPDATE bookings SET start_time = '12:00:00' WHERE shift = 'afternoon' AND start_time IS NULL;
        UPDATE bookings SET start_time = '18:00:00' WHERE shift = 'evening'   AND start_time IS NULL;
        UPDATE bookings SET start_time = '06:00:00' WHERE shift = 'fullday'   AND start_time IS NULL;
    END IF;

    -- Add end_time to bookings if it doesn't exist
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'bookings'
          AND column_name = 'end_time'
    ) THEN
        ALTER TABLE bookings ADD COLUMN end_time TIME DEFAULT NULL AFTER start_time;
        UPDATE bookings SET end_time = '12:00:00' WHERE shift = 'morning'   AND end_time IS NULL;
        UPDATE bookings SET end_time = '18:00:00' WHERE shift = 'afternoon' AND end_time IS NULL;
        UPDATE bookings SET end_time = '23:00:00' WHERE shift = 'evening'   AND end_time IS NULL;
        UPDATE bookings SET end_time = '23:00:00' WHERE shift = 'fullday'   AND end_time IS NULL;
    END IF;

    -- Add advance_payment_received to bookings if it doesn't exist
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'bookings'
          AND column_name = 'advance_payment_received'
    ) THEN
        ALTER TABLE bookings
            ADD COLUMN advance_payment_received TINYINT(1) DEFAULT 0
                COMMENT 'Whether advance payment has been received (0=No, 1=Yes)'
            AFTER payment_status;
        ALTER TABLE bookings ADD INDEX idx_advance_payment_received (advance_payment_received);
    END IF;
END$$

DELIMITER ;

CALL upgrade_bookings_columns();
DROP PROCEDURE IF EXISTS upgrade_bookings_columns;

-- ---- booking_services table -------------------------------------------------
-- createBooking() inserts description, category, added_by, quantity,
-- sub_service_id and design_id.  If any are absent the INSERT fails and rolls
-- back the whole booking (including the parent bookings row).

DROP PROCEDURE IF EXISTS upgrade_booking_services_columns;

DELIMITER $$
CREATE PROCEDURE upgrade_booking_services_columns()
BEGIN
    -- Add description column if missing
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'booking_services'
          AND column_name = 'description'
    ) THEN
        ALTER TABLE booking_services ADD COLUMN description TEXT AFTER price;
    END IF;

    -- Add category column if missing
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'booking_services'
          AND column_name = 'category'
    ) THEN
        ALTER TABLE booking_services ADD COLUMN category VARCHAR(100) AFTER description;
    END IF;

    -- Add added_by column if missing
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'booking_services'
          AND column_name = 'added_by'
    ) THEN
        ALTER TABLE booking_services
            ADD COLUMN added_by ENUM('user', 'admin') DEFAULT 'user'
                COMMENT 'Who added the service: user during booking or admin later'
            AFTER category;
        UPDATE booking_services SET added_by = 'user' WHERE added_by IS NULL;
    END IF;

    -- Add quantity column if missing
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'booking_services'
          AND column_name = 'quantity'
    ) THEN
        ALTER TABLE booking_services
            ADD COLUMN quantity INT DEFAULT 1
                COMMENT 'Quantity of service'
            AFTER added_by;
        UPDATE booking_services SET quantity = 1 WHERE quantity IS NULL;
    END IF;

    -- Add sub_service_id column if missing
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'booking_services'
          AND column_name = 'sub_service_id'
    ) THEN
        ALTER TABLE booking_services
            ADD COLUMN sub_service_id INT DEFAULT NULL
                COMMENT 'References service_sub_services.id if this is a design selection'
            AFTER quantity;
    END IF;

    -- Add design_id column if missing
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'booking_services'
          AND column_name = 'design_id'
    ) THEN
        ALTER TABLE booking_services
            ADD COLUMN design_id INT DEFAULT NULL
                COMMENT 'References service_designs.id if this is a design selection'
            AFTER sub_service_id;
    END IF;

    -- Remove the FK on service_id if it exists.
    -- Admin-added services use service_id = 0 which violates the FK.
    SET @fk_svc = NULL;
    SELECT CONSTRAINT_NAME INTO @fk_svc
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'booking_services'
      AND COLUMN_NAME = 'service_id'
      AND REFERENCED_TABLE_NAME = 'additional_services'
    LIMIT 1;

    IF @fk_svc IS NOT NULL THEN
        SET @drop_fk_svc = CONCAT('ALTER TABLE booking_services DROP FOREIGN KEY `', @fk_svc, '`');
        PREPARE stmt FROM @drop_fk_svc;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;

    -- Ensure service_id allows 0 (admin services have no reference row)
    ALTER TABLE booking_services
        MODIFY service_id INT NOT NULL DEFAULT 0
            COMMENT '0 for admin services, >0 for user services referencing additional_services';

    -- Add indexes if missing
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.statistics
        WHERE table_schema = DATABASE()
          AND table_name = 'booking_services'
          AND index_name = 'idx_booking_services_added_by'
    ) THEN
        CREATE INDEX idx_booking_services_added_by ON booking_services(added_by);
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.statistics
        WHERE table_schema = DATABASE()
          AND table_name = 'booking_services'
          AND index_name = 'idx_booking_services_service_id'
    ) THEN
        CREATE INDEX idx_booking_services_service_id ON booking_services(service_id);
    END IF;
END$$

DELIMITER ;

CALL upgrade_booking_services_columns();
DROP PROCEDURE IF EXISTS upgrade_booking_services_columns;

-- ---- service_designs table --------------------------------------------------
-- Required by getServiceDesigns() (called from booking-step4.php) and the
-- design lookup inside createBooking().  Missing service_id causes a
-- PDOException that prevents booking-step4 from loading, so users cannot
-- reach step 5 to submit a booking.

DROP PROCEDURE IF EXISTS upgrade_service_designs_columns;

DELIMITER $$
CREATE PROCEDURE upgrade_service_designs_columns()
BEGIN
    -- Add service_id column if missing
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'service_designs'
          AND column_name = 'service_id'
    ) THEN
        ALTER TABLE service_designs
            ADD COLUMN service_id INT DEFAULT NULL
                COMMENT 'References additional_services.id (direct service design flow)'
            AFTER sub_service_id,
            ADD CONSTRAINT fk_service_designs_service
                FOREIGN KEY (service_id) REFERENCES additional_services(id) ON DELETE CASCADE;
    END IF;

    -- Make sub_service_id nullable (direct-service designs have no sub-service)
    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'service_designs'
          AND column_name = 'sub_service_id'
          AND is_nullable = 'NO'
    ) THEN
        SET @fk_sd_ss = NULL;
        SELECT CONSTRAINT_NAME INTO @fk_sd_ss
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'service_designs'
          AND COLUMN_NAME = 'sub_service_id'
          AND REFERENCED_TABLE_NAME = 'service_sub_services'
        LIMIT 1;

        IF @fk_sd_ss IS NOT NULL AND @fk_sd_ss REGEXP '^[A-Za-z0-9_]+$' THEN
            SET @drop_fk_sd = CONCAT('ALTER TABLE service_designs DROP FOREIGN KEY `', @fk_sd_ss, '`');
            PREPARE stmt FROM @drop_fk_sd;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
        END IF;

        ALTER TABLE service_designs
            MODIFY COLUMN sub_service_id INT DEFAULT NULL
                COMMENT 'References service_sub_services.id (legacy sub-service flow)';

        IF NOT EXISTS (
            SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'service_designs'
              AND CONSTRAINT_NAME = 'fk_service_designs_sub_service'
        ) THEN
            ALTER TABLE service_designs
                ADD CONSTRAINT fk_service_designs_sub_service
                    FOREIGN KEY (sub_service_id) REFERENCES service_sub_services(id) ON DELETE CASCADE;
        END IF;
    END IF;
END$$

DELIMITER ;

CALL upgrade_service_designs_columns();
DROP PROCEDURE IF EXISTS upgrade_service_designs_columns;

-- ---- additional_services.vendor_type_id (proper FK to vendor_types) --------
-- Adds a vendor_type_id integer FK column to additional_services so that the
-- service ↔ vendor-type relationship is a proper reference instead of a
-- free-text label copy.  Safe to run multiple times (idempotent).

DROP PROCEDURE IF EXISTS upgrade_additional_services_vendor_type_id;

DELIMITER $$
CREATE PROCEDURE upgrade_additional_services_vendor_type_id()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name   = 'additional_services'
          AND column_name  = 'vendor_type_id'
    ) THEN
        ALTER TABLE additional_services
            ADD COLUMN vendor_type_id INT DEFAULT NULL
                COMMENT 'FK → vendor_types.id; replaces free-text category field'
            AFTER category;
        ALTER TABLE additional_services
            ADD INDEX idx_additional_services_vendor_type_id (vendor_type_id);
        ALTER TABLE additional_services
            ADD CONSTRAINT fk_additional_services_vendor_type
                FOREIGN KEY (vendor_type_id) REFERENCES vendor_types(id) ON DELETE SET NULL;
    END IF;

    -- Populate vendor_type_id for rows that still have only a category label
    UPDATE additional_services s
    JOIN vendor_types vt ON LOWER(TRIM(vt.label)) = LOWER(TRIM(s.category))
    SET s.vendor_type_id = vt.id
    WHERE s.vendor_type_id IS NULL
      AND s.category IS NOT NULL
      AND s.category <> '';
END$$
DELIMITER ;

CALL upgrade_additional_services_vendor_type_id();
DROP PROCEDURE IF EXISTS upgrade_additional_services_vendor_type_id;
