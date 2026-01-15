-- Add email notification settings to the settings table
-- Run this migration to add email configuration support

-- Insert email configuration settings
INSERT INTO settings (setting_key, setting_value, setting_type) VALUES
('email_enabled', '1', 'boolean'),
('email_from_name', 'Venue Booking System', 'text'),
('email_from_address', 'noreply@venubooking.com', 'text'),
('admin_email', 'admin@venubooking.com', 'text'),
('smtp_enabled', '0', 'boolean'),
('smtp_host', '', 'text'),
('smtp_port', '587', 'number'),
('smtp_username', '', 'text'),
('smtp_password', '', 'password'),
('smtp_encryption', 'tls', 'text')
ON DUPLICATE KEY UPDATE setting_key = setting_key;
