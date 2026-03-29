-- Migration: Fix vendor_service_cities unique key constraint
--
-- Problem: If the vendor_service_cities table was created with a UNIQUE KEY only
-- on city_id (not on the composite vendor_id+city_id), then only ONE vendor can
-- be assigned to each city. This causes:
--   1. When vendor A's cities are saved, the DELETE removes their existing rows,
--      then INSERT IGNORE silently fails for cities already assigned to vendor B.
--      Vendor A ends up with no service cities ("another vendor's city is removed").
--   2. Earlier code using REPLACE INTO or ON DUPLICATE KEY UPDATE vendor_id=?
--      would literally move the city row from vendor B to vendor A.
--
-- Fix: Ensure the UNIQUE KEY is composite (vendor_id, city_id) so multiple
-- vendors can independently share the same city as a service city.

DROP PROCEDURE IF EXISTS sp_fix_vsc_constraint;

DELIMITER //
CREATE PROCEDURE sp_fix_vsc_constraint()
BEGIN
    -- 1. Drop any incorrect single-column unique key on city_id alone
    IF EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME   = 'vendor_service_cities'
           AND INDEX_NAME   = 'uq_city'
           AND NON_UNIQUE   = 0
    ) THEN
        ALTER TABLE vendor_service_cities DROP INDEX uq_city;
    END IF;

    -- 2. Remove duplicate (vendor_id, city_id) rows, keeping the one with the smallest id
    DELETE t1 FROM vendor_service_cities t1
    INNER JOIN vendor_service_cities t2
       ON t1.vendor_id = t2.vendor_id
      AND t1.city_id   = t2.city_id
      AND t1.id > t2.id;

    -- 3. Add correct composite unique key if missing
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME   = 'vendor_service_cities'
           AND INDEX_NAME   = 'uq_vendor_city'
           AND NON_UNIQUE   = 0
    ) THEN
        ALTER TABLE vendor_service_cities
            ADD UNIQUE KEY uq_vendor_city (vendor_id, city_id);
    END IF;
END //
DELIMITER ;

CALL sp_fix_vsc_constraint();
DROP PROCEDURE IF EXISTS sp_fix_vsc_constraint;
