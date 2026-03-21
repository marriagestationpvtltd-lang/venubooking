-- Migration: Add custom venue support to bookings table
-- Allows customers to provide their own venue details instead of selecting from the listed venues.
-- This makes hall_id nullable and adds custom_venue_name / custom_hall_name columns.

-- Step 1: Drop the NOT NULL constraint and foreign key on hall_id
--   (MySQL requires dropping the FK before modifying the column)
ALTER TABLE bookings
    DROP FOREIGN KEY bookings_ibfk_2;

-- Step 2: Make hall_id nullable
ALTER TABLE bookings
    MODIFY COLUMN hall_id INT DEFAULT NULL;

-- Step 3: Re-add the foreign key as nullable (ON DELETE SET NULL so deleting a hall
--         does not cascade-delete the booking)
ALTER TABLE bookings
    ADD CONSTRAINT fk_bookings_hall_id
        FOREIGN KEY (hall_id) REFERENCES halls(id) ON DELETE SET NULL;

-- Step 4: Add custom venue columns
ALTER TABLE bookings
    ADD COLUMN custom_venue_name VARCHAR(255) DEFAULT NULL
        COMMENT 'Venue name when customer brings own venue (hall_id is NULL)'
        AFTER hall_id,
    ADD COLUMN custom_hall_name VARCHAR(255) DEFAULT NULL
        COMMENT 'Hall/location name when customer brings own venue (hall_id is NULL)'
        AFTER custom_venue_name;

-- Step 5: Add allow_custom_venue setting (default enabled)
INSERT INTO settings (setting_key, setting_value, setting_type)
VALUES ('allow_custom_venue', '1', 'boolean')
ON DUPLICATE KEY UPDATE setting_value = setting_value;
