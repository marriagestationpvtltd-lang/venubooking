-- Add invoice content settings for bill printing
-- These settings allow customization of invoice title, cancellation policy, and disclaimer

-- Add invoice title
INSERT INTO settings (setting_key, setting_value, setting_type) 
VALUES ('invoice_title', 'Wedding Booking Confirmation & Partial Payment Receipt', 'text')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

-- Add cancellation policy
INSERT INTO settings (setting_key, setting_value, setting_type) 
VALUES ('cancellation_policy', 'Advance payment is non-refundable in case of cancellation.
Full payment must be completed 7 days before the event date.
Cancellations made 30 days before the event will receive 50% refund of total amount (excluding advance).
Cancellations made less than 30 days before the event are non-refundable.
Date changes are subject to availability and must be requested at least 15 days in advance.', 'textarea')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

-- Add invoice disclaimer
INSERT INTO settings (setting_key, setting_value, setting_type) 
VALUES ('invoice_disclaimer', 'Note: This is a computer-generated estimate bill. Please create a complete invoice yourself.', 'textarea')
ON DUPLICATE KEY UPDATE setting_key = setting_key;
