-- Migration: Add advance_amount_received column to bookings table
-- This stores the ACTUAL advance payment amount received from the customer,
-- as manually entered by the admin. Previously only a boolean flag
-- (advance_payment_received) existed with no way to track the real amount.

ALTER TABLE bookings
    ADD COLUMN IF NOT EXISTS advance_amount_received DECIMAL(10,2) NOT NULL DEFAULT 0
        COMMENT 'Actual advance payment amount received from customer (manually entered by admin)'
    AFTER advance_payment_received;
