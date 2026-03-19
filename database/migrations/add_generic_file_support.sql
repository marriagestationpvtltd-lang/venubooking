-- Migration: Add generic file support to shared_photos table
-- Extends file_type ENUM to support any type of file (zip, pdf, docx, etc.)

DELIMITER //
DROP PROCEDURE IF EXISTS AddGenericFileSupport//
CREATE PROCEDURE AddGenericFileSupport()
BEGIN
    -- Check if file_type column already supports 'file' value
    IF NOT EXISTS (
        SELECT * FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'shared_photos'
          AND COLUMN_NAME = 'file_type'
          AND COLUMN_TYPE LIKE "%'file'%"
    ) THEN
        ALTER TABLE shared_photos
        MODIFY COLUMN file_type ENUM('photo', 'video', 'file') DEFAULT 'photo'
            COMMENT 'Type of file: photo, video, or generic file';
    END IF;
END//
DELIMITER ;

CALL AddGenericFileSupport();
DROP PROCEDURE IF EXISTS AddGenericFileSupport;
