-- Add Booking Payment Confirmation Feature
-- This migration adds payment tracking and confirmation options to the booking system

-- Create payment_methods table if not exists
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

-- Create booking_payment_methods junction table if not exists
CREATE TABLE IF NOT EXISTS booking_payment_methods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    payment_method_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (payment_method_id) REFERENCES payment_methods(id) ON DELETE CASCADE,
    UNIQUE KEY unique_booking_payment_method (booking_id, payment_method_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create payments table to track payment transactions
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

-- Update booking_status enum to include new status for payment submitted
ALTER TABLE bookings MODIFY COLUMN booking_status 
    ENUM('pending', 'confirmed', 'payment_submitted', 'cancelled', 'completed') 
    DEFAULT 'pending';

-- Add advance_payment_percentage setting if not exists
INSERT INTO settings (setting_key, setting_value, setting_type)
SELECT 'advance_payment_percentage', '25', 'number'
WHERE NOT EXISTS (
    SELECT 1 FROM settings WHERE setting_key = 'advance_payment_percentage'
);

-- Insert default payment methods if table is empty
INSERT INTO payment_methods (name, bank_details, status, display_order)
SELECT * FROM (
    SELECT 'Bank Transfer' as name, 
           'Bank: [Your Bank Name]\nAccount Name: [Account Holder Name]\nAccount Number: [Account Number]\nBranch: [Branch Name]\n\nNote: Please update these details in Admin > Payment Methods' as bank_details, 
           'inactive' as status, 
           1 as display_order
    UNION ALL
    SELECT 'eSewa', 
           'eSewa ID: [Your eSewa ID]\neSewa Number: [Your eSewa Number]\n\nNote: Please update these details in Admin > Payment Methods', 
           'inactive', 
           2
    UNION ALL
    SELECT 'Khalti', 
           'Khalti ID: [Your Khalti ID]\nKhalti Number: [Your Khalti Number]\n\nNote: Please update these details in Admin > Payment Methods', 
           'inactive', 
           3
    UNION ALL
    SELECT 'Cash Payment', 
           'Cash payment can be made at our office during business hours.\nPlease bring your booking reference number.', 
           'active', 
           4
) AS tmp
WHERE NOT EXISTS (SELECT 1 FROM payment_methods LIMIT 1);
