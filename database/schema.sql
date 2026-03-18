-- Venue Booking System Database Schema
-- NOTE: Make sure you have selected your database before running this script
-- For command line: mysql -u username -p database_name < database/schema.sql
-- For phpMyAdmin: Select your database first, then import this file
--
-- SAFE TO RE-RUN: Uses CREATE TABLE IF NOT EXISTS so it will NOT drop or
-- overwrite any existing tables or data.

-- Table: cities
CREATE TABLE IF NOT EXISTS cities (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: venues
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

-- Table: venue_images
CREATE TABLE IF NOT EXISTS venue_images (
    id INT PRIMARY KEY AUTO_INCREMENT,
    venue_id INT NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    is_primary BOOLEAN DEFAULT 0,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (venue_id) REFERENCES venues(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: halls
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

-- Table: hall_images
CREATE TABLE IF NOT EXISTS hall_images (
    id INT PRIMARY KEY AUTO_INCREMENT,
    hall_id INT NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    is_primary BOOLEAN DEFAULT 0,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (hall_id) REFERENCES halls(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: menus
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

-- Table: menu_items
CREATE TABLE IF NOT EXISTS menu_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    menu_id INT NOT NULL,
    item_name VARCHAR(255) NOT NULL,
    category VARCHAR(100),
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (menu_id) REFERENCES menus(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: hall_menus (many-to-many relationship)
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

-- Table: additional_services
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

-- Table: customers
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

-- Table: bookings
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
    INDEX idx_booking_number (booking_number),
    INDEX idx_status (booking_status),
    INDEX idx_advance_payment_received (advance_payment_received)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: booking_menus (link bookings with selected menus)
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

-- Table: booking_services (link bookings with selected services)
CREATE TABLE IF NOT EXISTS booking_services (
    id INT PRIMARY KEY AUTO_INCREMENT,
    booking_id INT NOT NULL,
    service_id INT NOT NULL DEFAULT 0 COMMENT '0 for admin-added services, >0 references additional_services',
    service_name VARCHAR(255) NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    description TEXT,
    category VARCHAR(100),
    added_by ENUM('user', 'admin') DEFAULT 'user' COMMENT 'Who added the service: user during booking or admin later',
    quantity INT DEFAULT 1 COMMENT 'Quantity of service',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    INDEX idx_booking_services_added_by (added_by),
    INDEX idx_booking_services_service_id (service_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: payment_methods
CREATE TABLE IF NOT EXISTS payment_methods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    qr_code VARCHAR(255),
    bank_details TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_display_order (display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: booking_payment_methods (junction table)
CREATE TABLE IF NOT EXISTS booking_payment_methods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    payment_method_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (payment_method_id) REFERENCES payment_methods(id) ON DELETE CASCADE,
    UNIQUE KEY unique_booking_payment_method (booking_id, payment_method_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: payments (track payment transactions)
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

-- Table: users (admin users)
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

-- Table: settings
CREATE TABLE IF NOT EXISTS settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: activity_logs
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

-- Insert default admin user (password: Admin@123)
-- ⚠️ SECURITY WARNING: Change this password immediately after installation!
-- Login at /admin/ with username: admin, password: Admin@123
-- Then update your password in the admin panel settings
INSERT IGNORE INTO users (username, password, full_name, email, role, status) 
VALUES ('admin', '$2y$10$5sw.gEWePITwobdChuwoRuRT4dtOnxCFf/RMosnL9JVeEeb3teuna', 'System Administrator', 'admin@venubooking.com', 'admin', 'active');

-- Table: site_images (for dynamic image management)
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

-- Insert default cities (Nepal)
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

-- Insert default settings
INSERT IGNORE INTO settings (setting_key, setting_value, setting_type) VALUES
('site_name', 'Venue Booking System', 'text'),
('site_logo', '', 'text'),
('site_favicon', '', 'file'),
('contact_email', 'info@venubooking.com', 'text'),
('contact_phone', '+977 1234567890', 'text'),
('contact_address', '', 'textarea'),
('currency', 'NPR', 'text'),
('tax_rate', '13', 'number'),
('advance_payment_percentage', '30', 'number'),
('company_name', '', 'text'),
('company_address', '', 'text'),
('company_phone', '', 'text'),
('company_email', '', 'text'),
('company_logo', '', 'text'),
('invoice_title', 'Booking Confirmation & Payment Receipt', 'text'),
('invoice_package_label', 'Event Package', 'text'),
('invoice_additional_items_label', 'Additional Items', 'text'),
('cancellation_policy', 'Advance payment is non-refundable in case of cancellation.
Full payment must be completed 7 days before the event date.
Cancellations made 30 days before the event will receive 50% refund of total amount (excluding advance).
Cancellations made less than 30 days before the event are non-refundable.
Date changes are subject to availability and must be requested at least 15 days in advance.', 'textarea'),
('invoice_disclaimer', 'Note: This is a computer-generated receipt. For any queries, please contact us.', 'text'),
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
('smtp_encryption', 'tls', 'text');

-- Table: vendor_types (admin-managed vendor type definitions)
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

INSERT IGNORE INTO vendor_types (slug, label, display_order) VALUES
    ('pandit',        'Pandit',            1),
    ('photographer',  'Photographer',      2),
    ('videographer',  'Videographer',      3),
    ('baje',          'Baje (Music/Band)', 4),
    ('decoration',    'Decoration',        5),
    ('catering',      'Catering',          6),
    ('other',         'Other',             7);

-- Table: vendors (service providers assigned to bookings)
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

-- Table: vendor_photos (multi-photo support for vendors)
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

-- Table: booking_vendor_assignments (assigns vendors to specific tasks within a booking)
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

-- Table: shared_folders (for folder-based photo sharing like Google Drive)
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
    created_by INT NULL COMMENT 'Admin user who created the folder',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_download_token (download_token),
    INDEX idx_status (status),
    INDEX idx_expires_at (expires_at),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: shared_photos (for photo sharing feature)
CREATE TABLE IF NOT EXISTS shared_photos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    folder_id INT NULL COMMENT 'Folder this photo belongs to, NULL for standalone photo',
    title VARCHAR(255) NOT NULL,
    description TEXT,
    image_path VARCHAR(255) NOT NULL,
    download_token VARCHAR(64) NOT NULL UNIQUE COMMENT 'Unique token for download link',
    download_count INT DEFAULT 0 COMMENT 'Number of times this photo has been downloaded',
    max_downloads INT DEFAULT NULL COMMENT 'Maximum allowed downloads, NULL for unlimited',
    expires_at DATETIME DEFAULT NULL COMMENT 'Expiration date for the download link, NULL for never',
    status ENUM('active', 'inactive', 'expired') DEFAULT 'active',
    created_by INT NULL COMMENT 'Admin user who uploaded the photo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (folder_id) REFERENCES shared_folders(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_folder_id (folder_id),
    INDEX idx_download_token (download_token),
    INDEX idx_status (status),
    INDEX idx_expires_at (expires_at),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
