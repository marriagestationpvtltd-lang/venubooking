-- Migration: Add start_time and end_time to bookings table
-- Allows capturing the exact start and end time of each event booking.
-- Both columns are nullable and default based on the shift value.
-- Safe to run on databases where the columns may already exist (idempotent).
-- ============================================================================

-- NOTE: Make sure you have selected your database before running this script

DELIMITER $$

CREATE PROCEDURE add_booking_start_end_times()
BEGIN
    -- Add start_time if missing
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name   = 'bookings'
          AND column_name  = 'start_time'
    ) THEN
        ALTER TABLE bookings ADD COLUMN start_time TIME DEFAULT NULL AFTER event_date;
        UPDATE bookings SET start_time = '06:00:00' WHERE shift = 'morning'   AND start_time IS NULL;
        UPDATE bookings SET start_time = '12:00:00' WHERE shift = 'afternoon' AND start_time IS NULL;
        UPDATE bookings SET start_time = '18:00:00' WHERE shift = 'evening'   AND start_time IS NULL;
        UPDATE bookings SET start_time = '06:00:00' WHERE shift = 'fullday'   AND start_time IS NULL;
    END IF;

    -- Add end_time if missing
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name   = 'bookings'
          AND column_name  = 'end_time'
    ) THEN
        ALTER TABLE bookings ADD COLUMN end_time TIME DEFAULT NULL AFTER start_time;
        UPDATE bookings SET end_time = '12:00:00' WHERE shift = 'morning'   AND end_time IS NULL;
        UPDATE bookings SET end_time = '18:00:00' WHERE shift = 'afternoon' AND end_time IS NULL;
        UPDATE bookings SET end_time = '23:00:00' WHERE shift = 'evening'   AND end_time IS NULL;
        UPDATE bookings SET end_time = '23:00:00' WHERE shift = 'fullday'   AND end_time IS NULL;
    END IF;
END$$

DELIMITER ;

CALL add_booking_start_end_times();
DROP PROCEDURE IF EXISTS add_booking_start_end_times;

SELECT 'Migration complete: start_time and end_time columns are now present in bookings table.' AS Status;
SHOW COLUMNS FROM bookings LIKE 'start_time';
SHOW COLUMNS FROM bookings LIKE 'end_time';
