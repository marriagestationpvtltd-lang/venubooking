-- Add advance_payment_received field to bookings table
-- This allows admin to mark whether advance payment has been received

ALTER TABLE bookings 
ADD COLUMN advance_payment_received TINYINT(1) DEFAULT 0 
COMMENT 'Whether advance payment has been received (0=No, 1=Yes)' 
AFTER payment_status;

-- Add index for advance_payment_received for faster queries
ALTER TABLE bookings 
ADD INDEX idx_advance_payment_received (advance_payment_received);
