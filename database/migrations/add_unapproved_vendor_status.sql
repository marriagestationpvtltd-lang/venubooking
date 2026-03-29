-- Migration: Add 'unapproved' status to vendors table
-- Manually added vendors with incomplete information are stored as 'unapproved'
-- so they can be tracked separately and shown with an unverified label in
-- vendor assignment dropdowns.

ALTER TABLE vendors
    MODIFY COLUMN status ENUM('active', 'inactive', 'unapproved') DEFAULT 'active';
