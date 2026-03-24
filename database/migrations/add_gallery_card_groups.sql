-- Migration: Add named gallery card groups for separate event galleries
-- Allows admins to create separate named gallery cards for different events
-- e.g., "Asmita & Suman's Wedding", "Bina & Rajan's Wedding"
-- Run this migration once against your database.

-- 1. Create the gallery_card_groups table
CREATE TABLE IF NOT EXISTS gallery_card_groups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    display_order INT NOT NULL DEFAULT 0,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_by INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_gcg_status (status),
    INDEX idx_gcg_display_order (display_order),
    CONSTRAINT fk_gcg_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Add card_group_id column to site_images (nullable, so existing rows are unaffected)
ALTER TABLE site_images
    ADD COLUMN IF NOT EXISTS card_group_id INT NULL DEFAULT NULL AFTER card_id,
    ADD INDEX IF NOT EXISTS idx_site_images_card_group_id (card_group_id);

-- 3. Add the foreign key constraint (safe to add after column exists)
-- Using a procedure so it only runs if the constraint does not already exist.
DROP PROCEDURE IF EXISTS _add_gcg_fk;
DELIMITER ;;
CREATE PROCEDURE _add_gcg_fk()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = DATABASE()
          AND TABLE_NAME        = 'site_images'
          AND CONSTRAINT_NAME   = 'fk_site_images_card_group'
    ) THEN
        ALTER TABLE site_images
            ADD CONSTRAINT fk_site_images_card_group
                FOREIGN KEY (card_group_id)
                REFERENCES gallery_card_groups(id)
                ON DELETE SET NULL;
    END IF;
END;;
DELIMITER ;
CALL _add_gcg_fk();
DROP PROCEDURE IF EXISTS _add_gcg_fk;
