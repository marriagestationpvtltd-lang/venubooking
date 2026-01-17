-- Migration: Add status column to hall_menus table
-- This allows active/inactive status for hall-menu assignments
-- Date: 2026-01-17

-- Add status column to hall_menus table
ALTER TABLE hall_menus 
ADD COLUMN status ENUM('active', 'inactive') DEFAULT 'active' 
AFTER menu_id;

-- Update getMenusForHall query to filter by hall_menus.status as well
-- Note: This is handled in the application code (includes/functions.php)

-- For backward compatibility, set all existing assignments to 'active' (if column somehow isn't set)
-- Note: Due to DEFAULT 'active', all existing rows will already have status = 'active'
-- This is kept for documentation purposes only
