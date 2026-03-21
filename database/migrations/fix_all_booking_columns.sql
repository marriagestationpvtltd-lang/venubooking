-- ============================================================================
-- Migration: Fix all booking-related columns for existing databases
-- Description: Adds every column that createBooking() in includes/functions.php
--              requires.  Missing columns cause the entire booking to be rolled
--              back and produce a "DB schema issue" error for the customer.
--
-- Safe to run multiple times (idempotent).
-- Run this on any existing installation that was set up before these columns
-- were added, or use database/upgrade.sql for a full upgrade.
-- ============================================================================

-- NOTE: Select your database before running: USE your_db_name;

DELIMITER $$

CREATE PROCEDURE fix_all_booking_columns()
BEGIN
    -- ----------------------------------------------------------------
    -- bookings table
    -- ----------------------------------------------------------------

    -- custom_venue_name: required by createBooking() for all bookings
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

    -- custom_hall_name: required by createBooking() for all bookings
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

    -- Make hall_id nullable (custom-venue bookings store NULL)
    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'bookings'
          AND column_name = 'hall_id'
          AND is_nullable = 'NO'
    ) THEN
        SET @fk_hall = NULL;
        SELECT CONSTRAINT_NAME INTO @fk_hall
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'bookings'
          AND COLUMN_NAME = 'hall_id'
          AND REFERENCED_TABLE_NAME = 'halls'
        LIMIT 1;

        IF @fk_hall IS NOT NULL THEN
            SET @drop_fk_hall = CONCAT('ALTER TABLE bookings DROP FOREIGN KEY `', @fk_hall, '`');
            PREPARE stmt FROM @drop_fk_hall;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
        END IF;

        ALTER TABLE bookings MODIFY COLUMN hall_id INT DEFAULT NULL;

        -- Re-add FK (ON DELETE SET NULL preserves the booking when a hall is deleted)
        IF NOT EXISTS (
            SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'bookings'
              AND CONSTRAINT_NAME = 'fk_bookings_hall_id'
        ) THEN
            ALTER TABLE bookings
                ADD CONSTRAINT fk_bookings_hall_id
                    FOREIGN KEY (hall_id) REFERENCES halls(id) ON DELETE SET NULL;
        END IF;
    END IF;

    -- start_time: required by createBooking()
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'bookings'
          AND column_name = 'start_time'
    ) THEN
        ALTER TABLE bookings ADD COLUMN start_time TIME DEFAULT NULL AFTER event_date;
        UPDATE bookings SET start_time = '06:00:00' WHERE shift = 'morning'   AND start_time IS NULL;
        UPDATE bookings SET start_time = '12:00:00' WHERE shift = 'afternoon' AND start_time IS NULL;
        UPDATE bookings SET start_time = '18:00:00' WHERE shift = 'evening'   AND start_time IS NULL;
        UPDATE bookings SET start_time = '06:00:00' WHERE shift = 'fullday'   AND start_time IS NULL;
    END IF;

    -- end_time: required by createBooking()
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'bookings'
          AND column_name = 'end_time'
    ) THEN
        ALTER TABLE bookings ADD COLUMN end_time TIME DEFAULT NULL AFTER start_time;
        UPDATE bookings SET end_time = '12:00:00' WHERE shift = 'morning'   AND end_time IS NULL;
        UPDATE bookings SET end_time = '18:00:00' WHERE shift = 'afternoon' AND end_time IS NULL;
        UPDATE bookings SET end_time = '23:00:00' WHERE shift = 'evening'   AND end_time IS NULL;
        UPDATE bookings SET end_time = '23:00:00' WHERE shift = 'fullday'   AND end_time IS NULL;
    END IF;

    -- advance_payment_received
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'bookings'
          AND column_name = 'advance_payment_received'
    ) THEN
        ALTER TABLE bookings
            ADD COLUMN advance_payment_received TINYINT(1) DEFAULT 0
                COMMENT 'Whether advance payment has been received (0=No, 1=Yes)'
            AFTER payment_status;
        ALTER TABLE bookings ADD INDEX idx_advance_payment_received (advance_payment_received);
    END IF;

    -- ----------------------------------------------------------------
    -- booking_services table
    -- All columns below are inserted by createBooking().  If any are
    -- absent the INSERT fails and rolls back the whole booking.
    -- ----------------------------------------------------------------

    -- description
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'booking_services'
          AND column_name = 'description'
    ) THEN
        ALTER TABLE booking_services ADD COLUMN description TEXT AFTER price;
    END IF;

    -- category
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'booking_services'
          AND column_name = 'category'
    ) THEN
        ALTER TABLE booking_services ADD COLUMN category VARCHAR(100) AFTER description;
    END IF;

    -- added_by
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'booking_services'
          AND column_name = 'added_by'
    ) THEN
        ALTER TABLE booking_services
            ADD COLUMN added_by ENUM('user', 'admin') DEFAULT 'user'
                COMMENT 'Who added the service: user during booking or admin later'
            AFTER category;
        UPDATE booking_services SET added_by = 'user' WHERE added_by IS NULL;
    END IF;

    -- quantity
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'booking_services'
          AND column_name = 'quantity'
    ) THEN
        ALTER TABLE booking_services
            ADD COLUMN quantity INT DEFAULT 1
                COMMENT 'Quantity of service'
            AFTER added_by;
        UPDATE booking_services SET quantity = 1 WHERE quantity IS NULL;
    END IF;

    -- sub_service_id (needed for design insertions)
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'booking_services'
          AND column_name = 'sub_service_id'
    ) THEN
        ALTER TABLE booking_services
            ADD COLUMN sub_service_id INT DEFAULT NULL
                COMMENT 'References service_sub_services.id if this is a design selection'
            AFTER quantity;
    END IF;

    -- design_id (needed for design insertions)
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'booking_services'
          AND column_name = 'design_id'
    ) THEN
        ALTER TABLE booking_services
            ADD COLUMN design_id INT DEFAULT NULL
                COMMENT 'References service_designs.id if this is a design selection'
            AFTER sub_service_id;
    END IF;

    -- Remove the FK on service_id if it exists (admin services use service_id=0
    -- which violates the FK constraint and would block admin service insertions).
    SET @fk_svc = NULL;
    SELECT CONSTRAINT_NAME INTO @fk_svc
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'booking_services'
      AND COLUMN_NAME = 'service_id'
      AND REFERENCED_TABLE_NAME = 'additional_services'
    LIMIT 1;

    IF @fk_svc IS NOT NULL THEN
        SET @drop_fk_svc = CONCAT('ALTER TABLE booking_services DROP FOREIGN KEY `', @fk_svc, '`');
        PREPARE stmt FROM @drop_fk_svc;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;

    -- Ensure service_id defaults to 0 (admin services have no reference row)
    ALTER TABLE booking_services
        MODIFY service_id INT NOT NULL DEFAULT 0
            COMMENT '0 for admin services, >0 for user services referencing additional_services';

    -- Add indexes if missing
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.statistics
        WHERE table_schema = DATABASE()
          AND table_name = 'booking_services'
          AND index_name = 'idx_booking_services_added_by'
    ) THEN
        CREATE INDEX idx_booking_services_added_by ON booking_services(added_by);
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.statistics
        WHERE table_schema = DATABASE()
          AND table_name = 'booking_services'
          AND index_name = 'idx_booking_services_service_id'
    ) THEN
        CREATE INDEX idx_booking_services_service_id ON booking_services(service_id);
    END IF;

END$$

DELIMITER ;

CALL fix_all_booking_columns();
DROP PROCEDURE IF EXISTS fix_all_booking_columns;

-- Verification
SELECT 'Migration Complete' AS Status;
SHOW COLUMNS FROM bookings;
SHOW COLUMNS FROM booking_services;
