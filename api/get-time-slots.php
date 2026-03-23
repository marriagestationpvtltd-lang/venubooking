<?php
/**
 * API: Get Available Time Slots for a Hall on a Date
 *
 * GET params:
 *   hall_id  – integer
 *   date     – YYYY-MM-DD
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/functions.php';

// Validate inputs
if (!isset($_GET['hall_id']) || !is_numeric($_GET['hall_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing or invalid hall_id']);
    exit;
}

$hall_id = intval($_GET['hall_id']);

$date = trim($_GET['date'] ?? '');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !checkdate(
    (int)substr($date, 5, 2),
    (int)substr($date, 8, 2),
    (int)substr($date, 0, 4)
)) {
    echo json_encode(['success' => false, 'message' => 'Invalid or missing date (expected YYYY-MM-DD)']);
    exit;
}

if ($hall_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid hall_id']);
    exit;
}

try {
    $slots = getAvailableTimeSlotsForHall($hall_id, $date);

    // Format times for display
    foreach ($slots as &$slot) {
        $start_ts = strtotime($slot['start_time']);
        $end_ts   = strtotime($slot['end_time']);
        $slot['start_time_display'] = $start_ts !== false ? date('h:i A', $start_ts) : $slot['start_time'];
        $slot['end_time_display']   = $end_ts   !== false ? date('h:i A', $end_ts)   : $slot['end_time'];
        $slot['id']                 = (int)$slot['id'];
        $slot['hall_id']            = (int)$slot['hall_id'];
        if ($slot['price_override'] !== null) {
            $slot['price_override'] = (float)$slot['price_override'];
        }
    }
    unset($slot);

    echo json_encode([
        'success' => true,
        'slots'   => $slots,
    ]);
} catch (Exception $e) {
    error_log('get-time-slots error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error fetching time slots. Please try again.']);
}
