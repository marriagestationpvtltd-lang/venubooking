-- Migration: add extra_charge_per_item to menu_groups
-- This column stores the per-item extra charge applied when a customer selects
-- more items from a group than the group's choose_limit allows.
-- Example: choose_limit=3, extra_charge_per_item=100 → selecting 5 items
-- adds 2×100=200 to the booking total.

ALTER TABLE menu_groups
    ADD COLUMN IF NOT EXISTS extra_charge_per_item DECIMAL(10,2) NOT NULL DEFAULT 0.00
        COMMENT 'Charge per item selected beyond choose_limit (0 = no over-limit charge)'
    AFTER choose_limit;
