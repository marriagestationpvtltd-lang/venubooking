-- Add photo column to menu_group_items table
-- Each individual item within a menu group can have an optional photo
-- displayed as a circular icon in the booking menu selection UI.

ALTER TABLE menu_group_items
    ADD COLUMN IF NOT EXISTS photo VARCHAR(255) DEFAULT NULL AFTER sub_category;
