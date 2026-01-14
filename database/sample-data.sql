-- ============================================
-- Venue Booking System - Sample Data
-- ============================================

USE `venubooking`;

-- ============================================
-- Insert Venues
-- ============================================
INSERT INTO `venues` (`venue_name`, `location`, `address`, `description`, `image`, `status`) VALUES
('Royal Palace', 'Kathmandu', 'Durbar Marg, Kathmandu 44600', 'Luxury venue with traditional architecture and modern amenities. Perfect for grand celebrations and corporate events.', 'royal-palace.jpg', 'active'),
('Garden View Hall', 'Lalitpur', 'Pulchowk, Lalitpur 44700', 'Beautiful garden venue with indoor and outdoor spaces. Ideal for weddings and outdoor celebrations.', 'garden-view.jpg', 'active'),
('City Convention Center', 'Kathmandu', 'New Baneshwor, Kathmandu 44600', 'Modern convention center with state-of-the-art facilities. Best for large corporate events and conferences.', 'convention-center.jpg', 'active'),
('Lakeside Resort', 'Pokhara', 'Lakeside Road, Pokhara 33700', 'Scenic resort venue with stunning lake views. Perfect for destination weddings and retreats.', 'lakeside-resort.jpg', 'active');

-- ============================================
-- Insert Halls
-- ============================================
INSERT INTO `halls` (`venue_id`, `hall_name`, `capacity`, `hall_type`, `indoor_outdoor`, `base_price`, `description`, `amenities`, `status`) VALUES
(1, 'Sagarmatha Hall', 700, 'single', 'indoor', 150000.00, 'Our largest indoor hall with elegant decor and modern lighting. Perfect for grand weddings and large gatherings.', '["Air Conditioning", "Stage", "Sound System", "LED Screen", "Wi-Fi", "Parking"]', 'active'),
(1, 'Everest Hall', 500, 'single', 'indoor', 120000.00, 'Mid-sized elegant hall with premium finishes. Suitable for weddings and corporate events.', '["Air Conditioning", "Sound System", "Wi-Fi", "Parking", "VIP Lounge"]', 'active'),
(2, 'Garden Lawn', 1000, 'single', 'outdoor', 180000.00, 'Spacious outdoor lawn surrounded by beautiful gardens. Ideal for outdoor weddings and large celebrations.', '["Garden Setting", "Stage", "Tent Option", "Lighting", "Parking", "Catering Area"]', 'active'),
(2, 'Rose Hall', 300, 'single', 'indoor', 80000.00, 'Intimate indoor hall with romantic ambiance. Perfect for small gatherings and intimate celebrations.', '["Air Conditioning", "Sound System", "Decorative Lighting", "Wi-Fi"]', 'active'),
(3, 'Convention Hall A', 800, 'single', 'indoor', 200000.00, 'Large convention hall with modern facilities. Best for conferences and corporate events.', '["Air Conditioning", "Projector", "Sound System", "Stage", "Wi-Fi", "Business Center", "Parking"]', 'active'),
(3, 'Convention Hall B', 400, 'single', 'indoor', 100000.00, 'Medium-sized hall with professional setup. Great for meetings and medium-scale events.', '["Air Conditioning", "Projector", "Sound System", "Wi-Fi", "Whiteboard"]', 'active'),
(4, 'Lakeview Terrace', 600, 'single', 'outdoor', 220000.00, 'Premium outdoor terrace with panoramic lake views. Perfect for destination weddings.', '["Lake View", "Stage", "Lighting", "Tent Option", "Parking", "Photo Spots"]', 'active'),
(4, 'Sunset Hall', 350, 'single', 'indoor', 90000.00, 'Elegant indoor hall with lake views. Suitable for intimate weddings and events.', '["Air Conditioning", "Lake View", "Sound System", "Wi-Fi", "Parking"]', 'active');

-- ============================================
-- Insert Menus
-- ============================================
INSERT INTO `menus` (`menu_name`, `price_per_person`, `description`, `category`, `image`, `status`) VALUES
('Royal Gold Menu', 2399.00, 'Premium menu with 11 delicious items including special appetizers, main courses, and desserts.', 'Premium', 'royal-gold.jpg', 'active'),
('Silver Deluxe Menu', 1899.00, 'Deluxe menu with 9 carefully selected items for a perfect dining experience.', 'Deluxe', 'silver-deluxe.jpg', 'active'),
('Bronze Classic Menu', 1499.00, 'Classic menu with 8 popular items suitable for all occasions.', 'Standard', 'bronze-classic.jpg', 'active'),
('Vegetarian Special', 1299.00, 'Exclusively vegetarian menu with 8 items, perfect for vegetarian celebrations.', 'Vegetarian', 'vegetarian-special.jpg', 'active'),
('Premium Platinum', 2999.00, 'Our finest menu with 12 gourmet items for the most special occasions.', 'Luxury', 'premium-platinum.jpg', 'active');

