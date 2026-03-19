-- Migration: Allow any file type in shared_photos table
-- Extends file_type ENUM to include 'file' for documents, archives, and other file types

-- Update file_type column to support 'file' as a generic type
DELIMITER //
DROP PROCEDURE IF EXISTS AddAnyFileTypeSupport//
CREATE PROCEDURE AddAnyFileTypeSupport()
BEGIN
    -- Check current column type and update ENUM to include 'file'
    IF EXISTS (
        SELECT * FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'shared_photos'
        AND COLUMN_NAME = 'file_type'
    ) THEN
        ALTER TABLE shared_photos
        MODIFY COLUMN file_type ENUM('photo', 'video', 'file') DEFAULT 'photo'
        COMMENT 'Type of file: photo, video, or any other file';
    END IF;
END//
DELIMITER ;

CALL AddAnyFileTypeSupport();
DROP PROCEDURE IF EXISTS AddAnyFileTypeSupport;
