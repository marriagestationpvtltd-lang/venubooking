-- ============================================================================
-- Fix Admin Services: Add missing columns to booking_services table
-- ============================================================================
-- This migration adds the 'added_by' and 'quantity' columns required for
-- the admin services feature to work properly.
--
-- It also removes the problematic foreign key on service_id since admin
-- services use service_id=0 which doesn't exist in additional_services table.
--
-- Run this if you're getting "Failed to add admin service" error
-- ============================================================================

-- Check if columns already exist before adding them
-- MySQL doesn't have IF NOT EXISTS for ALTER TABLE ADD COLUMN, so we use a procedure

DELIMITER $$

CREATE PROCEDURE add_admin_services_columns()
BEGIN
    DECLARE CONTINUE HANDLER FOR SQLSTATE '42000' SET @dummy = 1;
    DECLARE CONTINUE HANDLER FOR SQLSTATE '42S21' SET @dummy = 1;
    DECLARE CONTINUE HANDLER FOR SQLSTATE '42S22' SET @dummy = 1;
    DECLARE CONTINUE HANDLER FOR SQLSTATE 'HY000' SET @dummy = 1;
    
    -- Drop foreign key on service_id if it exists (causes issues with admin services)
    SET @constraint_name = NULL;
    SELECT CONSTRAINT_NAME INTO @constraint_name
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'booking_services'
    AND COLUMN_NAME = 'service_id'
    AND REFERENCED_TABLE_NAME = 'additional_services'
    LIMIT 1;
    
    IF @constraint_name IS NOT NULL THEN
        SET @sql = CONCAT('ALTER TABLE booking_services DROP FOREIGN KEY ', @constraint_name);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
    
    -- Check and add 'added_by' column
    IF NOT EXISTS (
        SELECT * FROM information_schema.columns 
        WHERE table_schema = DATABASE() 
        AND table_name = 'booking_services' 
        AND column_name = 'added_by'
    ) THEN
        ALTER TABLE booking_services 
        ADD COLUMN added_by ENUM('user', 'admin') DEFAULT 'user' 
        COMMENT 'Who added the service: user during booking or admin later'
        AFTER category;
        
        -- Update existing records to mark them as user-added services
        UPDATE booking_services SET added_by = 'user' WHERE added_by IS NULL;
    END IF;
    
    -- Check and add 'quantity' column
    IF NOT EXISTS (
        SELECT * FROM information_schema.columns 
        WHERE table_schema = DATABASE() 
        AND table_name = 'booking_services' 
        AND column_name = 'quantity'
    ) THEN
        ALTER TABLE booking_services 
        ADD COLUMN quantity INT DEFAULT 1 
        COMMENT 'Quantity of service'
        AFTER added_by;
        
        -- Update existing records with default quantity
        UPDATE booking_services SET quantity = 1 WHERE quantity IS NULL;
    END IF;
    
    -- Set service_id default to 0 for compatibility with admin services
    ALTER TABLE booking_services MODIFY service_id INT NOT NULL DEFAULT 0 
    COMMENT '0 for admin services, >0 for user services referencing additional_services';
    
    -- Create index for better query performance (if not exists)
    IF NOT EXISTS (
        SELECT * FROM information_schema.statistics 
        WHERE table_schema = DATABASE() 
        AND table_name = 'booking_services' 
        AND index_name = 'idx_booking_services_added_by'
    ) THEN
        CREATE INDEX idx_booking_services_added_by ON booking_services(added_by);
    END IF;
    
    -- Create index on service_id if not exists
    IF NOT EXISTS (
        SELECT * FROM information_schema.statistics 
        WHERE table_schema = DATABASE() 
        AND table_name = 'booking_services' 
        AND index_name = 'idx_booking_services_service_id'
    ) THEN
        CREATE INDEX idx_booking_services_service_id ON booking_services(service_id);
    END IF;
END$$

DELIMITER ;

-- Execute the procedure
CALL add_admin_services_columns();

-- Drop the procedure
DROP PROCEDURE IF EXISTS add_admin_services_columns;

-- Verification query
SELECT 
    'Migration Complete' as Status,
    COUNT(*) as total_services,
    SUM(CASE WHEN added_by = 'user' THEN 1 ELSE 0 END) as user_services,
    SUM(CASE WHEN added_by = 'admin' THEN 1 ELSE 0 END) as admin_services,
    AVG(quantity) as avg_quantity
FROM booking_services;

-- Display table structure to verify
SHOW COLUMNS FROM booking_services;
