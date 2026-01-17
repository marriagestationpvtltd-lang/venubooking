-- Migration: Add support for admin-added services
-- This allows admins to add additional services directly from the booking page
-- These services are separate from user-selected services during booking

-- NOTE: Make sure you have selected your database before running this script

-- Add columns to booking_services table
ALTER TABLE booking_services 
ADD COLUMN added_by ENUM('user', 'admin') DEFAULT 'user' AFTER category,
ADD COLUMN quantity INT DEFAULT 1 AFTER added_by;

-- Create index for better query performance when filtering by added_by
CREATE INDEX idx_booking_services_added_by ON booking_services(added_by);

-- Update existing records to mark them as user-added services
UPDATE booking_services 
SET added_by = 'user', quantity = 1 
WHERE added_by IS NULL;

-- Verification query
SELECT 
    'Migration Complete' as Status,
    COUNT(*) as total_services,
    SUM(CASE WHEN added_by = 'user' THEN 1 ELSE 0 END) as user_services,
    SUM(CASE WHEN added_by = 'admin' THEN 1 ELSE 0 END) as admin_services
FROM booking_services;

-- ====================================
-- ROLLBACK INSTRUCTIONS
-- ====================================
-- To rollback this migration, run the following SQL:
-- 
-- DROP INDEX idx_booking_services_added_by ON booking_services;
-- ALTER TABLE booking_services DROP COLUMN quantity;
-- ALTER TABLE booking_services DROP COLUMN added_by;
-- 
-- Note: This will permanently delete all admin-added services data.
-- Make a backup before rollback if you need to preserve the data.
