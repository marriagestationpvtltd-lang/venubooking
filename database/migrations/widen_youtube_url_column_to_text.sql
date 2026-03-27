-- Migration: widen youtube_url column in service_packages to TEXT
-- Required because the field now stores the full YouTube embed code (<iframe>…</iframe>)
-- which can exceed the previous VARCHAR(500) limit.
-- Note: MODIFY COLUMN has no IF NOT EXISTS guard; running this on an already-TEXT
-- column is harmless but will briefly lock the table, so run it once during
-- a maintenance window on production.

ALTER TABLE service_packages
    MODIFY COLUMN youtube_url TEXT DEFAULT NULL
        COMMENT 'YouTube embed code (<iframe>…</iframe>) or plain YouTube URL to display on the package detail page';
