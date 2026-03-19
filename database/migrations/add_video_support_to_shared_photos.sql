-- Migration: Add video support to shared_photos table
-- Supports video files up to 8GB in addition to photos

-- Add file_type column to distinguish photos from videos
-- Also add file_size column to track large file sizes
ALTER TABLE shared_photos 
ADD COLUMN file_type ENUM('photo', 'video', 'file') DEFAULT 'photo' COMMENT 'Type of file: photo, video, or any other file' AFTER folder_id,
ADD COLUMN file_size BIGINT UNSIGNED DEFAULT NULL COMMENT 'File size in bytes, important for large video files' AFTER image_path;

-- Stored procedure to safely add columns if they don't exist
DELIMITER //
DROP PROCEDURE IF EXISTS AddVideoSupportToSharedPhotos//
CREATE PROCEDURE AddVideoSupportToSharedPhotos()
BEGIN
    -- Check if file_type column exists
    IF NOT EXISTS (
        SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'shared_photos' 
        AND COLUMN_NAME = 'file_type'
    ) THEN
        ALTER TABLE shared_photos 
        ADD COLUMN file_type ENUM('photo', 'video', 'file') DEFAULT 'photo' COMMENT 'Type of file: photo, video, or any other file' AFTER folder_id;
    END IF;
    
    -- Check if file_size column exists
    IF NOT EXISTS (
        SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'shared_photos' 
        AND COLUMN_NAME = 'file_size'
    ) THEN
        ALTER TABLE shared_photos 
        ADD COLUMN file_size BIGINT UNSIGNED DEFAULT NULL COMMENT 'File size in bytes, important for large video files' AFTER image_path;
    END IF;
END//
DELIMITER ;

-- Execute the procedure
CALL AddVideoSupportToSharedPhotos();

-- Clean up procedure
DROP PROCEDURE IF EXISTS AddVideoSupportToSharedPhotos;

-- Add index for file_type to optimize queries filtering by type
CREATE INDEX idx_file_type ON shared_photos(file_type);
