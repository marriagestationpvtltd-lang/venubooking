-- Migration: Add start_time and end_time to bookings table
-- Allows capturing the exact start and end time of each event booking.
-- Both columns are nullable and default based on the shift value.
-- ============================================================================

ALTER TABLE bookings
    ADD COLUMN start_time TIME DEFAULT NULL AFTER event_date,
    ADD COLUMN end_time   TIME DEFAULT NULL AFTER start_time;

-- Back-fill times for any existing bookings based on their shift value
UPDATE bookings SET start_time = '06:00:00', end_time = '12:00:00' WHERE shift = 'morning'   AND start_time IS NULL;
UPDATE bookings SET start_time = '12:00:00', end_time = '18:00:00' WHERE shift = 'afternoon' AND start_time IS NULL;
UPDATE bookings SET start_time = '18:00:00', end_time = '23:00:00' WHERE shift = 'evening'   AND start_time IS NULL;
UPDATE bookings SET start_time = '06:00:00', end_time = '23:00:00' WHERE shift = 'fullday'   AND start_time IS NULL;
