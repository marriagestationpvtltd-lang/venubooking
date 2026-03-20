-- Migration: Add show_preview option to shared_folders table
-- When disabled, users see only ZIP download option instead of individual photos preview
-- This saves bandwidth and provides a cleaner download-only experience

-- Add show_preview column (default to 1 for backwards compatibility)
ALTER TABLE shared_folders 
ADD COLUMN show_preview TINYINT(1) DEFAULT 1 COMMENT 'Show photo previews to users. If 0, only ZIP download is shown' AFTER allow_zip_download;

-- Stored procedure to safely add show_preview column if it doesn't exist
DELIMITER //
DROP PROCEDURE IF EXISTS AddShowPreviewToSharedFolders//
CREATE PROCEDURE AddShowPreviewToSharedFolders()
BEGIN
    -- Check if column already exists
    IF NOT EXISTS (
        SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'shared_folders' 
        AND COLUMN_NAME = 'show_preview'
    ) THEN
        ALTER TABLE shared_folders 
        ADD COLUMN show_preview TINYINT(1) DEFAULT 1 COMMENT 'Show photo previews to users. If 0, only ZIP download is shown' AFTER allow_zip_download;
    END IF;
END//
DELIMITER ;

-- Execute the procedure
CALL AddShowPreviewToSharedFolders();

-- Clean up procedure
DROP PROCEDURE IF EXISTS AddShowPreviewToSharedFolders;
