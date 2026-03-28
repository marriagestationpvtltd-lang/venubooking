-- Migration: Add guest_limit column to service_packages
-- When a package is booked, menu charges apply only to guests exceeding this limit.
-- Guests up to the limit are covered by the package price (no extra menu charge).

ALTER TABLE service_packages
    ADD COLUMN IF NOT EXISTS guest_limit INT NOT NULL DEFAULT 0
        COMMENT 'Max guests included in package price. Extra menu charges apply only above this limit.'
        AFTER price;
