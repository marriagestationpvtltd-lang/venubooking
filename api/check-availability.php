<?php
/**
 * API: Check Availability
 *
 * Supports two modes:
 *  1. Time-based  – pass start_time and end_time (HH:MM) together with hall_id and date.
 *  2. Shift-based (legacy) – pass shift together with hall_id and date.
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_GET['hall_id']) || !isset($_GET['date'])) {
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

if ($hall_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid hall ID']);
    exit;
}

// Time-based check takes precedence
$start_time = trim($_GET['start_time'] ?? '');
$end_time   = trim($_GET['end_time']   ?? '');

if (!empty($start_time) && !empty($end_time)) {
    if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $start_time) || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $end_time)) {
        echo json_encode(['success' => false, 'message' => 'Invalid time format (expected HH:MM)']);
        exit;
    }
    try {
        $available = checkTimeSlotAvailability($hall_id, $date, $start_time, $end_time);
        echo json_encode(['success' => true, 'available' => $available]);
    } catch (Exception $e) {
        error_log('check-availability error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error checking availability. Please try again.']);
    }
    exit;
}

// Legacy shift-based check
if (!isset($_GET['shift'])) {
    echo json_encode(['success' => false, 'message' => 'Missing shift or start_time/end_time parameters']);
    exit;
}

$allowed_shifts = ['morning', 'afternoon', 'evening', 'fullday'];
$shift = trim($_GET['shift']);
if (!in_array($shift, $allowed_shifts, true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid shift value']);
    exit;
}

try {
    $available = checkHallAvailability($hall_id, $date, $shift);
    echo json_encode(['success' => true, 'available' => $available]);
} catch (Exception $e) {
    error_log('check-availability error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error checking availability. Please try again.']);
}
