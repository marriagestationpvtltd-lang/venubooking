-- Add comprehensive settings for admin control panel
-- This migration adds all settings needed for complete website control

-- Insert new settings (using INSERT IGNORE to avoid duplicates)
INSERT IGNORE INTO settings (setting_key, setting_value, setting_type) VALUES
-- Basic Website Settings
('site_name', 'Venue Booking System', 'text'),
('site_logo', '', 'file'),
('site_favicon', '', 'file'),
('contact_email', 'info@venubooking.com', 'email'),
('contact_phone', '+977 1234567890', 'text'),
('contact_address', '', 'textarea'),
('currency', 'NPR', 'text'),
('tax_rate', '13', 'number'),
('advance_payment_percentage', '30', 'number'),

-- Frontend Content Settings
('footer_about', 'Your perfect venue for every occasion', 'textarea'),
('footer_copyright', '', 'text'),

-- Booking & System Settings
('booking_min_advance_days', '1', 'number'),
('booking_cancellation_hours', '24', 'number'),
('enable_online_payment', '0', 'boolean'),
('default_booking_status', 'pending', 'select'),

-- SEO & Meta Settings
('meta_title', 'Venue Booking System - Book Your Perfect Event Venue', 'text'),
('meta_description', 'Find and book the ideal venue for your wedding, birthday party, corporate event, or any special occasion.', 'textarea'),
('meta_keywords', 'venue booking, event venue, wedding hall, party hall, corporate events', 'textarea'),

-- Social Media Links
('social_facebook', '', 'url'),
('social_instagram', '', 'url'),
('social_tiktok', '', 'url'),
('social_twitter', '', 'url'),
('social_youtube', '', 'url'),
('social_linkedin', '', 'url'),

-- Contact & Communication
('whatsapp_number', '', 'text'),
('contact_map_url', '', 'url'),
('business_hours', '', 'textarea');
