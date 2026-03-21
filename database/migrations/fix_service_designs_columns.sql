-- ============================================================================
-- Migration: Fix service_designs table for direct-service design flow
-- Description: Adds the service_id column to service_designs and makes
--              sub_service_id nullable so that designs can be linked directly
--              to an additional_service without requiring a sub-service.
--
--              Without service_id, getServiceDesigns() in includes/functions.php
--              throws a PDOException that prevents booking-step4.php from
--              loading, blocking all booking submissions.
--
-- Safe to run multiple times (idempotent).
-- Run on any installation set up before the direct-design feature was added.
-- ============================================================================

-- NOTE: Select your database before running: USE your_db_name;

DELIMITER $$

CREATE PROCEDURE fix_service_designs_columns()
BEGIN
    -- Add service_id column if missing.
    -- This column allows a design to be linked directly to an
    -- additional_service (the new flow) instead of via a sub-service.
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'service_designs'
          AND column_name = 'service_id'
    ) THEN
        ALTER TABLE service_designs
            ADD COLUMN service_id INT DEFAULT NULL
                COMMENT 'References additional_services.id (direct service design flow)'
            AFTER sub_service_id,
            ADD CONSTRAINT fk_service_designs_service
                FOREIGN KEY (service_id) REFERENCES additional_services(id) ON DELETE CASCADE;
    END IF;

    -- Make sub_service_id nullable.
    -- Older installations may have created this column as NOT NULL.
    -- Direct-service designs (service_id IS NOT NULL) have no sub-service,
    -- so sub_service_id must be allowed to be NULL.
    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'service_designs'
          AND column_name = 'sub_service_id'
          AND is_nullable = 'NO'
    ) THEN
        -- Drop the existing FK constraint before modifying the column
        SET @fk_sd_ss = NULL;
        SELECT CONSTRAINT_NAME INTO @fk_sd_ss
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'service_designs'
          AND COLUMN_NAME = 'sub_service_id'
          AND REFERENCED_TABLE_NAME = 'service_sub_services'
        LIMIT 1;

        IF @fk_sd_ss IS NOT NULL AND @fk_sd_ss REGEXP '^[A-Za-z0-9_]+$' THEN
            SET @drop_fk_sd = CONCAT('ALTER TABLE service_designs DROP FOREIGN KEY `', @fk_sd_ss, '`');
            PREPARE stmt FROM @drop_fk_sd;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
        END IF;

        -- Make the column nullable
        ALTER TABLE service_designs
            MODIFY COLUMN sub_service_id INT DEFAULT NULL
                COMMENT 'References service_sub_services.id (legacy sub-service flow)';

        -- Re-add FK as nullable (ON DELETE CASCADE removes orphaned designs)
        IF NOT EXISTS (
            SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'service_designs'
              AND CONSTRAINT_NAME = 'fk_service_designs_sub_service'
        ) THEN
            ALTER TABLE service_designs
                ADD CONSTRAINT fk_service_designs_sub_service
                    FOREIGN KEY (sub_service_id) REFERENCES service_sub_services(id) ON DELETE CASCADE;
        END IF;
    END IF;

END$$

DELIMITER ;

CALL fix_service_designs_columns();
DROP PROCEDURE IF EXISTS fix_service_designs_columns;

-- Verification
SELECT 'Migration Complete' AS Status;
SHOW COLUMNS FROM service_designs;
