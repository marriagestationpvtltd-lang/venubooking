-- Add company-specific settings for invoice/bill printing
-- These settings are used in the print invoice layout

-- Add company name (defaults to site_name if not set)
INSERT INTO settings (setting_key, setting_value, setting_type) 
VALUES ('company_name', '', 'text')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

-- Add company address
INSERT INTO settings (setting_key, setting_value, setting_type) 
VALUES ('company_address', '', 'text')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

-- Add company phone
INSERT INTO settings (setting_key, setting_value, setting_type) 
VALUES ('company_phone', '', 'text')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

-- Add company email
INSERT INTO settings (setting_key, setting_value, setting_type) 
VALUES ('company_email', '', 'text')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

-- Add company logo (separate from site logo, specifically for invoices/bills)
INSERT INTO settings (setting_key, setting_value, setting_type) 
VALUES ('company_logo', '', 'text')
ON DUPLICATE KEY UPDATE setting_key = setting_key;
