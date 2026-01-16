-- ============================================================================
-- PRODUCTION DATABASE SETUP FOR VENUE BOOKING SYSTEM
-- ============================================================================
-- This is a production-ready database script that includes:
--   1. All required tables with proper relationships (18 tables)
--   2. Default admin user (username: admin, password: Admin@123)
--   3. Essential system settings only
--   4. Placeholder payment methods (inactive by default)
--   5. NO sample data - ready for production use
-- 
-- IMPORTANT SECURITY NOTES:
--   - Change the default admin password immediately after installation
--   - Update payment method details before activating them
--   - Configure email settings in Admin Panel
--   - Update company information in Settings
-- 
-- HOW TO USE:
-- 1. Create a database in your MySQL server or cPanel
-- 2. Select that database before importing this file
-- 3. For command line: mysql -u username -p database_name < database/production-ready.sql
-- 4. For phpMyAdmin: Select your database, then Import → Choose File → production-ready.sql
-- 5. Access admin panel at: /admin/ (username: admin, password: Admin@123)
-- 6. IMMEDIATELY change admin password after first login!
-- ============================================================================

-- NOTE: Make sure you have selected your database before running this script
-- This script does NOT create a database - you must create/select one first

-- Drop existing tables if they exist (for clean setup)
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS activity_logs;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS booking_payment_methods;
DROP TABLE IF EXISTS payment_methods;
DROP TABLE IF EXISTS booking_services;
DROP TABLE IF EXISTS booking_menus;
DROP TABLE IF EXISTS bookings;
DROP TABLE IF EXISTS customers;
DROP TABLE IF EXISTS hall_menus;
DROP TABLE IF EXISTS menu_items;
DROP TABLE IF EXISTS menus;
DROP TABLE IF EXISTS additional_services;
DROP TABLE IF EXISTS hall_images;
DROP TABLE IF EXISTS halls;
DROP TABLE IF EXISTS venues;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS settings;
DROP TABLE IF EXISTS site_images;
SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- TABLE: venues
-- ============================================================================
CREATE TABLE venues (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    location VARCHAR(255) NOT NULL,
    address TEXT,
    description TEXT,
    image VARCHAR(255),
    contact_phone VARCHAR(20),
    contact_email VARCHAR(100),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- TABLE: halls
-- ============================================================================
CREATE TABLE halls (
    id INT PRIMARY KEY AUTO_INCREMENT,
    venue_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    capacity INT NOT NULL,
    hall_type ENUM('single', 'multiple') DEFAULT 'single',
    indoor_outdoor ENUM('indoor', 'outdoor', 'both') DEFAULT 'indoor',
    base_price DECIMAL(10, 2) NOT NULL,
    description TEXT,
    features TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (venue_id) REFERENCES venues(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- TABLE: hall_images
-- ============================================================================
CREATE TABLE hall_images (
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
CREATE TABLE menus (
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
CREATE TABLE menu_items (
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
CREATE TABLE hall_menus (
    id INT PRIMARY KEY AUTO_INCREMENT,
    hall_id INT NOT NULL,
    menu_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (hall_id) REFERENCES halls(id) ON DELETE CASCADE,
    FOREIGN KEY (menu_id) REFERENCES menus(id) ON DELETE CASCADE,
    UNIQUE KEY unique_hall_menu (hall_id, menu_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- TABLE: additional_services
-- ============================================================================
CREATE TABLE additional_services (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL,
    category VARCHAR(100),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- TABLE: customers
-- ============================================================================
CREATE TABLE customers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    full_name VARCHAR(255) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(100),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_phone (phone),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- TABLE: bookings
-- ============================================================================
CREATE TABLE bookings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    booking_number VARCHAR(50) UNIQUE NOT NULL,
    customer_id INT NOT NULL,
    hall_id INT NOT NULL,
    event_date DATE NOT NULL,
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
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (hall_id) REFERENCES halls(id),
    INDEX idx_event_date (event_date),
    INDEX idx_booking_number (booking_number),
    INDEX idx_status (booking_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- TABLE: booking_menus (link bookings with selected menus)
-- ============================================================================
CREATE TABLE booking_menus (
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
CREATE TABLE booking_services (
    id INT PRIMARY KEY AUTO_INCREMENT,
    booking_id INT NOT NULL,
    service_id INT NOT NULL,
    service_name VARCHAR(255) NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    description TEXT,
    category VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES additional_services(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- TABLE: payment_methods
-- ============================================================================
CREATE TABLE payment_methods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    qr_code VARCHAR(255),
    bank_details TEXT,
    status ENUM('active','inactive') DEFAULT 'inactive',
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_display_order (display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- TABLE: booking_payment_methods (junction table)
-- ============================================================================
CREATE TABLE booking_payment_methods (
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
CREATE TABLE payments (
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
CREATE TABLE users (
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
CREATE TABLE settings (
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
CREATE TABLE activity_logs (
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
-- TABLE: site_images (for dynamic image management)
-- ============================================================================
CREATE TABLE site_images (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    image_path VARCHAR(255) NOT NULL,
    section VARCHAR(100) NOT NULL,
    display_order INT DEFAULT 0,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_section (section),
    INDEX idx_status (status),
    INDEX idx_display_order (display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- INSERT DEFAULT ADMIN USER AND ESSENTIAL SETTINGS
-- ============================================================================

-- Insert default admin user (password: Admin@123)
-- ⚠️ CRITICAL SECURITY WARNING: Change this password IMMEDIATELY after installation!
-- Login at /admin/ with username: admin, password: Admin@123
-- Then update your password in Admin Panel → Profile/Settings
INSERT INTO users (username, password, full_name, email, role, status) 
VALUES ('admin', '$2y$10$5sw.gEWePITwobdChuwoRuRT4dtOnxCFf/RMosnL9JVeEeb3teuna', 'System Administrator', 'admin@example.com', 'admin', 'active');

-- Insert essential system settings
-- These are default values - configure them in Admin Panel → Settings
INSERT INTO settings (setting_key, setting_value, setting_type) VALUES
('site_name', 'Venue Booking System', 'text'),
('site_logo', '', 'text'),
('contact_email', 'info@example.com', 'text'),
('contact_phone', '+977 1234567890', 'text'),
('contact_address', 'Your Address Here', 'text'),
('currency', 'NPR', 'text'),
('tax_rate', '13', 'number'),
('advance_payment_percentage', '30', 'number'),
('company_name', 'Your Company Name', 'text'),
('company_address', 'Your Company Address', 'text'),
('company_phone', '+977 1234567890', 'text'),
('company_email', 'company@example.com', 'text'),
('invoice_title', 'Booking Confirmation & Payment Receipt', 'text'),
('invoice_package_label', 'Event Package', 'text'),
('invoice_additional_items_label', 'Additional Items', 'text'),
('cancellation_policy', 'Advance payment is non-refundable in case of cancellation.
Full payment must be completed 7 days before the event date.
Cancellations made 30 days before the event will receive 50% refund of total amount (excluding advance).
Cancellations made less than 30 days before the event are non-refundable.
Date changes are subject to availability and must be requested at least 15 days in advance.', 'textarea'),
('invoice_disclaimer', 'Note: This is a computer-generated receipt. For any queries, please contact us.', 'text');

-- Insert placeholder payment methods (INACTIVE by default)
-- IMPORTANT: Update these details in Admin Panel → Payment Methods before activating!
INSERT INTO payment_methods (name, bank_details, status, display_order) VALUES
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

Please bring your booking reference number when making payment.', 'inactive', 4);

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
