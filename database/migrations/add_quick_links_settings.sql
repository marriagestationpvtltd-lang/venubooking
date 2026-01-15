-- Migration: Add quick links settings for footer
-- This migration adds settings to manage footer quick links dynamically

-- Insert quick links settings if they don't exist
INSERT INTO settings (setting_key, setting_value, setting_type) 
SELECT 'quick_links', '[]', 'json'
WHERE NOT EXISTS (SELECT 1 FROM settings WHERE setting_key = 'quick_links');

-- Insert default quick links example
-- Format: [{"label": "Home", "url": "/index.php", "order": 1}]
UPDATE settings 
SET setting_value = '[{"label":"Home","url":"/index.php","order":1}]'
WHERE setting_key = 'quick_links' AND (setting_value = '' OR setting_value = '[]');
