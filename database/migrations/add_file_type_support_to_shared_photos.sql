-- Migration: Add generic 'file' type to shared_photos table
-- Extends file sharing to support any file type (ZIP, PDF, DOC, etc.)
-- in addition to existing photo and video types.

-- Stored procedure to safely update the ENUM if not already updated
DELIMITER //
DROP PROCEDURE IF EXISTS AddFileTypeSupportToSharedPhotos//
CREATE PROCEDURE AddFileTypeSupportToSharedPhotos()
BEGIN
    -- Check current ENUM definition; only alter if 'file' is not already a valid value
    IF NOT EXISTS (
        SELECT * FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'shared_photos'
          AND COLUMN_NAME = 'file_type'
          AND COLUMN_TYPE LIKE '%''file''%'
    ) THEN
        ALTER TABLE shared_photos
        MODIFY COLUMN file_type ENUM('photo', 'video', 'file') DEFAULT 'photo'
            COMMENT 'Type of file: photo, video, or generic file';
    END IF;
END//
DELIMITER ;

CALL AddFileTypeSupportToSharedPhotos();
DROP PROCEDURE IF EXISTS AddFileTypeSupportToSharedPhotos;
