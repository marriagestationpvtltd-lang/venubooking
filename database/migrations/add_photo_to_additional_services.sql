-- ============================================================================
-- Migration: Add photo column to additional_services
-- Description: Allows each service to have an optional photo image that is
--              displayed in the admin listing, service detail view, and on the
--              booking step-4 service selection page.
-- ============================================================================

SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'additional_services'
      AND COLUMN_NAME  = 'photo'
);
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE additional_services ADD COLUMN photo VARCHAR(255) DEFAULT NULL COMMENT ''Optional service photo filename in uploads/ directory'' AFTER category',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
