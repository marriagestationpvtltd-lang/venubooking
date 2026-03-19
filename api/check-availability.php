<?php
/**
 * API: Check Availability
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_GET['hall_id']) || !isset($_GET['date']) || !isset($_GET['shift'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$hall_id = intval($_GET['hall_id']);

// Validate date format (YYYY-MM-DD)
$date = trim($_GET['date']);
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !checkdate(
    (int)substr($date, 5, 2),
    (int)substr($date, 8, 2),
    (int)substr($date, 0, 4)
)) {
    echo json_encode(['success' => false, 'message' => 'Invalid date format']);
    exit;
}

// Validate shift against allowed values
$allowed_shifts = ['morning', 'afternoon', 'evening', 'fullday'];
$shift = trim($_GET['shift']);
if (!in_array($shift, $allowed_shifts, true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid shift value']);
    exit;
}

if ($hall_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid hall ID']);
    exit;
}

try {
    $available = checkHallAvailability($hall_id, $date, $shift);
    
    echo json_encode([
        'success' => true,
        'available' => $available
    ]);
    
} catch (Exception $e) {
    error_log('check-availability error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error checking availability. Please try again.'
    ]);
}
