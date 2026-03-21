-- ============================================================================
-- Fix: Add custom_venue_name and custom_hall_name to the bookings table, and
--      make hall_id nullable so customers can book without selecting a listed
--      venue/hall (custom-venue feature).
--
-- Run this if admin/bookings/index.php shows "Oops! Something went wrong" on
-- a database that was set up before the custom-venue feature was added.
--
-- This script is fully idempotent – safe to run multiple times.
-- ============================================================================

-- NOTE: Make sure you have selected your database before running this script

DELIMITER $$

CREATE PROCEDURE fix_bookings_custom_venue_columns()
BEGIN

    -- Add custom_venue_name if missing
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'bookings'
          AND column_name = 'custom_venue_name'
    ) THEN
        ALTER TABLE bookings
            ADD COLUMN custom_venue_name VARCHAR(255) DEFAULT NULL
                COMMENT 'Venue name when customer brings own venue (hall_id is NULL)'
            AFTER hall_id;
    END IF;

    -- Add custom_hall_name if missing
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'bookings'
          AND column_name = 'custom_hall_name'
    ) THEN
        ALTER TABLE bookings
            ADD COLUMN custom_hall_name VARCHAR(255) DEFAULT NULL
                COMMENT 'Hall/location name when customer brings own venue (hall_id is NULL)'
            AFTER custom_venue_name;
    END IF;

    -- Make hall_id nullable if it is currently NOT NULL
    -- (needed so custom-venue bookings can have hall_id = NULL)
    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'bookings'
          AND column_name = 'hall_id'
          AND is_nullable = 'NO'
    ) THEN
        -- Drop existing FK on hall_id before altering the column
        SET @fk_hall = NULL;
        SELECT CONSTRAINT_NAME INTO @fk_hall
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'bookings'
          AND COLUMN_NAME = 'hall_id'
          AND REFERENCED_TABLE_NAME = 'halls'
        LIMIT 1;

        IF @fk_hall IS NOT NULL AND @fk_hall REGEXP '^[A-Za-z0-9_]+$' THEN
            SET @drop_fk = CONCAT('ALTER TABLE bookings DROP FOREIGN KEY `', @fk_hall, '`');
            PREPARE stmt FROM @drop_fk;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
        END IF;

        ALTER TABLE bookings MODIFY COLUMN hall_id INT DEFAULT NULL;

        -- Re-add the FK allowing NULL so deleting a hall doesn't cascade-delete bookings
        ALTER TABLE bookings
            ADD CONSTRAINT fk_bookings_hall_id
                FOREIGN KEY (hall_id) REFERENCES halls(id) ON DELETE SET NULL;
    END IF;

END$$

DELIMITER ;

CALL fix_bookings_custom_venue_columns();

DROP PROCEDURE IF EXISTS fix_bookings_custom_venue_columns;

-- Verification
SELECT
    'Migration Complete' AS Status,
    column_name,
    is_nullable,
    column_default
FROM information_schema.columns
WHERE table_schema = DATABASE()
  AND table_name = 'bookings'
  AND column_name IN ('hall_id', 'custom_venue_name', 'custom_hall_name')
ORDER BY ordinal_position;
