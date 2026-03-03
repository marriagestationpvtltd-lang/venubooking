-- Migration: Add city and loyalty_points columns to customers table
-- Instead of creating a new table, these columns extend the existing customers table.
--
-- city         VARCHAR(100) NULL    -- Optional city for the customer
-- loyalty_points INT DEFAULT 0      -- Accumulated loyalty points for the customer
--
-- Run this migration to enable city tracking and loyalty points for customers.
-- NOTE: Make sure you have selected your database before running this script.

-- ============================================================================
-- UP
-- ============================================================================

ALTER TABLE customers
    ADD COLUMN city VARCHAR(100) NULL AFTER address,
    ADD COLUMN loyalty_points INT NOT NULL DEFAULT 0 AFTER city;

-- Add index on city for faster filtering/grouping by city
ALTER TABLE customers ADD INDEX idx_city (city);

-- ============================================================================
-- DOWN
-- ============================================================================
-- To rollback this migration, run the following SQL:
--
-- ALTER TABLE customers DROP INDEX idx_city;
-- ALTER TABLE customers DROP COLUMN loyalty_points;
-- ALTER TABLE customers DROP COLUMN city;
