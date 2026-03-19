-- Migration: Add thumbnail_path column to shared_photos
-- Stores relative path to auto-generated JPEG preview thumbnail for fast grid display.
-- The original file (image_path) is preserved at full quality for download.

DELIMITER //
DROP PROCEDURE IF EXISTS AddThumbnailPathToSharedPhotos//
CREATE PROCEDURE AddThumbnailPathToSharedPhotos()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'shared_photos'
          AND column_name = 'thumbnail_path'
    ) THEN
        ALTER TABLE shared_photos
            ADD COLUMN thumbnail_path VARCHAR(255) DEFAULT NULL
            COMMENT 'Relative path to auto-generated preview thumbnail, NULL if not generated'
            AFTER file_size;
    END IF;
END//
DELIMITER ;

CALL AddThumbnailPathToSharedPhotos();
DROP PROCEDURE IF EXISTS AddThumbnailPathToSharedPhotos;
