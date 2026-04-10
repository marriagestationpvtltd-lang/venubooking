-- Migration: Add call_sessions table and account_type to customers
-- Purpose: Support in-app WebRTC calling between customers and admin with
--          caller ID display (name, account type, package/booking info) and
--          call-waiting queue so multiple admins can handle concurrent calls.
-- Run this migration once on existing installations.

-- ============================================================================
-- 1. Add account_type to customers (free / premium)
-- ============================================================================
DROP PROCEDURE IF EXISTS add_customer_account_type;
DELIMITER $$
CREATE PROCEDURE add_customer_account_type()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name   = 'customers'
          AND column_name  = 'account_type'
    ) THEN
        ALTER TABLE customers
            ADD COLUMN account_type ENUM('free', 'premium') NOT NULL DEFAULT 'free'
            COMMENT 'Customer subscription tier: free or premium'
            AFTER loyalty_points;
    END IF;
END$$
DELIMITER ;
CALL add_customer_account_type();
DROP PROCEDURE IF EXISTS add_customer_account_type;

-- ============================================================================
-- 2. Create call_sessions table
-- ============================================================================
CREATE TABLE IF NOT EXISTS call_sessions (
    id           INT          PRIMARY KEY AUTO_INCREMENT,
    session_token VARCHAR(64) UNIQUE NOT NULL COMMENT 'Random token shared with caller browser',
    customer_id  INT          DEFAULT NULL COMMENT 'Matched customer record (NULL for unidentified callers)',
    caller_name  VARCHAR(255) NOT NULL DEFAULT 'Guest',
    caller_phone VARCHAR(20)  NOT NULL DEFAULT '',
    account_type ENUM('free','premium') NOT NULL DEFAULT 'free' COMMENT 'Snapshot of customer account_type at call time',
    last_booking_number VARCHAR(50) DEFAULT NULL COMMENT 'Most recent booking number for this customer',
    last_package_name   VARCHAR(255) DEFAULT NULL COMMENT 'Most recent package/hall name for context',
    status       ENUM('pending','active','ended','declined','missed') NOT NULL DEFAULT 'pending'
                 COMMENT 'pending=waiting for admin, active=in progress, ended/declined/missed=finished',
    accepted_by  INT          DEFAULT NULL COMMENT 'users.id of the admin who accepted this call',
    -- WebRTC signaling fields (offer and answer exchanged via polling)
    offer_sdp    MEDIUMTEXT   DEFAULT NULL COMMENT 'WebRTC SDP offer from caller',
    answer_sdp   MEDIUMTEXT   DEFAULT NULL COMMENT 'WebRTC SDP answer from admin',
    -- ICE candidates stored as JSON arrays
    caller_ice   MEDIUMTEXT   DEFAULT NULL COMMENT 'JSON array of ICE candidates from caller',
    admin_ice    MEDIUMTEXT   DEFAULT NULL COMMENT 'JSON array of ICE candidates from admin',
    created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    FOREIGN KEY (accepted_by) REFERENCES users(id)     ON DELETE SET NULL,
    INDEX idx_status     (status),
    INDEX idx_token      (session_token),
    INDEX idx_customer   (customer_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='WebRTC call sessions between customers and admin';
