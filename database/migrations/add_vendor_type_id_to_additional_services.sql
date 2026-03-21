-- Migration: add_vendor_type_id_to_additional_services.sql
-- 
-- Adds a proper foreign-key column vendor_type_id to additional_services so
-- that the service ↔ vendor-type relationship is stored as an integer reference
-- instead of the previous free-text category label.
--
-- Safe to run multiple times (idempotent).

DROP PROCEDURE IF EXISTS add_vendor_type_id_to_additional_services;

DELIMITER $$
CREATE PROCEDURE add_vendor_type_id_to_additional_services()
BEGIN
    -- Add vendor_type_id column if it does not exist yet
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name   = 'additional_services'
          AND column_name  = 'vendor_type_id'
    ) THEN
        ALTER TABLE additional_services
            ADD COLUMN vendor_type_id INT DEFAULT NULL
                COMMENT 'FK → vendor_types.id; replaces free-text category field'
            AFTER category;

        -- Add index for fast lookups
        ALTER TABLE additional_services
            ADD INDEX idx_additional_services_vendor_type_id (vendor_type_id);

        -- Add foreign key (ON DELETE SET NULL keeps services when a vendor type is removed)
        ALTER TABLE additional_services
            ADD CONSTRAINT fk_additional_services_vendor_type
                FOREIGN KEY (vendor_type_id) REFERENCES vendor_types(id) ON DELETE SET NULL;
    END IF;

    -- Populate vendor_type_id for existing rows that have a category label stored
    -- Matches case-insensitively so minor capitalisation differences are tolerated
    UPDATE additional_services s
    JOIN vendor_types vt ON LOWER(TRIM(vt.label)) = LOWER(TRIM(s.category))
    SET s.vendor_type_id = vt.id
    WHERE s.vendor_type_id IS NULL
      AND s.category IS NOT NULL
      AND s.category <> '';
END$$
DELIMITER ;

CALL add_vendor_type_id_to_additional_services();
DROP PROCEDURE IF EXISTS add_vendor_type_id_to_additional_services;
