-- ============================================================================
-- Migration: Add login_attempts table for persistent brute-force protection
-- ============================================================================
-- Replaces the previous session-based login lockout with a database-backed
-- mechanism that survives server restarts and works correctly across multiple
-- PHP processes / load-balanced nodes.
--
-- Each row records a single login attempt (success or failure) from an IP.
-- The application queries this table to count recent failures and enforce
-- the LOGIN_MAX_ATTEMPTS / LOGIN_LOCKOUT_SECONDS thresholds.
--
-- Old data is automatically pruned by the scheduled DELETE below (or can be
-- cleaned up periodically via a cron job / MySQL Event).
-- ============================================================================

CREATE TABLE IF NOT EXISTS login_attempts (
    id          BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    ip_address  VARCHAR(45)      NOT NULL COMMENT 'IPv4 or IPv6 address of the client',
    success     TINYINT(1)       NOT NULL DEFAULT 0 COMMENT '0 = failed, 1 = successful login',
    attempted_at TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_ip_attempted (ip_address, attempted_at),
    INDEX idx_attempted_at (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='Tracks login attempts for IP-based brute-force protection';

-- Optional: clean up records older than 24 hours (run as a cron / MySQL Event)
-- DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 24 HOUR);
