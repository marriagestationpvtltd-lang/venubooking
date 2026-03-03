-- Migration: Add vendors and booking_vendor_assignments tables
-- This enables assigning different vendors (Pandit, Photographer, Videographer, Baje, etc.)
-- to specific tasks within a booking package.

-- Table: vendors
CREATE TABLE IF NOT EXISTS vendors (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    type VARCHAR(100) NOT NULL DEFAULT 'other',
    phone VARCHAR(20),
    email VARCHAR(100),
    address TEXT,
    location VARCHAR(255) DEFAULT NULL,
    photo VARCHAR(255) DEFAULT NULL,
    notes TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_type (type),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: booking_vendor_assignments
CREATE TABLE IF NOT EXISTS booking_vendor_assignments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    booking_id INT NOT NULL,
    vendor_id INT NOT NULL,
    task_description VARCHAR(255) NOT NULL COMMENT 'What the vendor will do for this booking',
    assigned_amount DECIMAL(10, 2) DEFAULT 0.00 COMMENT 'Amount to be paid to vendor',
    notes TEXT,
    status ENUM('assigned', 'confirmed', 'completed', 'cancelled') DEFAULT 'assigned',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE RESTRICT,
    INDEX idx_booking_id (booking_id),
    INDEX idx_vendor_id (vendor_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
