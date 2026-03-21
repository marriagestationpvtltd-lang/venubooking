-- Migration: Add booking_service_id to booking_vendor_assignments
-- This links a vendor assignment to a specific booking service row so that
-- vendor assignments can be displayed inline within each service's section.
-- The column is nullable to preserve backward compatibility with existing
-- assignments that were not linked to a specific service.

DELIMITER $$

DROP PROCEDURE IF EXISTS add_booking_service_id_to_vendor_assignments $$
CREATE PROCEDURE add_booking_service_id_to_vendor_assignments()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name   = 'booking_vendor_assignments'
          AND column_name  = 'booking_service_id'
    ) THEN
        ALTER TABLE booking_vendor_assignments
            ADD COLUMN booking_service_id INT DEFAULT NULL
                COMMENT 'FK → booking_services.id; links assignment to a specific booking service row'
            AFTER booking_id,
            ADD INDEX idx_bva_booking_service_id (booking_service_id);
    END IF;
END $$

DELIMITER ;

CALL add_booking_service_id_to_vendor_assignments();
DROP PROCEDURE IF EXISTS add_booking_service_id_to_vendor_assignments;
