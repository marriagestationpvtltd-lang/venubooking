-- Migration: Add public transfer fields to shared_folders table
-- This enables the TransferNow-like public file transfer feature where
-- anyone can upload files and share a download link without logging in.
-- Safe to run on databases where the columns may already exist (idempotent).
-- ============================================================================

DELIMITER $$

CREATE PROCEDURE add_transfer_fields_to_shared_folders()
BEGIN
    -- Add sender_email column (email of the person who uploaded the files)
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name   = 'shared_folders'
          AND column_name  = 'sender_email'
    ) THEN
        ALTER TABLE shared_folders
            ADD COLUMN sender_email VARCHAR(255) NULL DEFAULT NULL
            COMMENT 'Email of the sender for public transfers'
            AFTER show_preview;
    END IF;

    -- Add sender_message column (optional message from sender to recipient)
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name   = 'shared_folders'
          AND column_name  = 'sender_message'
    ) THEN
        ALTER TABLE shared_folders
            ADD COLUMN sender_message TEXT NULL DEFAULT NULL
            COMMENT 'Optional message from sender to recipient'
            AFTER sender_email;
    END IF;

    -- Add transfer_source column to distinguish admin folders from public transfers
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name   = 'shared_folders'
          AND column_name  = 'transfer_source'
    ) THEN
        ALTER TABLE shared_folders
            ADD COLUMN transfer_source ENUM('admin', 'public') NOT NULL DEFAULT 'admin'
            COMMENT 'Origin: admin-created folder or public transfer'
            AFTER sender_message;
        ALTER TABLE shared_folders ADD INDEX idx_transfer_source (transfer_source);
    END IF;
END$$

DELIMITER ;

CALL add_transfer_fields_to_shared_folders();
DROP PROCEDURE IF EXISTS add_transfer_fields_to_shared_folders;
