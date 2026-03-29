-- ============================================================================
-- PRODUCTION DATABASE FOR SHARED HOSTING (LIVE DEPLOYMENT)
-- ============================================================================
-- 
-- This is a CLEAN production database script with NO sample data.
-- It includes:
--   1. All required tables with proper relationships (35 tables)
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
-- TABLE: menu_sections (sections within a menu for custom item selection)
-- ============================================================================
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

-- ============================================================================
-- TABLE: menu_groups (groups within a menu section)
-- ============================================================================
CREATE TABLE IF NOT EXISTS menu_groups (
    id INT PRIMARY KEY AUTO_INCREMENT,
    menu_section_id INT NOT NULL,
    group_name VARCHAR(255) NOT NULL,
    photo VARCHAR(255) DEFAULT NULL COMMENT 'Optional thumbnail photo for the group',
    choose_limit INT DEFAULT NULL COMMENT 'NULL = inherit from section, >0 = per-group limit',
    extra_charge_per_item DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Charge per item selected beyond choose_limit (0 = no over-limit charge)',
    display_order INT NOT NULL DEFAULT 0,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (menu_section_id) REFERENCES menu_sections(id) ON DELETE CASCADE,
    INDEX idx_menu_groups_section_id (menu_section_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- TABLE: menu_group_items (items within a menu group with optional extra charges)
-- ============================================================================
CREATE TABLE IF NOT EXISTS menu_group_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    menu_group_id INT NOT NULL,
    item_name VARCHAR(255) NOT NULL,
    sub_category VARCHAR(255) DEFAULT NULL COMMENT 'Display-only label, e.g. Paneer Snacks',
    photo VARCHAR(255) DEFAULT NULL COMMENT 'Optional item photo displayed as circular icon in booking UI',
    extra_charge DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '0 = included in base price',
    display_order INT NOT NULL DEFAULT 0,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (menu_group_id) REFERENCES menu_groups(id) ON DELETE CASCADE,
    INDEX idx_menu_group_items_group_id (menu_group_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- TABLE: hall_time_slots
-- ============================================================================
CREATE TABLE IF NOT EXISTS hall_time_slots (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    hall_id INT NOT NULL,
    slot_name VARCHAR(100) NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    price_override DECIMAL(10,2) DEFAULT NULL COMMENT 'NULL = use hall base_price',
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_hts_hall FOREIGN KEY (hall_id) REFERENCES halls(id) ON DELETE CASCADE,
    INDEX idx_hts_hall_status (hall_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- TABLE: booking_time_slots (junction: booking ↔ individual hall_time_slots)
-- ============================================================================
CREATE TABLE IF NOT EXISTS booking_time_slots (
    id                INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    booking_id        INT NOT NULL,
    hall_time_slot_id INT NOT NULL,
    CONSTRAINT fk_bts_booking   FOREIGN KEY (booking_id)        REFERENCES bookings(id)        ON DELETE CASCADE,
    CONSTRAINT fk_bts_hall_slot FOREIGN KEY (hall_time_slot_id) REFERENCES hall_time_slots(id) ON DELETE CASCADE,
    INDEX idx_bts_booking   (booking_id),
    INDEX idx_bts_hall_slot (hall_time_slot_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
    guest_limit INT NOT NULL DEFAULT 0 COMMENT 'Max guests included in package price. Extra menu charges apply only above this limit.',
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
    service_id INT DEFAULT NULL COMMENT 'FK → additional_services.id; NULL for legacy free-text features',
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (package_id) REFERENCES service_packages(id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES additional_services(id) ON DELETE SET NULL,
    INDEX idx_package_id (package_id),
    INDEX idx_display_order (display_order),
    INDEX idx_spf_service_id (service_id)
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
    menu_special_instructions TEXT DEFAULT NULL COMMENT 'Special instructions for the menu entered during booking',
    booking_status ENUM('pending', 'payment_submitted', 'confirmed', 'cancelled', 'completed') DEFAULT 'pending',
    payment_status ENUM('pending', 'partial', 'paid', 'cancelled') DEFAULT 'pending',
    advance_payment_received TINYINT(1) DEFAULT 0 COMMENT 'Whether advance payment has been received (0=No, 1=Yes)',
    advance_amount_received DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT 'Actual advance payment amount received from customer (manually entered by admin)',
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
    extra_total DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Sum of extra_charge values from custom item selections',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (menu_id) REFERENCES menus(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- TABLE: booking_menu_item_selections (immutable snapshot of custom item selections)
-- ============================================================================
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
-- TABLE: user_reviews (customer-submitted reviews via dynamic token links)
-- ============================================================================
CREATE TABLE IF NOT EXISTS user_reviews (
    id             INT PRIMARY KEY AUTO_INCREMENT,
    booking_id     INT NULL                               COMMENT 'FK → bookings.id; nullable for non-booking reviews',
    token          VARCHAR(64) NOT NULL UNIQUE            COMMENT 'Secure random token for review link',
    reviewer_name  VARCHAR(255) NOT NULL DEFAULT ''       COMMENT 'Name entered by reviewer',
    reviewer_email VARCHAR(255) NOT NULL DEFAULT ''       COMMENT 'Email entered by reviewer',
    rating         TINYINT NOT NULL DEFAULT 5             COMMENT '1–5 star rating',
    review_text    TEXT NOT NULL DEFAULT ''               COMMENT 'Review body text',
    submitted      TINYINT(1) NOT NULL DEFAULT 0          COMMENT '0 = token issued only; 1 = review submitted',
    status         ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending' COMMENT 'Admin moderation status',
    admin_note     TEXT                                   COMMENT 'Internal note from admin (not shown publicly)',
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ur_token (token),
    INDEX idx_ur_booking_id (booking_id),
    INDEX idx_ur_status (status),
    INDEX idx_ur_submitted (submitted),
    CONSTRAINT fk_user_reviews_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='User-submitted reviews via token links';

-- ============================================================================
-- TABLE: gallery_card_groups (named groups for the gallery section)
-- ============================================================================
CREATE TABLE IF NOT EXISTS gallery_card_groups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    display_order INT NOT NULL DEFAULT 0,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_by INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_gcg_status (status),
    INDEX idx_gcg_display_order (display_order),
    CONSTRAINT fk_gcg_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
    card_group_id INT NULL DEFAULT NULL COMMENT 'FK → gallery_card_groups.id; named group override',
    event_category VARCHAR(150) DEFAULT NULL COMMENT 'Event category folder for work_photos section (e.g. Wedding Photos)',
    display_order INT DEFAULT 0,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_section (section),
    INDEX idx_card_id (card_id),
    INDEX idx_card_group_id (card_group_id),
    INDEX idx_event_category (event_category),
    INDEX idx_status (status),
    INDEX idx_display_order (display_order),
    CONSTRAINT fk_site_images_card_group FOREIGN KEY (card_group_id) REFERENCES gallery_card_groups(id) ON DELETE SET NULL
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
    status ENUM('active', 'inactive', 'unapproved') DEFAULT 'active',
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
    vendor_id INT DEFAULT NULL COMMENT 'NULL for manual (non-system) vendors',
    manual_vendor_name  VARCHAR(255) DEFAULT NULL COMMENT 'Free-text vendor name when vendor_id IS NULL',
    manual_vendor_phone VARCHAR(50)  DEFAULT NULL COMMENT 'Free-text vendor phone when vendor_id IS NULL',
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
    sender_email VARCHAR(255) NULL DEFAULT NULL COMMENT 'Email of the sender for public transfers',
    sender_message TEXT NULL DEFAULT NULL COMMENT 'Optional message from sender to recipient',
    transfer_source ENUM('admin', 'public') NOT NULL DEFAULT 'admin' COMMENT 'Origin: admin-created folder or public transfer',
    created_by INT NULL COMMENT 'Admin user who created the folder',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_download_token (download_token),
    INDEX idx_status (status),
    INDEX idx_expires_at (expires_at),
    INDEX idx_created_at (created_at),
    INDEX idx_transfer_source (transfer_source)
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

-- ---- customers table --------------------------------------------------------
-- getOrCreateCustomer() references city and loyalty_points.  These may be
-- absent on databases created from older schema versions.

DROP PROCEDURE IF EXISTS upgrade_customers_columns;

DELIMITER $$
CREATE PROCEDURE upgrade_customers_columns()
BEGIN
    -- Add city column if missing
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'customers'
          AND column_name = 'city'
    ) THEN
        ALTER TABLE customers ADD COLUMN city VARCHAR(100) NULL AFTER address;
    END IF;

    -- Add loyalty_points column if missing
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'customers'
          AND column_name = 'loyalty_points'
    ) THEN
        ALTER TABLE customers
            ADD COLUMN loyalty_points INT NOT NULL DEFAULT 0
                COMMENT 'Accumulated loyalty points'
            AFTER city;
    END IF;
END$$
DELIMITER ;

CALL upgrade_customers_columns();
DROP PROCEDURE IF EXISTS upgrade_customers_columns;

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

    -- Add advance_amount_received to bookings if it doesn't exist
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'bookings'
          AND column_name = 'advance_amount_received'
    ) THEN
        ALTER TABLE bookings
            ADD COLUMN advance_amount_received DECIMAL(10,2) NOT NULL DEFAULT 0
                COMMENT 'Actual advance payment amount received from customer (manually entered by admin)'
            AFTER advance_payment_received;
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

-- ============================================================================
-- UPGRADE: Create hall_time_slots table for existing installations
-- ============================================================================
DELIMITER $$
DROP PROCEDURE IF EXISTS upgrade_hall_time_slots$$
CREATE PROCEDURE upgrade_hall_time_slots()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.tables
        WHERE table_schema = DATABASE()
          AND table_name = 'hall_time_slots'
    ) THEN
        CREATE TABLE hall_time_slots (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            hall_id INT NOT NULL,
            slot_name VARCHAR(100) NOT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            price_override DECIMAL(10,2) DEFAULT NULL,
            status ENUM('active','inactive') NOT NULL DEFAULT 'active',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_hts_hall FOREIGN KEY (hall_id) REFERENCES halls(id) ON DELETE CASCADE,
            INDEX idx_hts_hall_status (hall_id, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    END IF;
END$$
DELIMITER ;
CALL upgrade_hall_time_slots();
DROP PROCEDURE IF EXISTS upgrade_hall_time_slots;

-- ============================================================================
-- UPGRADE: Create booking_time_slots table for existing installations
-- ============================================================================
DELIMITER $$
DROP PROCEDURE IF EXISTS upgrade_booking_time_slots$$
CREATE PROCEDURE upgrade_booking_time_slots()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.tables
        WHERE table_schema = DATABASE()
          AND table_name = 'booking_time_slots'
    ) THEN
        CREATE TABLE booking_time_slots (
            id                INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            booking_id        INT NOT NULL,
            hall_time_slot_id INT NOT NULL,
            CONSTRAINT fk_bts_booking   FOREIGN KEY (booking_id)        REFERENCES bookings(id)        ON DELETE CASCADE,
            CONSTRAINT fk_bts_hall_slot FOREIGN KEY (hall_time_slot_id) REFERENCES hall_time_slots(id) ON DELETE CASCADE,
            INDEX idx_bts_booking   (booking_id),
            INDEX idx_bts_hall_slot (hall_time_slot_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    END IF;
END$$
DELIMITER ;
CALL upgrade_booking_time_slots();
DROP PROCEDURE IF EXISTS upgrade_booking_time_slots;

-- ============================================================================
-- UPGRADE: Add shared_folders.total_downloads for existing installations
-- ============================================================================
-- This column was present in the original CREATE TABLE statement but was never
-- included in the ALTER TABLE upgrade procedures.  On older installations the
-- column may therefore be absent, causing a PDOException when folder.php tries
-- to increment it after a single-photo download and showing the user
-- "Access Denied – Download failed. Please try again."
DROP PROCEDURE IF EXISTS upgrade_shared_folders_total_downloads;

DELIMITER $$
CREATE PROCEDURE upgrade_shared_folders_total_downloads()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'shared_folders'
          AND column_name = 'total_downloads'
    ) THEN
        ALTER TABLE shared_folders
            ADD COLUMN total_downloads INT DEFAULT 0
            COMMENT 'Total download count across all photos'
            AFTER max_downloads;
    END IF;
END$$
DELIMITER ;

CALL upgrade_shared_folders_total_downloads();
DROP PROCEDURE IF EXISTS upgrade_shared_folders_total_downloads;

-- ============================================================================
-- UPGRADE: Create gallery_card_groups table and add card_group_id to site_images
-- ============================================================================
-- Required by admin/gallery-cards/ and getImagesByCards() in functions.php.
-- Safe to run on fresh installs (table already exists) and existing installs
-- (idempotent: checks before altering).

DROP PROCEDURE IF EXISTS upgrade_gallery_card_groups;

DELIMITER $$
CREATE PROCEDURE upgrade_gallery_card_groups()
BEGIN
    -- 1. Create gallery_card_groups table if it does not exist
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.tables
        WHERE table_schema = DATABASE()
          AND table_name = 'gallery_card_groups'
    ) THEN
        CREATE TABLE gallery_card_groups (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            description TEXT NULL,
            display_order INT NOT NULL DEFAULT 0,
            status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
            created_by INT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_gcg_status (status),
            INDEX idx_gcg_display_order (display_order),
            CONSTRAINT fk_gcg_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    END IF;

    -- 2. Add card_group_id column to site_images if missing
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'site_images'
          AND column_name = 'card_group_id'
    ) THEN
        ALTER TABLE site_images
            ADD COLUMN card_group_id INT NULL DEFAULT NULL
                COMMENT 'FK → gallery_card_groups.id; named group override'
            AFTER card_id,
            ADD INDEX idx_card_group_id (card_group_id);
    END IF;

    -- 3. Add FK constraint if gallery_card_groups exists and constraint is missing
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'site_images'
          AND CONSTRAINT_NAME = 'fk_site_images_card_group'
    ) THEN
        ALTER TABLE site_images
            ADD CONSTRAINT fk_site_images_card_group
                FOREIGN KEY (card_group_id)
                REFERENCES gallery_card_groups(id)
                ON DELETE SET NULL;
    END IF;
END$$
DELIMITER ;

CALL upgrade_gallery_card_groups();
DROP PROCEDURE IF EXISTS upgrade_gallery_card_groups;

-- ============================================================================
-- UPGRADE: Create custom menu selection tables and add new columns
-- ============================================================================
-- Adds menu_sections, menu_groups, menu_group_items, booking_menu_item_selections
-- tables and the extra_total / menu_special_instructions columns required by
-- the "Customize Your Menu Selections" feature in booking-step3.
-- Safe to run on fresh installs (tables/columns already exist via CREATE TABLE
-- IF NOT EXISTS above) and on existing installs (all checks are idempotent).

DROP PROCEDURE IF EXISTS upgrade_custom_menu_selection;

DELIMITER $$
CREATE PROCEDURE upgrade_custom_menu_selection()
BEGIN
    -- 1. Create menu_sections if missing
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.tables
        WHERE table_schema = DATABASE() AND table_name = 'menu_sections'
    ) THEN
        CREATE TABLE menu_sections (
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
    END IF;

    -- 2. Create menu_groups if missing
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.tables
        WHERE table_schema = DATABASE() AND table_name = 'menu_groups'
    ) THEN
        CREATE TABLE menu_groups (
            id INT PRIMARY KEY AUTO_INCREMENT,
            menu_section_id INT NOT NULL,
            group_name VARCHAR(255) NOT NULL,
            photo VARCHAR(255) DEFAULT NULL COMMENT 'Optional thumbnail photo for the group',
            choose_limit INT DEFAULT NULL COMMENT 'NULL = inherit from section, >0 = per-group limit',
            extra_charge_per_item DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Charge per item selected beyond choose_limit (0 = no over-limit charge)',
            display_order INT NOT NULL DEFAULT 0,
            status ENUM('active','inactive') NOT NULL DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (menu_section_id) REFERENCES menu_sections(id) ON DELETE CASCADE,
            INDEX idx_menu_groups_section_id (menu_section_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    END IF;

    -- 3. Add photo column to menu_groups if missing (for installs that created the
    --    table before add_photo_to_menu_groups.sql was applied)
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'menu_groups'
          AND column_name = 'photo'
    ) THEN
        ALTER TABLE menu_groups
            ADD COLUMN photo VARCHAR(255) DEFAULT NULL
                COMMENT 'Optional thumbnail photo for the group'
            AFTER group_name;
    END IF;

    -- 3b. Add extra_charge_per_item to menu_groups if missing (for installs created
    --     before add_extra_charge_per_item_to_menu_groups.sql was applied)
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'menu_groups'
          AND column_name = 'extra_charge_per_item'
    ) THEN
        ALTER TABLE menu_groups
            ADD COLUMN extra_charge_per_item DECIMAL(10,2) NOT NULL DEFAULT 0.00
                COMMENT 'Charge per item selected beyond choose_limit (0 = no over-limit charge)'
            AFTER choose_limit;
    END IF;

    -- 4. Create menu_group_items if missing
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.tables
        WHERE table_schema = DATABASE() AND table_name = 'menu_group_items'
    ) THEN
        CREATE TABLE menu_group_items (
            id INT PRIMARY KEY AUTO_INCREMENT,
            menu_group_id INT NOT NULL,
            item_name VARCHAR(255) NOT NULL,
            sub_category VARCHAR(255) DEFAULT NULL COMMENT 'Display-only label, e.g. Paneer Snacks',
            photo VARCHAR(255) DEFAULT NULL COMMENT 'Optional item photo displayed as circular icon in booking UI',
            extra_charge DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '0 = included in base price',
            display_order INT NOT NULL DEFAULT 0,
            status ENUM('active','inactive') NOT NULL DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (menu_group_id) REFERENCES menu_groups(id) ON DELETE CASCADE,
            INDEX idx_menu_group_items_group_id (menu_group_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    END IF;

    -- 4b. Add photo column to menu_group_items if missing (for installs created
    --     before add_photo_to_menu_group_items.sql was applied)
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'menu_group_items'
          AND column_name = 'photo'
    ) THEN
        ALTER TABLE menu_group_items
            ADD COLUMN photo VARCHAR(255) DEFAULT NULL
                COMMENT 'Optional item photo displayed as circular icon in booking UI'
            AFTER sub_category;
    END IF;

    -- 5. Create booking_menu_item_selections if missing
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.tables
        WHERE table_schema = DATABASE() AND table_name = 'booking_menu_item_selections'
    ) THEN
        CREATE TABLE booking_menu_item_selections (
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
    END IF;

    -- 6. Add extra_total to booking_menus if missing
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'booking_menus'
          AND column_name = 'extra_total'
    ) THEN
        ALTER TABLE booking_menus
            ADD COLUMN extra_total DECIMAL(10,2) NOT NULL DEFAULT 0.00
                COMMENT 'Sum of extra_charge values from custom item selections'
            AFTER total_price;
    END IF;

    -- 7. Add menu_special_instructions to bookings if missing
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'bookings'
          AND column_name = 'menu_special_instructions'
    ) THEN
        ALTER TABLE bookings
            ADD COLUMN menu_special_instructions TEXT DEFAULT NULL
                COMMENT 'Special instructions for the menu entered during booking'
            AFTER special_requests;
    END IF;
END$$
DELIMITER ;

CALL upgrade_custom_menu_selection();
DROP PROCEDURE IF EXISTS upgrade_custom_menu_selection;

-- ============================================================================
-- UPGRADE: Create policy_pages table and seed default policy content
-- ============================================================================
-- Adds the policy_pages table used by the Policy Pages system (Terms,
-- Privacy Policy, Refund Policy, etc.) and the booking acceptance checkbox
-- on the final booking step.
-- Safe to run on existing installs (uses IF NOT EXISTS / INSERT IGNORE).

DROP PROCEDURE IF EXISTS upgrade_policy_pages;

DELIMITER $$
CREATE PROCEDURE upgrade_policy_pages()
BEGIN
    -- 1. Create policy_pages table if missing
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.tables
        WHERE table_schema = DATABASE()
          AND table_name = 'policy_pages'
    ) THEN
        CREATE TABLE `policy_pages` (
            `id`                 INT AUTO_INCREMENT PRIMARY KEY,
            `title`              VARCHAR(255)                    NOT NULL,
            `slug`               VARCHAR(255)                    NOT NULL,
            `content`            LONGTEXT                        NOT NULL DEFAULT '',
            `status`             ENUM('active','inactive')       NOT NULL DEFAULT 'active',
            `require_acceptance` TINYINT(1)                      NOT NULL DEFAULT 0
                                 COMMENT 'When 1, users must accept this policy before completing a booking',
            `sort_order`         INT                             NOT NULL DEFAULT 0,
            `created_at`         TIMESTAMP                       NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at`         TIMESTAMP                       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `uq_policy_slug` (`slug`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    END IF;

    -- 2. Seed default Terms and Conditions (INSERT IGNORE skips if slug already exists)
    -- require_acceptance defaults to 0 so existing bookings are not blocked;
    -- site admins can enable it from Admin → Policy Pages when ready.
    INSERT IGNORE INTO `policy_pages` (`title`, `slug`, `content`, `status`, `require_acceptance`, `sort_order`) VALUES (
        'Terms and Conditions',
        'terms-and-conditions',
        '<h2>Terms and Conditions</h2>\n<p>Welcome to our venue booking platform. By accessing or using our services, you agree to be bound by these Terms and Conditions. Please read them carefully before proceeding with a booking.</p>\n\n<h3>1. Acceptance of Terms</h3>\n<p>By making a booking, you confirm that you have read, understood, and agreed to these Terms and Conditions in their entirety.</p>\n\n<h3>2. Booking and Confirmation</h3>\n<p>All bookings are subject to availability. A booking is only confirmed once you receive a written confirmation from us.</p>\n\n<h3>3. Payment</h3>\n<p>An advance payment is required to secure your booking. The balance amount is due on or before the event date.</p>\n\n<h3>4. Cancellation Policy</h3>\n<p>Cancellations must be made in writing. Cancellation charges may apply depending on how far in advance the cancellation is made.</p>\n\n<h3>5. Governing Law</h3>\n<p>These Terms and Conditions are governed by the laws of Nepal.</p>',
        'active',
        0,
        10
    );

    -- 3. Seed default Privacy Policy
    INSERT IGNORE INTO `policy_pages` (`title`, `slug`, `content`, `status`, `require_acceptance`, `sort_order`) VALUES (
        'Privacy Policy',
        'privacy-policy',
        '<h2>Privacy Policy</h2>\n<p>Your privacy is important to us. This Privacy Policy explains how we collect, use, and protect your personal information when you use our venue booking services.</p>\n\n<h3>1. Information We Collect</h3>\n<p>We collect your name, phone number, email address, event details, and payment references.</p>\n\n<h3>2. How We Use Your Information</h3>\n<p>We use the information to process bookings, communicate about your event, and improve our services.</p>\n\n<h3>3. Information Sharing</h3>\n<p>We do not sell or transfer your personal information to third parties without your consent.</p>\n\n<h3>4. Data Security</h3>\n<p>We implement appropriate measures to protect your personal information.</p>',
        'active',
        0,
        20
    );

    -- 4. Seed default Refund Policy
    INSERT IGNORE INTO `policy_pages` (`title`, `slug`, `content`, `status`, `require_acceptance`, `sort_order`) VALUES (
        'Refund Policy',
        'refund-policy',
        '<h2>Refund Policy</h2>\n<p>We understand that plans can change. Please review our refund policy carefully before making a booking.</p>\n\n<h3>1. Cancellation and Refund Schedule</h3>\n<ul>\n  <li><strong>More than 30 days before the event:</strong> Full refund less administrative fees.</li>\n  <li><strong>15–30 days before the event:</strong> 50% of the advance payment refunded.</li>\n  <li><strong>Less than 15 days before the event:</strong> No refund.</li>\n</ul>\n\n<h3>2. Refund Processing</h3>\n<p>Approved refunds are processed within 7–14 business days.</p>',
        'active',
        0,
        30
    );

    -- 5. Reset require_acceptance for any seeded policy pages that were previously
    --    inserted with require_acceptance=1 by an earlier version of this script.
    --    NOTE: This only affects the three auto-seeded slugs; any other policy
    --    pages (including those slugs if an admin changed them intentionally via
    --    this same script) are left untouched.  Admins can re-enable acceptance
    --    requirements from Admin → Policy Pages whenever they are ready.
    UPDATE `policy_pages`
       SET `require_acceptance` = 0
     WHERE `slug` IN ('terms-and-conditions', 'privacy-policy', 'refund-policy')
       AND `require_acceptance` = 1;
END$$
DELIMITER ;

CALL upgrade_policy_pages();
DROP PROCEDURE IF EXISTS upgrade_policy_pages;

-- ---- service_package_features: add service_id column (package-to-service linking) ----

DROP PROCEDURE IF EXISTS upgrade_service_package_features;

DELIMITER $$
CREATE PROCEDURE upgrade_service_package_features()
BEGIN
    -- Add service_id column if missing (added when package-feature-to-service linking was introduced)
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'service_package_features'
          AND column_name = 'service_id'
    ) THEN
        ALTER TABLE service_package_features
            ADD COLUMN service_id INT DEFAULT NULL
                COMMENT 'FK → additional_services.id; NULL for legacy free-text features'
            AFTER feature_text;
    END IF;

    -- Add FK if the column was just created and FK doesn't exist yet
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.key_column_usage
        WHERE table_schema = DATABASE()
          AND table_name = 'service_package_features'
          AND column_name = 'service_id'
          AND referenced_table_name = 'additional_services'
    ) THEN
        -- Only add FK if additional_services table exists
        IF EXISTS (
            SELECT 1 FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = 'additional_services'
        ) THEN
            ALTER TABLE service_package_features
                ADD CONSTRAINT fk_spf_service_id
                FOREIGN KEY (service_id) REFERENCES additional_services(id) ON DELETE SET NULL;
        END IF;
    END IF;

    -- Add index on service_id if missing
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.statistics
        WHERE table_schema = DATABASE()
          AND table_name = 'service_package_features'
          AND index_name = 'idx_spf_service_id'
    ) THEN
        CREATE INDEX idx_spf_service_id ON service_package_features(service_id);
    END IF;

    -- Ensure service_package_photos table exists (created alongside service_id support)
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
END$$
DELIMITER ;

CALL upgrade_service_package_features();
DROP PROCEDURE IF EXISTS upgrade_service_package_features;

-- ============================================================================
-- UPGRADE: Create user_reviews table for token-based review submissions
-- ============================================================================
DROP PROCEDURE IF EXISTS upgrade_user_reviews;
DELIMITER $$
CREATE PROCEDURE upgrade_user_reviews()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.tables
        WHERE table_schema = DATABASE() AND table_name = 'user_reviews'
    ) THEN
        CREATE TABLE user_reviews (
            id            INT PRIMARY KEY AUTO_INCREMENT,
            booking_id    INT NULL,
            token         VARCHAR(64) NOT NULL UNIQUE,
            reviewer_name VARCHAR(255) NOT NULL DEFAULT '',
            reviewer_email VARCHAR(255) NOT NULL DEFAULT '',
            rating        TINYINT NOT NULL DEFAULT 5,
            review_text   TEXT NOT NULL DEFAULT '',
            submitted     TINYINT(1) NOT NULL DEFAULT 0,
            status        ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
            admin_note    TEXT,
            created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_ur_token (token),
            INDEX idx_ur_booking_id (booking_id),
            INDEX idx_ur_status (status),
            INDEX idx_ur_submitted (submitted),
            CONSTRAINT fk_user_reviews_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    END IF;
END$$
DELIMITER ;

CALL upgrade_user_reviews();
DROP PROCEDURE IF EXISTS upgrade_user_reviews;
