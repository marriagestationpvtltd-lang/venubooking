-- ============================================================================
-- Migration: Fix package booking confirmation issues
-- Description: Resolves all known causes of "booking not confirmed" when a
--              user selects a service package and submits the booking form.
--
-- Root causes addressed:
--   1. policy_pages.require_acceptance = 1 on auto-seeded rows blocks ALL
--      booking confirmations; reset to 0 (admin can re-enable via admin panel).
--   2. booking_services FK on service_id → additional_services causes package
--      INSERT to fail (package IDs come from service_packages, not
--      additional_services).
--   3. Missing booking_services columns (description, category, added_by,
--      quantity) cause the package INSERT to fail and the outer try-catch to
--      roll back the entire booking.
--   4. Missing bookings.menu_special_instructions causes the main booking
--      INSERT to fail for ALL bookings.
--   5. Missing customers.city causes getOrCreateCustomer() to fail and roll
--      back the booking.
--   6. Missing service_package_features.service_id prevents booking-step5
--      from loading the included-service list (non-fatal but blocks UX).
--   7. Missing service_package_photos table causes booking-step4 / step6
--      package summary to throw if the table is queried.
--
-- Safe to run multiple times (idempotent).
-- Run on production: mysql -u USER -p DATABASE < fix_package_booking_schema.sql
-- ============================================================================

-- NOTE: Select your database before running: USE your_db_name;

DELIMITER $$

DROP PROCEDURE IF EXISTS fix_package_booking_schema$$

CREATE PROCEDURE fix_package_booking_schema()
BEGIN

    -- ----------------------------------------------------------------
    -- 1. Reset auto-seeded policy pages that block booking confirmation
    -- ----------------------------------------------------------------
    -- Policy pages seeded by the upgrade script with require_acceptance=1
    -- prevent ALL bookings from being confirmed until the user explicitly
    -- accepts them.  Reset to 0 so bookings work out-of-the-box; admins can
    -- enable acceptance requirements from Admin → Policy Pages.
    IF EXISTS (
        SELECT 1 FROM information_schema.tables
        WHERE table_schema = DATABASE() AND table_name = 'policy_pages'
    ) THEN
        UPDATE `policy_pages`
           SET `require_acceptance` = 0
         WHERE `slug` IN ('terms-and-conditions', 'privacy-policy', 'refund-policy')
           AND `require_acceptance` = 1;
    END IF;

    -- ----------------------------------------------------------------
    -- 2. customers table: add city / loyalty_points if missing
    -- ----------------------------------------------------------------
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'customers'
          AND column_name = 'city'
    ) THEN
        ALTER TABLE customers ADD COLUMN city VARCHAR(100) NULL AFTER address;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'customers'
          AND column_name = 'loyalty_points'
    ) THEN
        ALTER TABLE customers
            ADD COLUMN loyalty_points INT NOT NULL DEFAULT 0
                COMMENT 'Accumulated loyalty points'
            AFTER city;
    END IF;

    -- ----------------------------------------------------------------
    -- 3. bookings table: add menu_special_instructions if missing
    -- ----------------------------------------------------------------
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'bookings'
          AND column_name = 'menu_special_instructions'
    ) THEN
        ALTER TABLE bookings
            ADD COLUMN menu_special_instructions TEXT DEFAULT NULL
                COMMENT 'Special instructions for the menu entered during booking'
            AFTER special_requests;
    END IF;

    -- ----------------------------------------------------------------
    -- 4. booking_services table: add missing columns and drop FK
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
            ADD COLUMN added_by ENUM('user','admin') DEFAULT 'user' AFTER category;
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
            ADD COLUMN quantity INT DEFAULT 1 AFTER added_by;
        UPDATE booking_services SET quantity = 1 WHERE quantity IS NULL;
    END IF;

    -- sub_service_id
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'booking_services'
          AND column_name = 'sub_service_id'
    ) THEN
        ALTER TABLE booking_services
            ADD COLUMN sub_service_id INT DEFAULT NULL AFTER quantity;
    END IF;

    -- design_id
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'booking_services'
          AND column_name = 'design_id'
    ) THEN
        ALTER TABLE booking_services
            ADD COLUMN design_id INT DEFAULT NULL AFTER sub_service_id;
    END IF;

    -- Drop FK on service_id → additional_services if it exists.
    -- Package rows use service_id = service_packages.id which is NOT a valid
    -- additional_services.id; the FK would cause every package INSERT to fail.
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

    -- Ensure service_id allows 0 (admin services and packages may not map to
    -- any additional_services row).
    ALTER TABLE booking_services
        MODIFY service_id INT NOT NULL DEFAULT 0
            COMMENT '0 for admin/package rows, >0 for rows referencing additional_services';

    -- ----------------------------------------------------------------
    -- 5. service_package_features: add service_id if missing
    -- ----------------------------------------------------------------
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'service_package_features'
          AND column_name = 'service_id'
    ) THEN
        ALTER TABLE service_package_features
            ADD COLUMN service_id INT DEFAULT NULL
                COMMENT 'FK → additional_services.id; NULL for free-text features'
            AFTER feature_text;
    END IF;

    -- ----------------------------------------------------------------
    -- 6. service_package_photos: create table if missing
    -- ----------------------------------------------------------------
    CREATE TABLE IF NOT EXISTS service_package_photos (
        id           INT PRIMARY KEY AUTO_INCREMENT,
        package_id   INT NOT NULL,
        image_path   VARCHAR(255) NOT NULL,
        display_order INT DEFAULT 0,
        created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (package_id) REFERENCES service_packages(id) ON DELETE CASCADE,
        INDEX idx_spp_package_id   (package_id),
        INDEX idx_spp_display_order (display_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    -- ----------------------------------------------------------------
    -- 7. booking_menus: add extra_total if missing
    -- ----------------------------------------------------------------
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'booking_menus'
          AND column_name = 'extra_total'
    ) THEN
        ALTER TABLE booking_menus
            ADD COLUMN extra_total DECIMAL(10,2) NOT NULL DEFAULT 0.00
                COMMENT 'Sum of extra_charge values from custom item selections'
            AFTER total_price;
    END IF;

END$$

DELIMITER ;

CALL fix_package_booking_schema();
DROP PROCEDURE IF EXISTS fix_package_booking_schema;

SELECT 'fix_package_booking_schema complete' AS Status;
