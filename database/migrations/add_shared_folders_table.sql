-- Migration: Add shared_folders table for folder-based photo sharing
-- Similar to Google Drive - upload photos to folders and share folder link

-- Create shared_folders table for folder-based sharing
CREATE TABLE IF NOT EXISTS shared_folders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    folder_name VARCHAR(255) NOT NULL COMMENT 'Display name for the folder',
    description TEXT NULL COMMENT 'Optional description of folder contents',
    download_token VARCHAR(64) NOT NULL UNIQUE COMMENT 'Unique token for shareable folder link',
    photo_count INT DEFAULT 0 COMMENT 'Cached count of photos in folder',
    total_downloads INT DEFAULT 0 COMMENT 'Total download count across all photos',
    max_downloads INT DEFAULT NULL COMMENT 'Maximum allowed downloads per photo, NULL for unlimited',
    expires_at DATETIME DEFAULT NULL COMMENT 'Folder expiration date, NULL for never',
    status ENUM('active', 'inactive', 'expired') DEFAULT 'active',
    allow_zip_download TINYINT(1) DEFAULT 1 COMMENT 'Allow downloading all photos as ZIP',
    created_by INT NULL COMMENT 'Admin user who created the folder',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_download_token (download_token),
    INDEX idx_status (status),
    INDEX idx_expires_at (expires_at),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add folder_id column to existing shared_photos table
-- Photos can optionally belong to a folder for folder-based sharing
ALTER TABLE shared_photos 
ADD COLUMN folder_id INT NULL COMMENT 'Folder this photo belongs to, NULL for standalone photo' AFTER id,
ADD INDEX idx_folder_id (folder_id),
ADD CONSTRAINT fk_shared_photos_folder FOREIGN KEY (folder_id) REFERENCES shared_folders(id) ON DELETE CASCADE;

-- Stored procedure to safely add folder_id column if it doesn't exist
DELIMITER //
DROP PROCEDURE IF EXISTS AddFolderIdToSharedPhotos//
CREATE PROCEDURE AddFolderIdToSharedPhotos()
BEGIN
    -- Check if column already exists
    IF NOT EXISTS (
        SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'shared_photos' 
        AND COLUMN_NAME = 'folder_id'
    ) THEN
        ALTER TABLE shared_photos 
        ADD COLUMN folder_id INT NULL COMMENT 'Folder this photo belongs to, NULL for standalone photo' AFTER id,
        ADD INDEX idx_folder_id (folder_id);
        
        -- Add foreign key separately for easier error handling
        SET @sql = 'ALTER TABLE shared_photos ADD CONSTRAINT fk_shared_photos_folder FOREIGN KEY (folder_id) REFERENCES shared_folders(id) ON DELETE CASCADE';
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END//
DELIMITER ;

-- Execute the procedure
CALL AddFolderIdToSharedPhotos();

-- Clean up procedure
DROP PROCEDURE IF EXISTS AddFolderIdToSharedPhotos;

-- Trigger to update photo_count when photos are added/removed
DELIMITER //
DROP TRIGGER IF EXISTS trg_shared_photos_insert//
CREATE TRIGGER trg_shared_photos_insert
AFTER INSERT ON shared_photos
FOR EACH ROW
BEGIN
    IF NEW.folder_id IS NOT NULL THEN
        UPDATE shared_folders 
        SET photo_count = photo_count + 1,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = NEW.folder_id;
    END IF;
END//

DROP TRIGGER IF EXISTS trg_shared_photos_delete//
CREATE TRIGGER trg_shared_photos_delete
AFTER DELETE ON shared_photos
FOR EACH ROW
BEGIN
    IF OLD.folder_id IS NOT NULL THEN
        UPDATE shared_folders 
        SET photo_count = GREATEST(0, photo_count - 1),
            updated_at = CURRENT_TIMESTAMP
        WHERE id = OLD.folder_id;
    END IF;
END//

DROP TRIGGER IF EXISTS trg_shared_photos_update//
CREATE TRIGGER trg_shared_photos_update
AFTER UPDATE ON shared_photos
FOR EACH ROW
BEGIN
    -- Handle folder changes
    IF OLD.folder_id IS NOT NULL AND (NEW.folder_id IS NULL OR NEW.folder_id != OLD.folder_id) THEN
        UPDATE shared_folders 
        SET photo_count = GREATEST(0, photo_count - 1),
            updated_at = CURRENT_TIMESTAMP
        WHERE id = OLD.folder_id;
    END IF;
    
    IF NEW.folder_id IS NOT NULL AND (OLD.folder_id IS NULL OR NEW.folder_id != OLD.folder_id) THEN
        UPDATE shared_folders 
        SET photo_count = photo_count + 1,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = NEW.folder_id;
    END IF;
END//
DELIMITER ;
