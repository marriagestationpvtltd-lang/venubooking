-- Migration: add youtube_url column to service_packages
-- Run once on existing installations that do not yet have this column.

ALTER TABLE service_packages
    ADD COLUMN IF NOT EXISTS youtube_url VARCHAR(500) DEFAULT NULL
        COMMENT 'Optional YouTube video URL to embed on the package detail page';
