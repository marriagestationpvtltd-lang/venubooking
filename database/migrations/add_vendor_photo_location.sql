-- Migration: Add photo and location columns to vendors table
-- This enables storing a vendor profile photo and specific location for each vendor.

ALTER TABLE vendors
    ADD COLUMN location VARCHAR(255) DEFAULT NULL AFTER address,
    ADD COLUMN photo VARCHAR(255) DEFAULT NULL AFTER location;
