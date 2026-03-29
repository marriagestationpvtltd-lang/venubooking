-- Migration: add manual_vendor_type column to booking_vendor_assignments
-- This stores the vendor type (e.g. 'photographer', 'pandit') for manually-entered vendors
-- so the type is preserved alongside the name/phone for display and future reuse.

DROP PROCEDURE IF EXISTS _add_manual_vendor_type_column;
DELIMITER //
CREATE PROCEDURE _add_manual_vendor_type_column()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'booking_vendor_assignments'
          AND COLUMN_NAME  = 'manual_vendor_type'
    ) THEN
        ALTER TABLE booking_vendor_assignments
            ADD COLUMN manual_vendor_type VARCHAR(100) DEFAULT NULL
                COMMENT 'Vendor type slug (from vendor_types) for manual vendors (when vendor_id IS NULL)';
    END IF;
END //
DELIMITER ;
CALL _add_manual_vendor_type_column();
DROP PROCEDURE IF EXISTS _add_manual_vendor_type_column;
