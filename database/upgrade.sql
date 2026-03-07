-- ============================================================================
-- SAFE SCHEMA UPGRADE FOR VENUE BOOKING SYSTEM
-- ============================================================================
-- Run this script on an EXISTING database to add any missing tables and
-- columns without touching your data.  It is safe to run multiple times
-- (fully idempotent).
--
-- HOW TO USE:
-- 1. Make sure you have selected your database before running this script.
-- 2. Command line:
--      mysql -u username -p database_name < database/upgrade.sql
-- 3. phpMyAdmin: Select your database → Import → choose this file → Go
--
-- WHAT IT DOES:
--   • Creates any tables that do not yet exist (using CREATE TABLE IF NOT EXISTS)
--   • Adds any columns that are present in the latest schema but missing from
--     your current database (detected via information_schema)
--   • Inserts essential reference rows (admin user, cities, default settings,
--     vendor types, payment methods) only when they are absent
--   • Never drops, truncates, or overwrites existing rows
-- ============================================================================

-- NOTE: Make sure you have selected your database before running this script
-- This script does NOT create a database — you must create/select one first

-- ============================================================================
-- 1. ENSURE ALL TABLES EXIST
-- ============================================================================

CREATE TABLE IF NOT EXISTS cities (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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

CREATE TABLE IF NOT EXISTS venue_images (
    id INT PRIMARY KEY AUTO_INCREMENT,
    venue_id INT NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    is_primary BOOLEAN DEFAULT 0,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (venue_id) REFERENCES venues(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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

CREATE TABLE IF NOT EXISTS hall_images (
    id INT PRIMARY KEY AUTO_INCREMENT,
    hall_id INT NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    is_primary BOOLEAN DEFAULT 0,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (hall_id) REFERENCES halls(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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

CREATE TABLE IF NOT EXISTS menu_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    menu_id INT NOT NULL,
    item_name VARCHAR(255) NOT NULL,
    category VARCHAR(100),
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (menu_id) REFERENCES menus(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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

CREATE TABLE IF NOT EXISTS additional_services (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL,
    category VARCHAR(100),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS service_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    display_order INT DEFAULT 0,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS service_packages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    category_id INT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL DEFAULT 0,
    display_order INT DEFAULT 0,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES service_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS service_package_features (
    id INT PRIMARY KEY AUTO_INCREMENT,
    package_id INT NOT NULL,
    feature_text VARCHAR(500) NOT NULL,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (package_id) REFERENCES service_packages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS service_package_photos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    package_id INT NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    caption VARCHAR(255),
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (package_id) REFERENCES service_packages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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

CREATE TABLE IF NOT EXISTS bookings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    booking_number VARCHAR(50) UNIQUE NOT NULL,
    customer_id INT NOT NULL,
    hall_id INT NOT NULL,
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
    FOREIGN KEY (hall_id) REFERENCES halls(id),
    INDEX idx_event_date (event_date),
    INDEX idx_status (booking_status),
    INDEX idx_advance_payment_received (advance_payment_received)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    INDEX idx_booking_services_added_by (added_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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

CREATE TABLE IF NOT EXISTS booking_payment_methods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    payment_method_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (payment_method_id) REFERENCES payment_methods(id) ON DELETE CASCADE,
    UNIQUE KEY unique_booking_payment_method (booking_id, payment_method_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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

CREATE TABLE IF NOT EXISTS settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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

CREATE TABLE IF NOT EXISTS vendor_types (
    id INT PRIMARY KEY AUTO_INCREMENT,
    slug VARCHAR(100) NOT NULL UNIQUE,
    label VARCHAR(255) NOT NULL,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_display_order (display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS vendors (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    type VARCHAR(100) NOT NULL DEFAULT 'other',
    short_description VARCHAR(500) DEFAULT NULL,
    phone VARCHAR(20),
    email VARCHAR(100),
    address TEXT,
    location VARCHAR(255) DEFAULT NULL,
    photo VARCHAR(255) DEFAULT NULL,
    city_id INT NULL,
    notes TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (city_id) REFERENCES cities(id) ON DELETE SET NULL,
    INDEX idx_type (type),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS vendor_photos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    vendor_id INT NOT NULL,
    photo_path VARCHAR(255) NOT NULL,
    photo_card_id INT NOT NULL DEFAULT 1 COMMENT 'Groups vendor photos into cards of max 10',
    caption VARCHAR(255),
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE,
    INDEX idx_vendor_id (vendor_id),
    INDEX idx_photo_card_id (photo_card_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS booking_vendor_assignments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    booking_id INT NOT NULL,
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
    INDEX idx_vendor_id (vendor_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- 2. ADD MISSING COLUMNS TO EXISTING TABLES
--    Uses stored procedures + information_schema checks so it is safe to run
--    on any MySQL/MariaDB version regardless of IF NOT EXISTS support.
-- ============================================================================

DELIMITER $$

CREATE PROCEDURE venue_booking_upgrade()
BEGIN

    -- ---- venues.map_link ------------------------------------------------
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'venues'
          AND column_name = 'map_link'
    ) THEN
        ALTER TABLE venues ADD COLUMN map_link VARCHAR(500) DEFAULT NULL AFTER contact_email;
    END IF;

    -- ---- venues.city_id -------------------------------------------------
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'venues'
          AND column_name = 'city_id'
    ) THEN
        ALTER TABLE venues ADD COLUMN city_id INT NULL AFTER location;
        ALTER TABLE venues ADD CONSTRAINT fk_venues_city
            FOREIGN KEY (city_id) REFERENCES cities(id) ON DELETE SET NULL;
    END IF;

    -- ---- customers.city -------------------------------------------------
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'customers'
          AND column_name = 'city'
    ) THEN
        ALTER TABLE customers ADD COLUMN city VARCHAR(100) NULL AFTER address;
        ALTER TABLE customers ADD INDEX idx_city (city);
    END IF;

    -- ---- customers.loyalty_points ---------------------------------------
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'customers'
          AND column_name = 'loyalty_points'
    ) THEN
        ALTER TABLE customers ADD COLUMN loyalty_points INT NOT NULL DEFAULT 0 AFTER city;
    END IF;

    -- ---- bookings.start_time --------------------------------------------
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'bookings'
          AND column_name = 'start_time'
    ) THEN
        ALTER TABLE bookings ADD COLUMN start_time TIME DEFAULT NULL AFTER event_date;
        -- Back-fill from shift
        UPDATE bookings SET start_time = '06:00:00' WHERE shift = 'morning'   AND start_time IS NULL;
        UPDATE bookings SET start_time = '12:00:00' WHERE shift = 'afternoon' AND start_time IS NULL;
        UPDATE bookings SET start_time = '18:00:00' WHERE shift = 'evening'   AND start_time IS NULL;
        UPDATE bookings SET start_time = '06:00:00' WHERE shift = 'fullday'   AND start_time IS NULL;
    END IF;

    -- ---- bookings.end_time ----------------------------------------------
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'bookings'
          AND column_name = 'end_time'
    ) THEN
        ALTER TABLE bookings ADD COLUMN end_time TIME DEFAULT NULL AFTER start_time;
        -- Back-fill from shift
        UPDATE bookings SET end_time = '12:00:00' WHERE shift = 'morning'   AND end_time IS NULL;
        UPDATE bookings SET end_time = '18:00:00' WHERE shift = 'afternoon' AND end_time IS NULL;
        UPDATE bookings SET end_time = '23:00:00' WHERE shift = 'evening'   AND end_time IS NULL;
        UPDATE bookings SET end_time = '23:00:00' WHERE shift = 'fullday'   AND end_time IS NULL;
    END IF;

    -- ---- bookings.advance_payment_received ------------------------------
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

    -- ---- hall_menus.status ----------------------------------------------
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'hall_menus'
          AND column_name = 'status'
    ) THEN
        ALTER TABLE hall_menus
            ADD COLUMN status ENUM('active', 'inactive') DEFAULT 'active' AFTER menu_id;
    END IF;

    -- ---- booking_services.description -----------------------------------
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'booking_services'
          AND column_name = 'description'
    ) THEN
        ALTER TABLE booking_services ADD COLUMN description TEXT AFTER price;
    END IF;

    -- ---- booking_services.category --------------------------------------
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'booking_services'
          AND column_name = 'category'
    ) THEN
        ALTER TABLE booking_services ADD COLUMN category VARCHAR(100) AFTER description;
    END IF;

    -- ---- booking_services.added_by --------------------------------------
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

    -- ---- booking_services.quantity --------------------------------------
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

    -- ---- site_images.card_id --------------------------------------------
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'site_images'
          AND column_name = 'card_id'
    ) THEN
        ALTER TABLE site_images
            ADD COLUMN card_id INT NOT NULL DEFAULT 1
                COMMENT 'Groups photos into cards of max 10 per section'
            AFTER section;
        ALTER TABLE site_images ADD INDEX idx_card_id (card_id);
    END IF;

    -- ---- site_images.event_category -------------------------------------
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'site_images'
          AND column_name = 'event_category'
    ) THEN
        ALTER TABLE site_images
            ADD COLUMN event_category VARCHAR(150) DEFAULT NULL
                COMMENT 'Event category folder for work_photos section (e.g. Wedding Photos)'
            AFTER card_id;
        ALTER TABLE site_images ADD INDEX idx_event_category (event_category);
    END IF;

    -- ---- vendors.short_description --------------------------------------
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'vendors'
          AND column_name = 'short_description'
    ) THEN
        ALTER TABLE vendors ADD COLUMN short_description VARCHAR(500) DEFAULT NULL AFTER type;
    END IF;

    -- ---- vendors.location -----------------------------------------------
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'vendors'
          AND column_name = 'location'
    ) THEN
        ALTER TABLE vendors ADD COLUMN location VARCHAR(255) DEFAULT NULL AFTER address;
    END IF;

    -- ---- vendors.photo --------------------------------------------------
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'vendors'
          AND column_name = 'photo'
    ) THEN
        ALTER TABLE vendors ADD COLUMN photo VARCHAR(255) DEFAULT NULL AFTER location;
    END IF;

    -- ---- vendors.city_id ------------------------------------------------
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'vendors'
          AND column_name = 'city_id'
    ) THEN
        ALTER TABLE vendors ADD COLUMN city_id INT NULL AFTER photo;
        ALTER TABLE vendors ADD CONSTRAINT fk_vendors_city
            FOREIGN KEY (city_id) REFERENCES cities(id) ON DELETE SET NULL;
    END IF;

    -- ---- vendor_photos.photo_card_id ------------------------------------
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'vendor_photos'
          AND column_name = 'photo_card_id'
    ) THEN
        ALTER TABLE vendor_photos
            ADD COLUMN photo_card_id INT NOT NULL DEFAULT 1
                COMMENT 'Groups vendor photos into cards of max 10'
            AFTER vendor_id;
        ALTER TABLE vendor_photos ADD INDEX idx_photo_card_id (photo_card_id);
    END IF;

    -- ---- venues.pano_image ----------------------------------------------
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

    -- ---- halls.pano_image -----------------------------------------------
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

