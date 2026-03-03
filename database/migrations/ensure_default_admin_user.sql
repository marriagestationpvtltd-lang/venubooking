-- Migration: Ensure Default Admin User Exists
-- Run this if the admin user is missing from the database.
--
-- Default credentials:
--   Username: admin
--   Password: Admin@123
--
-- ⚠️ SECURITY WARNING: Change this password immediately after logging in!
-- Update your password in Admin Panel → Change Password

INSERT IGNORE INTO users (username, password, full_name, email, role, status)
VALUES (
    'admin',
    '$2y$10$5sw.gEWePITwobdChuwoRuRT4dtOnxCFf/RMosnL9JVeEeb3teuna',
    'System Administrator',
    'admin@venubooking.com',
    'admin',
    'active'
);
