-- Migration: add photo column to menu_groups
-- Allows each group within a custom menu section to have an optional thumbnail photo.

ALTER TABLE menu_groups
    ADD COLUMN IF NOT EXISTS photo VARCHAR(255) DEFAULT NULL AFTER group_name;