-- ============================================
-- Insert Menu Items for Royal Gold Menu
-- ============================================
INSERT INTO `menu_items` (`menu_id`, `item_name`, `category`, `description`, `display_order`) VALUES
(1, 'Welcome Drinks', 'Beverages', 'Fresh juice and soft drinks', 1),
(1, 'Chicken Tikka', 'Appetizers', 'Marinated grilled chicken', 2),
(1, 'Paneer Pakoda', 'Appetizers', 'Fried cottage cheese fritters', 3),
(1, 'Chicken Biryani', 'Main Course', 'Aromatic rice with chicken', 4),
(1, 'Mutton Curry', 'Main Course', 'Spicy mutton gravy', 5),
(1, 'Dal Makhani', 'Main Course', 'Creamy black lentils', 6),
(1, 'Mix Vegetable', 'Main Course', 'Seasonal vegetables', 7),
(1, 'Roti/Naan', 'Bread', 'Indian breads', 8),
(1, 'Raita', 'Sides', 'Yogurt side dish', 9),
(1, 'Salad', 'Sides', 'Fresh green salad', 10),
(1, 'Gulab Jamun', 'Desserts', 'Sweet dumplings in syrup', 11);

-- ============================================
-- Insert Menu Items for Silver Deluxe Menu
-- ============================================
INSERT INTO `menu_items` (`menu_id`, `item_name`, `category`, `description`, `display_order`) VALUES
(2, 'Welcome Drinks', 'Beverages', 'Soft drinks', 1),
(2, 'Chicken Chili', 'Appetizers', 'Spicy chicken appetizer', 2),
(2, 'Chicken Pulao', 'Main Course', 'Flavored rice with chicken', 3),
(2, 'Chicken Curry', 'Main Course', 'Traditional chicken curry', 4),
(2, 'Dal Fry', 'Main Course', 'Tempered yellow lentils', 5),
(2, 'Mix Vegetable', 'Main Course', 'Seasonal vegetables', 6),
(2, 'Roti', 'Bread', 'Wheat bread', 7),
(2, 'Raita', 'Sides', 'Yogurt side dish', 8),
(2, 'Ice Cream', 'Desserts', 'Vanilla ice cream', 9);

-- ============================================
-- Insert Menu Items for Bronze Classic Menu
-- ============================================
INSERT INTO `menu_items` (`menu_id`, `item_name`, `category`, `description`, `display_order`) VALUES
(3, 'Soft Drinks', 'Beverages', 'Assorted soft drinks', 1),
(3, 'Vegetable Pakoda', 'Appetizers', 'Fried vegetable fritters', 2),
(3, 'Chicken Fried Rice', 'Main Course', 'Stir-fried rice with chicken', 3),
(3, 'Chicken Curry', 'Main Course', 'Chicken in curry sauce', 4),
(3, 'Dal', 'Main Course', 'Yellow lentils', 5),
(3, 'Vegetable Curry', 'Main Course', 'Mixed vegetables', 6),
(3, 'Roti', 'Bread', 'Wheat bread', 7),
(3, 'Pickle', 'Sides', 'Spicy pickle', 8);

-- ============================================
-- Insert Menu Items for Vegetarian Special
-- ============================================
INSERT INTO `menu_items` (`menu_id`, `item_name`, `category`, `description`, `display_order`) VALUES
(4, 'Fresh Juice', 'Beverages', 'Seasonal fresh juice', 1),
(4, 'Paneer Tikka', 'Appetizers', 'Grilled cottage cheese', 2),
(4, 'Vegetable Biryani', 'Main Course', 'Aromatic rice with vegetables', 3),
(4, 'Paneer Butter Masala', 'Main Course', 'Cottage cheese in butter gravy', 4),
(4, 'Dal Makhani', 'Main Course', 'Creamy black lentils', 5),
(4, 'Mix Vegetable', 'Main Course', 'Seasonal vegetables', 6),
(4, 'Naan', 'Bread', 'Indian flatbread', 7),
(4, 'Kheer', 'Desserts', 'Rice pudding', 8);