CALL venue_booking_upgrade();
DROP PROCEDURE IF EXISTS venue_booking_upgrade;

-- ============================================================================
-- 3. INSERT MISSING REFERENCE DATA
-- ============================================================================

-- Default admin user (password: Admin@123)
-- Skipped if a user with this username already exists.
-- ⚠️  Change this password immediately after first login!
INSERT IGNORE INTO users (username, password, full_name, email, role, status)
VALUES ('admin', '$2y$10$5sw.gEWePITwobdChuwoRuRT4dtOnxCFf/RMosnL9JVeEeb3teuna',
        'System Administrator', 'admin@venubooking.com', 'admin', 'active');

-- Default cities (Nepal) — skipped for any city name that already exists
INSERT IGNORE INTO cities (name, status) VALUES
('Kathmandu', 'active'), ('Pokhara', 'active'), ('Lalitpur (Patan)', 'active'),
('Bhaktapur', 'active'), ('Biratnagar', 'active'), ('Birgunj', 'active'),
('Butwal', 'active'), ('Dharan', 'active'), ('Hetauda', 'active'),
('Itahari', 'active'), ('Janakpur', 'active'), ('Nepalgunj', 'active'),
('Bharatpur', 'active'), ('Dhangadhi', 'active'), ('Tulsipur', 'active');

