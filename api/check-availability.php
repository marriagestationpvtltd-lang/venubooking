<?php
/**
 * Check Hall Availability API
 * Returns availability status for a hall on a specific date and shift
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Get parameters
$hall_id = $_GET['hall_id'] ?? null;
$date = $_GET['date'] ?? null;
$shift = $_GET['shift'] ?? null;
$exclude_booking_id = $_GET['exclude_booking_id'] ?? null;

// Validate parameters
if (!$hall_id || !$date || !$shift) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required parameters'
    ]);
    exit;
}

// Check availability
$available = checkHallAvailability($hall_id, $date, $shift, $exclude_booking_id);

echo json_encode([
    'success' => true,
    'available' => $available,
    'message' => $available ? 'Hall is available' : 'Hall is already booked'
]);
