-- Migration: Fix settings page data-save issue
-- ============================================================================
-- Adds settings rows that were missing from earlier database setups.
-- These keys are referenced by admin/settings/index.php but were not included
-- in the original INSERT IGNORE block, causing:
--   1. The Booking tab fields (min advance days, cancellation hours, etc.) to
--      silently insert as new rows on first save — safe, but confusing if the
--      DB has strict write restrictions.
--   2. google_analytics_id to be absent, so the header.php getSetting() call
--      always returned the empty default from the .env fallback map.
--
-- Safe to run multiple times (INSERT IGNORE skips existing rows).
-- ============================================================================

INSERT IGNORE INTO settings (setting_key, setting_value, setting_type) VALUES
('booking_min_advance_days',  '1',       'number'),
('booking_cancellation_hours', '24',     'number'),
('default_booking_status',    'pending', 'text'),
('enable_online_payment',     '0',       'boolean'),
('google_analytics_id',       '',        'text');

SELECT 'fix_settings_missing_keys: complete' AS migration_status;
