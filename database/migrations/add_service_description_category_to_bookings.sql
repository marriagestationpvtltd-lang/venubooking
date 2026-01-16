-- Migration: Add description and category to booking_services table
-- This ensures complete historical data preservation for booked services
-- Even if a service is deleted from the master table, its full details remain visible

-- NOTE: Make sure you have selected your database before running this script

-- Add description and category columns to booking_services table
ALTER TABLE booking_services 
ADD COLUMN description TEXT AFTER price,
ADD COLUMN category VARCHAR(100) AFTER description;

-- Update existing records to populate description and category from master table
-- This is a one-time update for existing data
UPDATE booking_services bs
INNER JOIN additional_services s ON bs.service_id = s.id
SET bs.description = s.description,
    bs.category = s.category
WHERE bs.description IS NULL;

-- Verification query
SELECT 
    'Migration Complete' as Status,
    COUNT(*) as total_records,
    SUM(CASE WHEN description IS NOT NULL THEN 1 ELSE 0 END) as with_description,
    SUM(CASE WHEN category IS NOT NULL THEN 1 ELSE 0 END) as with_category
FROM booking_services;
