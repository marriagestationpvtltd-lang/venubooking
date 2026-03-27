-- Migration: add youtube_url column to service_packages
-- Run once on existing installations that do not yet have this column.
-- NOTE: Run widen_youtube_url_column_to_text.sql afterwards to expand the column to TEXT
--       so it can hold the full YouTube embed code (<iframe>…</iframe>).

ALTER TABLE service_packages
    ADD COLUMN IF NOT EXISTS youtube_url VARCHAR(500) DEFAULT NULL
        COMMENT 'YouTube embed code or plain URL to embed a video on the package detail page';
