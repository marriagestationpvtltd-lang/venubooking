-- Migration: Replace vendor location (free text) with city_id (FK to cities table)
-- Run this on existing databases that already have the location column.

ALTER TABLE vendors
    ADD COLUMN city_id INT NULL AFTER address,
    ADD CONSTRAINT fk_vendors_city FOREIGN KEY (city_id) REFERENCES cities(id) ON DELETE SET NULL;

ALTER TABLE vendors
    DROP COLUMN location;
