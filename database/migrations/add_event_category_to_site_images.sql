-- Migration: Add event_category column to site_images
-- Used by the "Our Work" section to organise photos into folder-style event categories
-- (e.g. "Wedding Photos", "Bratabandha Photos", "Engagement Photos", "Reception Photos")

ALTER TABLE site_images
    ADD COLUMN event_category VARCHAR(150) DEFAULT NULL
        COMMENT 'Event category folder for work_photos section (e.g. Wedding Photos)'
        AFTER card_id;

ALTER TABLE site_images
    ADD INDEX idx_event_category (event_category);
