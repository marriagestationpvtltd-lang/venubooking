-- Migration: Add card_id column to site_images for photo card grouping
-- Each card holds a maximum of 10 photos; photos in the same section share a card_id.
-- Run this migration once against your database.

ALTER TABLE site_images
    ADD COLUMN card_id INT NOT NULL DEFAULT 1 AFTER section,
    ADD INDEX idx_card_id (card_id);

-- Retroactively assign card_id values to existing rows.
-- Photos are numbered in order of (display_order, created_at) within each section;
-- every group of 10 gets the same card number.
--
-- The subquery uses a user-defined variable technique compatible with MySQL 5.x and 8.x.
SET @rn := 0;
SET @prev_section := '';

UPDATE site_images AS t
JOIN (
    SELECT
        id,
        section,
        @rn := IF(@prev_section = section, @rn + 1, 1)           AS row_num,
        @prev_section := section                                   AS _s
    FROM site_images
    ORDER BY section, display_order, created_at
) AS ranked ON t.id = ranked.id
SET t.card_id = CEIL(ranked.row_num / 10);
