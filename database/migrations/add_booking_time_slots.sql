-- ============================================================================
-- Migration: Add booking_time_slots junction table
--
-- This table links a booking to the individual hall_time_slots that the
-- customer selected.  It enables:
--   • Multiple time-slot selection per booking
--   • Precise per-slot availability checking (instead of aggregate time-range)
--
-- The bookings.start_time / end_time columns continue to store the aggregate
-- (earliest start → latest end) for display and legacy compatibility.
--
-- Run once on existing installations.  Safe to run multiple times (IF NOT EXISTS).
-- ============================================================================

CREATE TABLE IF NOT EXISTS booking_time_slots (
    id                INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    booking_id        INT NOT NULL,
    hall_time_slot_id INT NOT NULL,
    CONSTRAINT fk_bts_booking   FOREIGN KEY (booking_id)        REFERENCES bookings(id)        ON DELETE CASCADE,
    CONSTRAINT fk_bts_hall_slot FOREIGN KEY (hall_time_slot_id) REFERENCES hall_time_slots(id) ON DELETE CASCADE,
    INDEX idx_bts_booking   (booking_id),
    INDEX idx_bts_hall_slot (hall_time_slot_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
