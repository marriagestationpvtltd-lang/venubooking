-- Migration: prevent duplicate vendor assignments for the same booking service
-- Removes any existing duplicates (keeps the one with the lowest id), then adds
-- a unique index on (booking_id, booking_service_id, vendor_id).

-- Step 1: delete duplicate rows, keeping only the earliest assignment per combination
DELETE bva1
FROM booking_vendor_assignments bva1
INNER JOIN booking_vendor_assignments bva2
    ON  bva1.booking_id         = bva2.booking_id
    AND bva1.booking_service_id = bva2.booking_service_id
    AND bva1.vendor_id          = bva2.vendor_id
    AND bva1.id                 > bva2.id
WHERE bva1.booking_service_id IS NOT NULL;

-- Step 2: add unique index (only covers rows where booking_service_id is not null,
--         because NULL != NULL in SQL so a plain UNIQUE already allows multiple NULLs)
ALTER TABLE booking_vendor_assignments
    ADD UNIQUE INDEX uq_vendor_booking_service (booking_id, booking_service_id, vendor_id);
