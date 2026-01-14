-- Sample Data for Venue Booking System
USE venubooking;

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
('Bijay Kumar', '+977 9861234567', 'bijay.kumar@example.com', 'Bhaktapur, Nepal');

-- Insert Sample Bookings
INSERT INTO bookings (booking_number, customer_id, hall_id, event_date, shift, event_type, number_of_guests, hall_price, menu_total, services_total, subtotal, tax_amount, grand_total, booking_status, payment_status) VALUES
('BK-20260115-0001', 1, 1, '2026-02-15', 'evening', 'Wedding', 500, 150000.00, 1199500.00, 65000.00, 1414500.00, 183885.00, 1598385.00, 'confirmed', 'partial'),
('BK-20260120-0002', 2, 3, '2026-03-20', 'fullday', 'Birthday Party', 200, 180000.00, 299800.00, 45000.00, 524800.00, 68224.00, 593024.00, 'pending', 'unpaid');
