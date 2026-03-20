-- ============================================================================
-- MIGRATION: Add Folder Page Banner Ads Settings
-- ============================================================================
-- This migration adds settings for banner advertisements displayed on the 
-- public folder/photo sharing page (folder.php).
-- Banner A appears on the left side, Banner B appears on the right side.
-- ============================================================================

-- Insert banner A settings (left side banner)
INSERT IGNORE INTO settings (setting_key, setting_value, setting_type) VALUES
('folder_banner_a', '', 'image'),
('folder_banner_a_link', '', 'url'),
('folder_banner_a_enabled', '0', 'boolean');

-- Insert banner B settings (right side banner)
INSERT IGNORE INTO settings (setting_key, setting_value, setting_type) VALUES
('folder_banner_b', '', 'image'),
('folder_banner_b_link', '', 'url'),
('folder_banner_b_enabled', '0', 'boolean');

-- ============================================================================
-- HOW TO USE:
-- 1. Run this migration on your existing database:
--    mysql -u username -p database_name < add_folder_banner_ads_settings.sql
--
-- 2. Go to Admin Panel → Settings → Banner Ads tab
--
-- 3. Upload banner images (recommended size: 300×600px vertical banners)
--
-- 4. Optionally add click-through links for each banner
--
-- 5. Enable the banners to display them on the public folder page
-- ============================================================================
