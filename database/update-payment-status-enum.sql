-- Migration: Update payment status enum to support 'pending' and 'cancelled'
-- This script updates the payment_status enum structure

USE venubooking;

-- Step 1: Update any existing 'unpaid' records to 'pending' first
UPDATE bookings 
SET payment_status = 'pending' 
WHERE payment_status = 'unpaid';

-- Step 2: Change the enum to include new values (without unpaid)
ALTER TABLE bookings 
MODIFY COLUMN payment_status ENUM('pending', 'partial', 'paid', 'cancelled') DEFAULT 'pending';

-- Note: The flow should be: pending → partial → paid
-- cancelled can be set from any status
