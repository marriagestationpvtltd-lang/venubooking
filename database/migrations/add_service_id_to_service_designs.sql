-- ============================================================================
-- Migration: Add service_id to service_designs for direct design flow
-- Description: Allows designs to be attached directly to services without
--              requiring a sub-service layer.  Existing sub-service-based
--              designs are unaffected (sub_service_id remains).
-- ============================================================================

-- Add service_id column to service_designs (nullable, with FK to additional_services)
SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'service_designs'
      AND COLUMN_NAME  = 'service_id'
);
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE service_designs ADD COLUMN service_id INT DEFAULT NULL COMMENT ''Direct parent service (additional_services.id) — alternative to sub_service_id'' AFTER sub_service_id, ADD CONSTRAINT fk_service_designs_service FOREIGN KEY (service_id) REFERENCES additional_services(id) ON DELETE CASCADE',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Allow sub_service_id to be NULL (direct-service designs have no sub-service).
-- Drop existing FK first, modify the column, then recreate the FK.
SET @nullable_check = (
    SELECT IS_NULLABLE FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'service_designs'
      AND COLUMN_NAME  = 'sub_service_id'
);

-- Drop the FK constraint on sub_service_id if the column is currently NOT NULL
SET @fk_name = (
    SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'service_designs'
      AND COLUMN_NAME  = 'sub_service_id'
      AND REFERENCED_TABLE_NAME = 'service_sub_services'
    LIMIT 1
);

SET @drop_fk = IF(@nullable_check = 'NO' AND @fk_name IS NOT NULL,
    CONCAT('ALTER TABLE service_designs DROP FOREIGN KEY ', @fk_name),
    'SELECT 1'
);
PREPARE stmt2 FROM @drop_fk; EXECUTE stmt2; DEALLOCATE PREPARE stmt2;

-- Make sub_service_id nullable
SET @sql3 = IF(@nullable_check = 'NO',
    'ALTER TABLE service_designs MODIFY COLUMN sub_service_id INT DEFAULT NULL COMMENT ''References service_sub_services.id — NULL for direct-service designs''',
    'SELECT 1'
);
PREPARE stmt3 FROM @sql3; EXECUTE stmt3; DEALLOCATE PREPARE stmt3;

-- Re-add the FK constraint for sub_service_id (nullable, cascade on delete)
SET @fk_exists = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA      = DATABASE()
      AND TABLE_NAME        = 'service_designs'
      AND CONSTRAINT_TYPE   = 'FOREIGN KEY'
      AND CONSTRAINT_NAME   = 'fk_service_designs_sub_service'
);
SET @readd_fk = IF(@nullable_check = 'NO' AND @fk_exists = 0,
    'ALTER TABLE service_designs ADD CONSTRAINT fk_service_designs_sub_service FOREIGN KEY (sub_service_id) REFERENCES service_sub_services(id) ON DELETE CASCADE',
    'SELECT 1'
);
PREPARE stmt4 FROM @readd_fk; EXECUTE stmt4; DEALLOCATE PREPARE stmt4;
