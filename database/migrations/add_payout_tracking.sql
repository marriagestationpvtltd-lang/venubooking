-- ============================================================================
-- Migration: add_payout_tracking.sql
-- Adds payout tracking fields to record how much has been paid out
-- to each vendor (per assignment) and to the venue provider (per booking).
-- ============================================================================

DROP PROCEDURE IF EXISTS add_payout_tracking_columns;

DELIMITER $$
CREATE PROCEDURE add_payout_tracking_columns()
BEGIN
    -- Add amount_paid to booking_vendor_assignments if it doesn't exist
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'booking_vendor_assignments'
          AND column_name = 'amount_paid'
    ) THEN
        ALTER TABLE booking_vendor_assignments
            ADD COLUMN amount_paid DECIMAL(10,2) NOT NULL DEFAULT 0
                COMMENT 'Amount actually paid out to this vendor for this assignment'
            AFTER assigned_amount;
    END IF;

    -- Add venue_amount_paid to bookings if it doesn't exist
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'bookings'
          AND column_name = 'venue_amount_paid'
    ) THEN
        ALTER TABLE bookings
            ADD COLUMN venue_amount_paid DECIMAL(10,2) NOT NULL DEFAULT 0
                COMMENT 'Amount actually paid out to the venue provider (hall + menu charges)'
            AFTER advance_amount_received;
    END IF;
END$$

DELIMITER ;

CALL add_payout_tracking_columns();
DROP PROCEDURE IF EXISTS add_payout_tracking_columns;