-- ============================================
-- Insert Menu Items for Premium Platinum
-- ============================================
INSERT INTO `menu_items` (`menu_id`, `item_name`, `category`, `description`, `display_order`) VALUES
(5, 'Premium Drinks', 'Beverages', 'Fresh juice and premium beverages', 1),
(5, 'Seafood Platter', 'Appetizers', 'Assorted seafood appetizers', 2),
(5, 'Chicken Tandoori', 'Appetizers', 'Tandoor-grilled chicken', 3),
(5, 'Mutton Biryani', 'Main Course', 'Aromatic rice with mutton', 4),
(5, 'Butter Chicken', 'Main Course', 'Chicken in butter gravy', 5),
(5, 'Fish Curry', 'Main Course', 'Fish in special curry', 6),
(5, 'Paneer Makhani', 'Main Course', 'Cottage cheese in gravy', 7),
(5, 'Dal Makhani', 'Main Course', 'Premium black lentils', 8),
(5, 'Assorted Naan', 'Bread', 'Variety of naan breads', 9),
(5, 'Raita & Salad', 'Sides', 'Fresh accompaniments', 10),
(5, 'Premium Desserts', 'Desserts', 'Assorted premium desserts', 11),
(5, 'Coffee/Tea', 'Beverages', 'Hot beverages', 12);

-- ============================================
-- Link Menus to Halls (hall_menus)
-- ============================================
INSERT INTO `hall_menus` (`hall_id`, `menu_id`) VALUES
-- Sagarmatha Hall - all menus available
(1, 1), (1, 2), (1, 3), (1, 4), (1, 5),
-- Everest Hall - all menus available
(2, 1), (2, 2), (2, 3), (2, 4), (2, 5),
-- Garden Lawn - all menus available
(3, 1), (3, 2), (3, 3), (3, 4), (3, 5),
-- Rose Hall - standard menus
(4, 2), (4, 3), (4, 4),
-- Convention Hall A - all menus available
(5, 1), (5, 2), (5, 3), (5, 4), (5, 5),
-- Convention Hall B - standard menus
(6, 2), (6, 3), (6, 4),
-- Lakeview Terrace - premium menus
(7, 1), (7, 2), (7, 5),
-- Sunset Hall - standard and deluxe menus
(8, 2), (8, 3), (8, 4);

-- ============================================
-- Insert Additional Services
-- ============================================
INSERT INTO `additional_services` (`service_name`, `service_type`, `price`, `description`, `status`) VALUES
('Flower Decoration', 'Decoration', 15000.00, 'Beautiful flower arrangements for stage and venue', 'active'),
('Stage Decoration', 'Decoration', 25000.00, 'Complete stage setup with backdrop and props', 'active'),
('Photography Package', 'Photography', 30000.00, 'Professional photography with 2 photographers for full event coverage', 'active'),
('Videography Package', 'Videography', 40000.00, 'HD video coverage with drone shots and edited highlight video', 'active'),
('DJ Service', 'Entertainment', 20000.00, 'Professional DJ with sound system and lighting', 'active'),
('Live Band', 'Entertainment', 50000.00, 'Live music band performance for 3-4 hours', 'active'),
('Transportation', 'Logistics', 35000.00, 'Guest transportation with luxury buses', 'active'),
('Valet Parking', 'Logistics', 10000.00, 'Professional valet parking service', 'active');

-- ============================================
-- Insert Admin User
-- Password: Admin@123 (hashed using bcrypt)
-- ============================================
INSERT INTO `users` (`username`, `password`, `full_name`, `email`, `role`, `status`) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin@venubooking.com', 'admin', 'active');

-- ============================================
-- Insert Settings
-- ============================================
INSERT INTO `settings` (`setting_key`, `setting_value`, `setting_type`) VALUES
('site_name', 'Venue Booking System', 'text'),
('site_email', 'info@venubooking.com', 'text'),
('site_phone', '+977-1-4123456', 'text'),
('site_address', 'Kathmandu, Nepal', 'text'),
('currency', 'NPR', 'text'),
('currency_symbol', 'Rs. ', 'text'),
('tax_rate', '13', 'number'),
('advance_payment_percentage', '30', 'number'),
('timezone', 'Asia/Kathmandu', 'text'),
('date_format', 'Y-m-d', 'text'),
('time_format', 'H:i:s', 'text'),
('booking_buffer_days', '1', 'number'),
('max_advance_booking_days', '365', 'number'),
('smtp_host', 'smtp.gmail.com', 'text'),
('smtp_port', '587', 'text'),
('smtp_username', '', 'text'),
('smtp_password', '', 'text'),
('smtp_encryption', 'tls', 'text'),
('email_from_address', 'noreply@venubooking.com', 'text'),
('email_from_name', 'Venue Booking System', 'text');

