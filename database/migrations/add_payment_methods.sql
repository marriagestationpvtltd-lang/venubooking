-- Add Payment Methods System
-- This migration adds payment methods management to the booking system

-- Create payment_methods table
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

-- Create booking_payment_methods junction table to support multiple payment methods per booking
CREATE TABLE IF NOT EXISTS booking_payment_methods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    payment_method_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (payment_method_id) REFERENCES payment_methods(id) ON DELETE CASCADE,
    UNIQUE KEY unique_booking_payment_method (booking_id, payment_method_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default payment methods (can be customized later by admin)
-- NOTE: Replace placeholder values [in brackets] with actual information before production use
-- These are sample entries to demonstrate the structure
INSERT INTO payment_methods (name, bank_details, status, display_order) VALUES
('Bank Transfer', 'Bank: [Your Bank Name]\nAccount Name: [Account Holder Name]\nAccount Number: [Account Number]\nBranch: [Branch Name]\n\nNote: Please replace these placeholders with actual bank details', 'inactive', 1),
('eSewa', 'eSewa ID: [Your eSewa ID]\neSewa Number: [Your eSewa Number]\n\nNote: Please replace these placeholders with actual eSewa details', 'inactive', 2),
('Khalti', 'Khalti ID: [Your Khalti ID]\nKhalti Number: [Your Khalti Number]\n\nNote: Please replace these placeholders with actual Khalti details', 'inactive', 3),
('Cash Payment', 'Cash payment can be made at our office during business hours.\nPlease bring your booking reference number.', 'active', 4);
