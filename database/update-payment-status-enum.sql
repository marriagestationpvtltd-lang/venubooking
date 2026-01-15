-- Migration: Update payment status enum to support 'pending' and 'cancelled'
-- This script adds 'pending' and 'cancelled' to the payment_status enum

USE venubooking;

-- Update the bookings table to add new payment status values
-- Step 1: Change the enum to include new values
ALTER TABLE bookings 
MODIFY COLUMN payment_status ENUM('pending', 'unpaid', 'partial', 'paid', 'cancelled') DEFAULT 'pending';

-- Step 2: Update any existing 'unpaid' records to 'pending' for better semantics
UPDATE bookings 
SET payment_status = 'pending' 
WHERE payment_status = 'unpaid';

-- Note: The flow should be: pending → partial → paid
-- cancelled can be set from any status