-- ============================================
-- Insert Sample Customers
-- ============================================
INSERT INTO `customers` (`full_name`, `phone`, `email`, `address`) VALUES
('Rajesh Kumar Sharma', '9841234567', 'rajesh.sharma@email.com', 'Baneshwor, Kathmandu'),
('Sita Devi Shrestha', '9851234567', 'sita.shrestha@email.com', 'Lalitpur, Nepal'),
('Amit Prasad Adhikari', '9861234567', 'amit.adhikari@email.com', 'Bhaktapur, Nepal'),
('Priya Kumari Thapa', '9871234567', 'priya.thapa@email.com', 'Pokhara, Nepal'),
('Mohan Bahadur KC', '9881234567', 'mohan.kc@email.com', 'Kathmandu, Nepal');

-- ============================================
-- Insert Sample Bookings
-- ============================================
INSERT INTO `bookings` (`booking_number`, `customer_id`, `venue_id`, `hall_id`, `booking_date`, `shift`, `number_of_guests`, `event_type`, `subtotal`, `tax_amount`, `total_cost`, `advance_payment`, `payment_status`, `booking_status`, `special_requests`) VALUES
('BK-20260110-0001', 1, 1, 1, '2026-02-14', 'evening', 600, 'Wedding', 1589400.00, 206622.00, 1796022.00, 538806.60, 'partial', 'confirmed', 'Need extra chairs for elderly guests'),
('BK-20260111-0002', 2, 2, 3, '2026-03-20', 'full_day', 800, 'Wedding', 2099200.00, 272796.00, 2371996.00, 711598.80, 'partial', 'confirmed', 'Vegetarian menu preferred'),
('BK-20260112-0003', 3, 3, 5, '2026-02-28', 'morning', 400, 'Corporate Event', 959600.00, 124748.00, 1084348.00, 325304.40, 'partial', 'confirmed', 'Need projector and sound system'),
('BK-20260113-0004', 4, 4, 7, '2026-04-15', 'evening', 500, 'Anniversary', 1419500.00, 184535.00, 1604035.00, 481210.50, 'pending', 'pending', 'Lake view seating preferred'),
('BK-20260108-0005', 5, 1, 2, '2026-01-25', 'afternoon', 350, 'Birthday Party', 784650.00, 102004.50, 886654.50, 265996.35, 'partial', 'confirmed', NULL),
('BK-20260105-0006', 1, 2, 4, '2025-12-20', 'evening', 250, 'Wedding', 454750.00, 59117.50, 513867.50, 0.00, 'paid', 'completed', 'Small intimate wedding'),
('BK-20260107-0007', 3, 3, 6, '2026-01-30', 'full_day', 300, 'Corporate Event', 669700.00, 87061.00, 756761.00, 227028.30, 'partial', 'confirmed', 'Need Wi-Fi and whiteboards'),
('BK-20260109-0008', 2, 4, 8, '2026-03-05', 'morning', 200, 'Birthday Party', 349800.00, 45474.00, 395274.00, 118582.20, 'partial', 'confirmed', 'Kids party, need entertainment'),
('BK-20260106-0009', 4, 1, 1, '2025-12-15', 'evening', 650, 'Corporate Event', 1484350.00, 192965.50, 1677315.50, 0.00, 'paid', 'completed', 'Annual company dinner'),
('BK-20260114-0010', 5, 2, 3, '2026-05-10', 'full_day', 900, 'Wedding', 2339100.00, 304083.00, 2643183.00, 0.00, 'pending', 'pending', 'Garden wedding ceremony');

-- ============================================
-- Insert Booking Menus (sample for first few bookings)
-- ============================================
INSERT INTO `booking_menus` (`booking_id`, `menu_id`, `quantity`, `price_per_person`, `total_price`, `is_customized`, `customization_details`) VALUES
(1, 1, 600, 2399.00, 1439400.00, 0, NULL),
(2, 5, 800, 2999.00, 2399200.00, 0, NULL),
(3, 2, 400, 1899.00, 759600.00, 0, NULL),
(4, 1, 500, 2399.00, 1199500.00, 0, NULL),
(5, 3, 350, 1499.00, 524650.00, 0, NULL);

-- ============================================
-- Insert Booking Services (sample for first few bookings)
-- ============================================
INSERT INTO `booking_services` (`booking_id`, `service_id`, `quantity`, `price`, `total_price`) VALUES
(1, 2, 1, 25000.00, 25000.00),
(1, 3, 1, 30000.00, 30000.00),
(1, 4, 1, 40000.00, 40000.00),
(2, 1, 1, 15000.00, 15000.00),
(2, 2, 1, 25000.00, 25000.00),
(2, 3, 1, 30000.00, 30000.00),
(3, 3, 1, 30000.00, 30000.00),
(3, 8, 1, 10000.00, 10000.00),
(4, 1, 1, 15000.00, 15000.00),
(4, 5, 1, 20000.00, 20000.00),
(5, 1, 1, 15000.00, 15000.00),
(5, 5, 1, 20000.00, 20000.00);

COMMIT;
