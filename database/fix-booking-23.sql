-- Fix for HTTP 500 Error - Add Missing Booking #23
-- This script adds the missing booking record with ID=23
-- Run this if you already have the database setup but are missing booking ID=23

USE venubooking;

-- Check if booking #23 already exists
SELECT 'Checking for existing booking #23...' as Status;
SELECT COUNT(*) as existing_count FROM bookings WHERE id = 23;

-- Add customer if not exists
INSERT IGNORE INTO customers (id, full_name, phone, email, address) VALUES
(7, 'Uttam Acharya', '+977 9801234567', 'uttam.acharya@example.com', 'Kathmandu, Nepal');

-- Add booking #23 if it doesn't exist
INSERT IGNORE INTO bookings (id, booking_number, customer_id, hall_id, event_date, shift, event_type, number_of_guests, hall_price, menu_total, services_total, subtotal, tax_amount, grand_total, special_requests, booking_status, payment_status) VALUES
(23, 'BK-20260125-0023', 7, 4, '2026-04-10', 'evening', 'Wedding Reception', 250, 80000.00, 374750.00, 75000.00, 529750.00, 68867.50, 598617.50, 'Please provide separate dining area for elderly guests', 'confirmed', 'paid');

-- Add booking menus for booking #23
INSERT IGNORE INTO booking_menus (booking_id, menu_id, price_per_person, number_of_guests, total_price) VALUES
(23, 2, 1499.00, 250, 374750.00);

-- Add booking services for booking #23
INSERT IGNORE INTO booking_services (booking_id, service_id, service_name, price) VALUES
(23, 1, 'Flower Decoration', 15000.00),
(23, 2, 'Stage Decoration', 25000.00),
(23, 3, 'Photography Package', 30000.00),
(23, 8, 'Valet Parking', 10000.00);

-- Verify the fix
SELECT 'Fix Applied Successfully!' as Status;
SELECT id, booking_number, event_type, booking_status, payment_status, grand_total FROM bookings WHERE id = 23;
