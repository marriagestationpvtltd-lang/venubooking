-- Migration: Add short_description column to vendors table
-- This allows admins to write a brief description of what the vendor does.

ALTER TABLE vendors
    ADD COLUMN short_description VARCHAR(500) DEFAULT NULL AFTER type;
