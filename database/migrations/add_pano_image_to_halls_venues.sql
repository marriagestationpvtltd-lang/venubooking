-- Migration: Add 360° panoramic image support to halls and venues
-- Run this script once to add the pano_image column to both tables.
-- SAFE TO RE-RUN: uses ADD COLUMN ... IF NOT EXISTS syntax.

ALTER TABLE halls
    ADD COLUMN IF NOT EXISTS pano_image VARCHAR(255) DEFAULT NULL
        COMMENT '360° equirectangular panoramic image filename (stored in uploads/)';

ALTER TABLE venues
    ADD COLUMN IF NOT EXISTS pano_image VARCHAR(255) DEFAULT NULL
        COMMENT '360° equirectangular panoramic image filename (stored in uploads/)';
