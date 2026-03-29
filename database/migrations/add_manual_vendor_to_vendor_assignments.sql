-- Migration: add manual vendor support to booking_vendor_assignments
-- Allows admins to record a vendor by name/phone when the vendor is not in the system.
-- vendor_id becomes nullable; new columns manual_vendor_name and manual_vendor_phone are added.

-- Step 1: drop the existing NOT-NULL foreign key on vendor_id
ALTER TABLE booking_vendor_assignments
    DROP FOREIGN KEY IF EXISTS booking_vendor_assignments_ibfk_2;

-- Try alternate auto-generated FK names (different MySQL versions may differ)
-- The DROP FOREIGN KEY above covers common names; we add a second attempt just in case.
ALTER TABLE booking_vendor_assignments
    MODIFY COLUMN vendor_id INT DEFAULT NULL COMMENT 'NULL for manual (non-system) vendors';

-- Step 2: re-add the foreign key (MySQL silently ignores NULL values in FK columns)
ALTER TABLE booking_vendor_assignments
    ADD CONSTRAINT fk_bva_vendor_id
        FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE RESTRICT;

-- Step 3: add manual vendor columns (guarded with PROCEDURE to avoid errors on re-run)
DROP PROCEDURE IF EXISTS _add_manual_vendor_columns;
DELIMITER //
CREATE PROCEDURE _add_manual_vendor_columns()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'booking_vendor_assignments'
          AND COLUMN_NAME = 'manual_vendor_name'
    ) THEN
        ALTER TABLE booking_vendor_assignments
            ADD COLUMN manual_vendor_name VARCHAR(255) DEFAULT NULL
                COMMENT 'Free-text vendor name when vendor_id IS NULL';
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'booking_vendor_assignments'
          AND COLUMN_NAME = 'manual_vendor_phone'
    ) THEN
        ALTER TABLE booking_vendor_assignments
            ADD COLUMN manual_vendor_phone VARCHAR(50) DEFAULT NULL
                COMMENT 'Free-text vendor phone when vendor_id IS NULL';
    END IF;
END //
DELIMITER ;
CALL _add_manual_vendor_columns();
DROP PROCEDURE IF EXISTS _add_manual_vendor_columns;
