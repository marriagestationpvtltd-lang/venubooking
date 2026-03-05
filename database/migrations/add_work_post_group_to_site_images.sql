-- Migration: Add post_group column to site_images for grouping work photos into posts
-- Work photos (section = 'work_photos') with the same post_group value are displayed
-- together as a single card on the homepage "Our Work" section.

ALTER TABLE site_images
    ADD COLUMN post_group VARCHAR(255) NULL DEFAULT NULL COMMENT 'Groups work_photos into posts; photos sharing the same post_group appear in one card'
    AFTER description;

-- Add an index so lookups by section+post_group are fast
ALTER TABLE site_images
    ADD INDEX idx_post_group (post_group);
