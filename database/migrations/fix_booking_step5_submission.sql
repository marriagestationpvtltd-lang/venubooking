-- ============================================================================
-- Fix: Ensure booking_services table has all required columns for booking
--      submission to work correctly from booking-step5.php
-- ============================================================================
-- This migration safely adds any missing columns to the booking_services table.
-- It is safe to run multiple times (idempotent).
--
-- Run this if bookings are not being stored when submitted from booking-step5.php
-- ============================================================================

-- NOTE: Make sure you have selected your database before running this script

DELIMITER $$

CREATE PROCEDURE fix_booking_services_columns()
BEGIN
    -- Add 'description' column if missing
    IF NOT EXISTS (
        SELECT * FROM information_schema.columns
        WHERE table_schema = DATABASE()
        AND table_name = 'booking_services'
        AND column_name = 'description'
    ) THEN
        ALTER TABLE booking_services
        ADD COLUMN description TEXT AFTER price;
    END IF;

    -- Add 'category' column if missing
    IF NOT EXISTS (
        SELECT * FROM information_schema.columns
        WHERE table_schema = DATABASE()
        AND table_name = 'booking_services'
        AND column_name = 'category'
    ) THEN
        ALTER TABLE booking_services
        ADD COLUMN category VARCHAR(100) AFTER description;
    END IF;

    -- Add 'added_by' column if missing
    IF NOT EXISTS (
        SELECT * FROM information_schema.columns
        WHERE table_schema = DATABASE()
        AND table_name = 'booking_services'
        AND column_name = 'added_by'
    ) THEN
        ALTER TABLE booking_services
        ADD COLUMN added_by ENUM('user', 'admin') DEFAULT 'user'
        COMMENT 'Who added the service: user during booking or admin later'
        AFTER category;
    END IF;

    -- Add 'quantity' column if missing
    IF NOT EXISTS (
        SELECT * FROM information_schema.columns
        WHERE table_schema = DATABASE()
        AND table_name = 'booking_services'
        AND column_name = 'quantity'
    ) THEN
        ALTER TABLE booking_services
        ADD COLUMN quantity INT DEFAULT 1
        COMMENT 'Quantity of service'
        AFTER added_by;
    END IF;

    -- Remove foreign key on service_id if it exists
    -- (admin services use service_id=0 which violates the FK)
    SET @fk_name = NULL;
    SELECT CONSTRAINT_NAME INTO @fk_name
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'booking_services'
    AND COLUMN_NAME = 'service_id'
    AND REFERENCED_TABLE_NAME = 'additional_services'
    LIMIT 1;

    IF @fk_name IS NOT NULL THEN
        SET @drop_fk = CONCAT('ALTER TABLE booking_services DROP FOREIGN KEY ', @fk_name);
        PREPARE stmt FROM @drop_fk;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;

    -- Ensure service_id defaults to 0 (for admin-added services)
    ALTER TABLE booking_services
    MODIFY service_id INT NOT NULL DEFAULT 0
    COMMENT '0 for admin services, >0 for user services referencing additional_services';

    -- Create index on added_by if missing
    IF NOT EXISTS (
        SELECT * FROM information_schema.statistics
        WHERE table_schema = DATABASE()
        AND table_name = 'booking_services'
        AND index_name = 'idx_booking_services_added_by'
    ) THEN
        CREATE INDEX idx_booking_services_added_by ON booking_services(added_by);
    END IF;

    -- Create index on service_id if missing
    IF NOT EXISTS (
        SELECT * FROM information_schema.statistics
        WHERE table_schema = DATABASE()
        AND table_name = 'booking_services'
        AND index_name = 'idx_booking_services_service_id'
    ) THEN
        CREATE INDEX idx_booking_services_service_id ON booking_services(service_id);
    END IF;
END$$

DELIMITER ;

-- Run the procedure
CALL fix_booking_services_columns();

-- Clean up
DROP PROCEDURE IF EXISTS fix_booking_services_columns;

-- Verification
SELECT
    'Migration Complete' AS Status,
    COUNT(*) AS total_services
FROM booking_services;

SHOW COLUMNS FROM booking_services;