-- Default vendor types — skipped for any slug that already exists
INSERT IGNORE INTO vendor_types (slug, label, display_order) VALUES
('pandit', 'Pandit / Pujari', 1),
('photographer', 'Photographer', 2),
('videographer', 'Videographer', 3),
('baje', 'Baje / Music Band', 4),
('decorator', 'Decorator', 5),
('catering', 'Catering Service', 6),
('makeup', 'Makeup Artist', 7),
('dj', 'DJ / Sound System', 8),
('transport', 'Transport / Car Rental', 9),
('other', 'Other', 10);

-- Core system settings — each key inserted only if it does not already exist
INSERT IGNORE INTO settings (setting_key, setting_value, setting_type) VALUES
('company_name', 'Venue Booking System', 'text'),
('company_email', 'info@venubooking.com', 'email'),
('company_phone', '+977-0000000000', 'text'),
('company_address', 'Kathmandu, Nepal', 'text'),
('currency_symbol', 'Rs.', 'text'),
('tax_percentage', '13', 'number'),
('advance_payment_percentage', '25', 'number'),
('booking_confirmation_email', '1', 'boolean'),
('timezone', 'Asia/Kathmandu', 'text');

-- Default payment methods — inserted only when the table is completely empty
INSERT IGNORE INTO payment_methods (name, bank_details, status, display_order)
SELECT * FROM (
    SELECT 'Bank Transfer' AS name,
           'Bank: [Your Bank Name]\nAccount Name: [Account Holder Name]\nAccount Number: [Account Number]\nBranch: [Branch Name]' AS bank_details,
           'inactive' AS status, 1 AS display_order
    UNION ALL
    SELECT 'eSewa',
           'eSewa ID: [Your eSewa ID]\neSewa Number: [Your eSewa Number]',
           'inactive', 2
    UNION ALL
    SELECT 'Khalti',
           'Khalti ID: [Your Khalti ID]\nKhalti Number: [Your Khalti Number]',
           'inactive', 3
    UNION ALL
    SELECT 'Cash Payment',
           'Cash payment can be made at our office during business hours.',
           'active', 4
) AS defaults
WHERE NOT EXISTS (SELECT 1 FROM payment_methods LIMIT 1);

-- ============================================================================
-- Upgrade complete.
-- ============================================================================
SELECT 'Upgrade complete — your existing data has not been modified.' AS Status;
