-- Migration: add_payment_status_automation_trigger.sql
-- Purpose : Keep booking_status and advance_payment_received in sync with
--           payment_status automatically via a MySQL BEFORE UPDATE trigger.
--
-- Logic:
--   payment_status = 'pending'  → booking_status = 'pending',   advance_payment_received = 0
--   payment_status = 'partial'  → booking_status = 'confirmed', advance_payment_received = 1
--   payment_status = 'paid'     → booking_status = 'completed', advance_payment_received = 1
--   payment_status = anything else → no automatic change (e.g. 'cancelled')
--
-- The application layer (update-payment-status.php) applies the same rules in PHP,
-- so this trigger is a safety-net for any direct SQL updates.

DROP TRIGGER IF EXISTS trg_bookings_payment_status_sync;

DELIMITER $$

CREATE TRIGGER trg_bookings_payment_status_sync
BEFORE UPDATE ON bookings
FOR EACH ROW
BEGIN
    -- Only act when payment_status actually changes
    IF NEW.payment_status <> OLD.payment_status THEN
        CASE NEW.payment_status
            WHEN 'pending' THEN
                SET NEW.booking_status          = 'pending';
                SET NEW.advance_payment_received = 0;
            WHEN 'partial' THEN
                SET NEW.booking_status          = 'confirmed';
                SET NEW.advance_payment_received = 1;
            WHEN 'paid' THEN
                SET NEW.booking_status          = 'completed';
                SET NEW.advance_payment_received = 1;
            ELSE
                -- 'cancelled' or any future status: leave as-is
                BEGIN END;
        END CASE;
    END IF;
END$$

DELIMITER ;
