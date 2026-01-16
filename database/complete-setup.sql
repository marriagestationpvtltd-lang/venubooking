-- Complete Database Setup for Venue Booking System
-- This script sets up the entire database from scratch
-- Run this to fix HTTP 500 errors related to missing tables or data

-- Create Database
CREATE DATABASE IF NOT EXISTS venubooking;
USE venubooking;

-- Drop existing tables if they exist (to ensure clean setup)
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS activity_logs;
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

-- Table: venues
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

-- Table: halls
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

-- Table: hall_images
CREATE TABLE hall_images (
    id INT PRIMARY KEY AUTO_INCREMENT,
    hall_id INT NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    is_primary BOOLEAN DEFAULT 0,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (hall_id) REFERENCES halls(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: menus
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

-- Table: menu_items
CREATE TABLE menu_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    menu_id INT NOT NULL,
    item_name VARCHAR(255) NOT NULL,
    category VARCHAR(100),
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (menu_id) REFERENCES menus(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: hall_menus (many-to-many relationship)
CREATE TABLE hall_menus (
    id INT PRIMARY KEY AUTO_INCREMENT,
    hall_id INT NOT NULL,
    menu_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (hall_id) REFERENCES halls(id) ON DELETE CASCADE,
    FOREIGN KEY (menu_id) REFERENCES menus(id) ON DELETE CASCADE,
    UNIQUE KEY unique_hall_menu (hall_id, menu_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: additional_services
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

-- Table: customers
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

-- Table: bookings
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
    booking_status ENUM('pending', 'confirmed', 'cancelled', 'completed') DEFAULT 'pending',
    payment_status ENUM('pending', 'partial', 'paid', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (hall_id) REFERENCES halls(id),
    INDEX idx_event_date (event_date),
    INDEX idx_booking_number (booking_number),
    INDEX idx_status (booking_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: booking_menus (link bookings with selected menus)
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

-- Table: booking_services (link bookings with selected services)
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

-- Table: users (admin users)
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

-- Table: settings
CREATE TABLE settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: activity_logs
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

-- Table: site_images (for dynamic image management)
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

-- Insert default admin user (password: Admin@123)
-- ⚠️ SECURITY WARNING: Change this password immediately after installation!
-- Login at /admin/ with username: admin, password: Admin@123
-- Then update your password in the admin panel settings
INSERT INTO users (username, password, full_name, email, role, status) 
VALUES ('admin', '$2y$10$5sw.gEWePITwobdChuwoRuRT4dtOnxCFf/RMosnL9JVeEeb3teuna', 'System Administrator', 'admin@venubooking.com', 'admin', 'active');

-- Insert default settings
INSERT INTO settings (setting_key, setting_value, setting_type) VALUES
('site_name', 'Venue Booking System', 'text'),
('site_logo', '', 'text'),
('contact_email', 'info@venubooking.com', 'text'),
('contact_phone', '+977 1234567890', 'text'),
('currency', 'NPR', 'text'),
('tax_rate', '13', 'number'),
('advance_payment_percentage', '30', 'number');

-- Insert Venues
INSERT INTO venues (name, location, address, description, image, contact_phone, contact_email) VALUES
('Royal Palace', 'Kathmandu', 'Durbar Marg, Kathmandu', 'Luxury venue in the heart of Kathmandu with traditional architecture and modern amenities.', 'royal-palace.jpg', '+977 1-4234567', 'info@royalpalace.com'),
('Garden View Hall', 'Lalitpur', 'Jawalakhel, Lalitpur', 'Beautiful garden venue perfect for outdoor events with stunning greenery.', 'garden-view.jpg', '+977 1-5234567', 'contact@gardenview.com'),
('City Convention Center', 'Kathmandu', 'Thamel, Kathmandu', 'Modern convention center with state-of-the-art facilities for corporate events.', 'city-convention.jpg', '+977 1-4123456', 'info@cityconvention.com'),
('Lakeside Resort', 'Pokhara', 'Lakeside Road, Pokhara', 'Scenic lakeside venue with breathtaking mountain views.', 'lakeside-resort.jpg', '+977 61-234567', 'booking@lakesideresort.com');

-- Insert Halls
INSERT INTO halls (venue_id, name, capacity, hall_type, indoor_outdoor, base_price, description, features) VALUES
(1, 'Sagarmatha Hall', 700, 'single', 'indoor', 150000.00, 'Our flagship hall with capacity of 700 guests. Features premium amenities and elegant decor.', 'Air conditioning, Stage, Sound system, LED screens'),
(1, 'Everest Hall', 500, 'single', 'indoor', 120000.00, 'Mid-sized hall perfect for intimate gatherings with modern facilities.', 'Air conditioning, Stage, Sound system'),
(2, 'Garden Lawn', 1000, 'single', 'outdoor', 180000.00, 'Expansive outdoor lawn with beautiful garden setting, ideal for large weddings.', 'Garden setting, Gazebo, Outdoor lighting'),
(2, 'Rose Hall', 300, 'single', 'indoor', 80000.00, 'Cozy indoor hall with floral themed decor.', 'Air conditioning, Stage, Projector'),
(3, 'Convention Hall A', 800, 'single', 'indoor', 200000.00, 'Large convention hall with modern audio-visual equipment.', 'Air conditioning, Multiple screens, Conference setup, Wi-Fi'),
(3, 'Convention Hall B', 400, 'single', 'indoor', 100000.00, 'Smaller convention space perfect for corporate meetings and seminars.', 'Air conditioning, Projector, Wi-Fi'),
(4, 'Lakeview Terrace', 600, 'single', 'outdoor', 220000.00, 'Premium outdoor terrace with stunning lake and mountain views.', 'Lake view, Mountain view, Outdoor seating'),
(4, 'Sunset Hall', 350, 'single', 'indoor', 90000.00, 'Indoor hall with large windows offering panoramic sunset views.', 'Air conditioning, Stage, Natural lighting');

-- Insert Hall Images
INSERT INTO hall_images (hall_id, image_path, is_primary, display_order) VALUES
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
INSERT INTO menus (name, description, price_per_person, image) VALUES
('Royal Gold Menu', 'Premium menu featuring the finest selection of dishes with international and local cuisine.', 2399.00, 'royal-gold-menu.jpg'),
('Silver Deluxe Menu', 'Deluxe menu with a perfect blend of traditional and modern dishes.', 1899.00, 'silver-deluxe-menu.jpg'),
('Bronze Classic Menu', 'Classic menu with popular dishes that satisfy all tastes.', 1499.00, 'bronze-classic-menu.jpg'),
('Vegetarian Special', 'Specially curated vegetarian menu with diverse and flavorful options.', 1299.00, 'vegetarian-special-menu.jpg'),
('Premium Platinum', 'Ultimate luxury menu with exotic dishes and premium ingredients.', 2999.00, 'premium-platinum-menu.jpg');

-- Insert Menu Items for Royal Gold Menu
INSERT INTO menu_items (menu_id, item_name, category, display_order) VALUES
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
INSERT INTO menu_items (menu_id, item_name, category, display_order) VALUES
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
INSERT INTO menu_items (menu_id, item_name, category, display_order) VALUES
(3, 'Soft Drinks', 'Beverages', 1),
(3, 'Mixed Salad', 'Appetizers', 2),
(3, 'Chicken Curry', 'Main Course', 3),
(3, 'Vegetable Curry', 'Main Course', 4),
(3, 'Pulao Rice', 'Main Course', 5),
(3, 'Dal', 'Main Course', 6),
(3, 'Roti', 'Breads', 7),
(3, 'Seasonal Fruits', 'Desserts', 8);

-- Insert Menu Items for Vegetarian Special
INSERT INTO menu_items (menu_id, item_name, category, display_order) VALUES
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
INSERT INTO menu_items (menu_id, item_name, category, display_order) VALUES
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
INSERT INTO hall_menus (hall_id, menu_id) VALUES
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
INSERT INTO additional_services (name, description, price, category) VALUES
('Flower Decoration', 'Beautiful floral arrangements throughout the venue', 15000.00, 'Decoration'),
('Stage Decoration', 'Professional stage setup with backdrop and lighting', 25000.00, 'Decoration'),
('Photography Package', 'Professional photography services for the entire event', 30000.00, 'Photography'),
('Videography Package', 'HD video coverage with edited highlights', 40000.00, 'Videography'),
('DJ Service', 'Professional DJ with sound system and lighting', 20000.00, 'Entertainment'),
('Live Band', 'Live music performance by professional band', 50000.00, 'Entertainment'),
('Transportation', 'Guest transportation service with comfortable vehicles', 35000.00, 'Logistics'),
('Valet Parking', 'Professional valet parking service for guests', 10000.00, 'Logistics');

-- Insert Sample Customers
INSERT INTO customers (full_name, phone, email, address) VALUES
('Ramesh Sharma', '+977 9841234567', 'ramesh.sharma@example.com', 'Kathmandu, Nepal'),
('Sita Thapa', '+977 9851234567', 'sita.thapa@example.com', 'Lalitpur, Nepal'),
('Bijay Kumar', '+977 9861234567', 'bijay.kumar@example.com', 'Bhaktapur, Nepal'),
('Anil Gurung', '+977 9871234567', 'anil.gurung@example.com', 'Pokhara, Nepal'),
('Maya Rai', '+977 9881234567', 'maya.rai@example.com', 'Chitwan, Nepal'),
('Prakash Shrestha', '+977 9891234567', 'prakash.shrestha@example.com', 'Bhaktapur, Nepal'),
('Uttam Acharya', '+977 9801234567', 'uttam.acharya@example.com', 'Kathmandu, Nepal');

-- Insert Sample Bookings (Including booking with ID=23)
INSERT INTO bookings (id, booking_number, customer_id, hall_id, event_date, shift, event_type, number_of_guests, hall_price, menu_total, services_total, subtotal, tax_amount, grand_total, special_requests, booking_status, payment_status) VALUES
(1, 'BK-20260115-0001', 1, 1, '2026-02-15', 'evening', 'Wedding', 500, 150000.00, 1199500.00, 65000.00, 1414500.00, 183885.00, 1598385.00, 'Please arrange for vegetarian options separately', 'confirmed', 'partial'),
(2, 'BK-20260120-0002', 2, 3, '2026-03-20', 'fullday', 'Birthday Party', 200, 180000.00, 299800.00, 45000.00, 524800.00, 68224.00, 593024.00, NULL, 'pending', 'pending'),
(23, 'BK-20260125-0023', 7, 4, '2026-04-10', 'evening', 'Wedding Reception', 250, 80000.00, 374750.00, 75000.00, 529750.00, 68867.50, 598617.50, 'Please provide separate dining area for elderly guests', 'confirmed', 'paid');

-- Insert booking menus for booking #1
INSERT INTO booking_menus (booking_id, menu_id, price_per_person, number_of_guests, total_price) VALUES
(1, 1, 2399.00, 500, 1199500.00);

-- Insert booking menus for booking #2
INSERT INTO booking_menus (booking_id, menu_id, price_per_person, number_of_guests, total_price) VALUES
(2, 4, 1499.00, 200, 299800.00);

-- Insert booking menus for booking #23
INSERT INTO booking_menus (booking_id, menu_id, price_per_person, number_of_guests, total_price) VALUES
(23, 2, 1499.00, 250, 374750.00);

-- Insert booking services for booking #1
INSERT INTO booking_services (booking_id, service_id, service_name, price) VALUES
(1, 1, 'Flower Decoration', 15000.00),
(1, 3, 'Photography Package', 30000.00),
(1, 5, 'DJ Service', 20000.00);

-- Insert booking services for booking #2
INSERT INTO booking_services (booking_id, service_id, service_name, price) VALUES
(2, 1, 'Flower Decoration', 15000.00),
(2, 3, 'Photography Package', 30000.00);

-- Insert booking services for booking #23
INSERT INTO booking_services (booking_id, service_id, service_name, price) VALUES
(23, 1, 'Flower Decoration', 15000.00),
(23, 2, 'Stage Decoration', 25000.00),
(23, 3, 'Photography Package', 30000.00),
(23, 8, 'Valet Parking', 10000.00);

-- Verify database setup
SELECT 'Database Setup Complete!' as Status;
SELECT COUNT(*) as total_tables FROM information_schema.tables WHERE table_schema = 'venubooking';
SELECT COUNT(*) as total_venues FROM venues;
SELECT COUNT(*) as total_halls FROM halls;
SELECT COUNT(*) as total_menus FROM menus;
SELECT COUNT(*) as total_customers FROM customers;
SELECT COUNT(*) as total_bookings FROM bookings;
SELECT id, booking_number, event_type, booking_status FROM bookings WHERE id = 23;
