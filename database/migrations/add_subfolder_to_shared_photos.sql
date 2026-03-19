-- Migration: Add subfolder_name to shared_photos for album/sub-folder grouping within shared folders
-- This allows photos inside a shared folder to be organised into named sub-albums.
-- Public view: users see sub-folder cards first, then photos after clicking a sub-folder.

DELIMITER //
DROP PROCEDURE IF EXISTS AddSubfolderNameToSharedPhotos//
CREATE PROCEDURE AddSubfolderNameToSharedPhotos()
BEGIN
    IF NOT EXISTS (
        SELECT * FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'shared_photos'
          AND COLUMN_NAME  = 'subfolder_name'
    ) THEN
        ALTER TABLE shared_photos
            ADD COLUMN subfolder_name VARCHAR(255) NULL DEFAULT NULL
                COMMENT 'Album/sub-folder name for grouping photos within a shared folder'
                AFTER folder_id,
            ADD INDEX idx_subfolder_name (subfolder_name);
    END IF;
END//
DELIMITER ;

CALL AddSubfolderNameToSharedPhotos();
DROP PROCEDURE IF EXISTS AddSubfolderNameToSharedPhotos;
