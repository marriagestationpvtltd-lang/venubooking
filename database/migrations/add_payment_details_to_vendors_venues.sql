-- Migration: Add bank_details and qr_code columns to vendors and venues tables
-- These columns allow storing payment information for each vendor/venue provider
-- so that admins can view QR codes and bank details when recording payments.

ALTER TABLE vendors
    ADD COLUMN IF NOT EXISTS bank_details TEXT DEFAULT NULL
        COMMENT 'Bank account details for vendor payment (account name, number, bank name, etc.)'
        AFTER notes,
    ADD COLUMN IF NOT EXISTS qr_code VARCHAR(255) DEFAULT NULL
        COMMENT 'QR code image filename for vendor payment scanning'
        AFTER bank_details;

ALTER TABLE venues
    ADD COLUMN IF NOT EXISTS bank_details TEXT DEFAULT NULL
        COMMENT 'Bank account details for venue provider payment (account name, number, bank name, etc.)'
        AFTER address,
    ADD COLUMN IF NOT EXISTS qr_code VARCHAR(255) DEFAULT NULL
        COMMENT 'QR code image filename for venue provider payment scanning'
        AFTER bank_details;
