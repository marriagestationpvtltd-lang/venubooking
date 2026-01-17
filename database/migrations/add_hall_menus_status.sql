-- Migration: Add status column to hall_menus table
-- This allows active/inactive status for hall-menu assignments
-- Date: 2026-01-17

-- Add status column to hall_menus table
-- The DEFAULT 'active' ensures all existing rows automatically get 'active' status
ALTER TABLE hall_menus 
ADD COLUMN status ENUM('active', 'inactive') DEFAULT 'active' 
AFTER menu_id;
