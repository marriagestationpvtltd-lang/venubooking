<?php
/**
 * API: Get booking counts grouped by date
 *
 * Returns the number of (non-cancelled) bookings for each date within the
 * requested range.  Used by the public date picker to show how many events
 * are already booked on each day.
 *
 * Parameters (GET):
 *   start  - Start date, inclusive (YYYY-MM-DD)
 *   end    - End date, inclusive (YYYY-MM-DD)
 *
 * Response (JSON):
 *   { "success": true, "counts": { "2081-01-15": 3, "2081-01-20": 1, ... } }
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/functions.php';

try {
    $start = isset($_GET['start']) ? trim($_GET['start']) : null;
    $end   = isset($_GET['end'])   ? trim($_GET['end'])   : null;

    if (!$start || !$end) {
        echo json_encode(['success' => false, 'message' => 'start and end parameters are required']);
        exit;
    }

    // Validate date format (YYYY-MM-DD)
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) ||
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
        echo json_encode(['success' => false, 'message' => 'Invalid date format. Use YYYY-MM-DD.']);
        exit;
    }

    // Validate date values
    if (!checkdate((int)substr($start, 5, 2), (int)substr($start, 8, 2), (int)substr($start, 0, 4)) ||
        !checkdate((int)substr($end, 5, 2), (int)substr($end, 8, 2), (int)substr($end, 0, 4))) {
        echo json_encode(['success' => false, 'message' => 'Invalid date value']);
        exit;
    }

    // Limit range to at most 2 years to prevent expensive queries
    define('MAX_DATE_RANGE_DAYS', 730);
    $startTs = strtotime($start);
    $endTs   = strtotime($end);
    if ($endTs < $startTs || ($endTs - $startTs) > MAX_DATE_RANGE_DAYS * 86400) {
        echo json_encode(['success' => false, 'message' => 'Invalid date range']);
        exit;
    }

    $counts = getBookingCountsByDate($start, $end);

    echo json_encode([
        'success' => true,
        'counts'  => $counts
    ]);

} catch (Exception $e) {
    error_log('get-booking-counts error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error loading booking counts']);
}
