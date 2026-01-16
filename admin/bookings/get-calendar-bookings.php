<?php
/**
 * API endpoint to get bookings for calendar view
 * Returns events in FullCalendar format
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requireLogin();

// Status color mapping for calendar events
define('STATUS_COLORS', [
    'confirmed' => '#28a745',
    'pending' => '#ffc107',
    'cancelled' => '#dc3545',
    'completed' => '#007bff',
    'payment_submitted' => '#17a2b8'
]);
define('DEFAULT_STATUS_COLOR', '#6c757d');

try {
    $db = getDB();
    
    $start = $_GET['start'] ?? null;
    $end = $_GET['end'] ?? null;
    
    if (!$start || !$end) {
        echo json_encode([
            'success' => false,
            'message' => 'Start and end dates are required'
        ]);
        exit;
    }
    
    // Validate date format (YYYY-MM-DD)
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid date format'
        ]);
        exit;
    }
    
    // Fetch bookings within the date range
    $stmt = $db->prepare("SELECT b.id, b.booking_number, b.event_date, b.shift, 
                          b.event_type, b.booking_status,
                          c.full_name as customer_name,
                          h.name as hall_name,
                          v.name as venue_name
                          FROM bookings b
                          INNER JOIN customers c ON b.customer_id = c.id
                          INNER JOIN halls h ON b.hall_id = h.id
                          INNER JOIN venues v ON h.venue_id = v.id
                          WHERE b.event_date >= ? AND b.event_date <= ?
                          ORDER BY b.event_date, b.shift");
    
    $stmt->execute([$start, $end]);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format events for FullCalendar
    $events = [];
    
    foreach ($bookings as $booking) {
        $color = STATUS_COLORS[$booking['booking_status']] ?? DEFAULT_STATUS_COLOR;
        
        $events[] = [
            'id' => $booking['id'],
            'title' => $booking['booking_number'] . ' - ' . $booking['shift'],
            'start' => $booking['event_date'],
            'backgroundColor' => $color,
            'borderColor' => $color,
            'extendedProps' => [
                'customer_name' => $booking['customer_name'],
                'venue_name' => $booking['venue_name'],
                'hall_name' => $booking['hall_name'],
                'shift' => $booking['shift'],
                'event_type' => $booking['event_type'],
                'status' => $booking['booking_status']
            ]
        ];
    }
    
    echo json_encode([
        'success' => true,
        'events' => $events
    ]);
    
} catch (Exception $e) {
    error_log("Error in get-calendar-bookings.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error loading bookings: ' . $e->getMessage()
    ]);
}
