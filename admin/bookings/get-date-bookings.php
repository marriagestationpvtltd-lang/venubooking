<?php
/**
 * API endpoint to get detailed bookings for a specific date
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();

try {
    $db = getDB();
    
    $date = $_GET['date'] ?? null;
    
    if (!$date) {
        echo json_encode([
            'success' => false,
            'message' => 'Date is required'
        ]);
        exit;
    }
    
    // Fetch bookings for the specific date with all details
    $stmt = $db->prepare("SELECT b.*, 
                          c.full_name as customer_name,
                          c.phone as customer_phone,
                          c.email as customer_email,
                          h.name as hall_name,
                          v.name as venue_name
                          FROM bookings b
                          INNER JOIN customers c ON b.customer_id = c.id
                          INNER JOIN halls h ON b.hall_id = h.id
                          INNER JOIN venues v ON h.venue_id = v.id
                          WHERE b.event_date = ?
                          ORDER BY 
                            CASE b.shift
                                WHEN 'morning' THEN 1
                                WHEN 'afternoon' THEN 2
                                WHEN 'evening' THEN 3
                                WHEN 'fullday' THEN 4
                            END");
    
    $stmt->execute([$date]);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch menu items for each booking
    foreach ($bookings as &$booking) {
        // Get menu/package details
        $menuStmt = $db->prepare("SELECT m.name, bm.number_of_guests, bm.price_per_person, bm.total_price
                                  FROM booking_menus bm
                                  INNER JOIN menus m ON bm.menu_id = m.id
                                  WHERE bm.booking_id = ?");
        $menuStmt->execute([$booking['id']]);
        $menus = $menuStmt->fetchAll(PDO::FETCH_ASSOC);
        $booking['packages'] = array_column($menus, 'name');
        $booking['menu_details'] = $menus;
        
        // Get service details
        $serviceStmt = $db->prepare("SELECT service_name, price
                                     FROM booking_services
                                     WHERE booking_id = ?");
        $serviceStmt->execute([$booking['id']]);
        $services = $serviceStmt->fetchAll(PDO::FETCH_ASSOC);
        $booking['services'] = array_column($services, 'service_name');
        $booking['service_details'] = $services;
        
        // Format currency
        $booking['grand_total_formatted'] = formatCurrency($booking['grand_total']);
        $booking['hall_price_formatted'] = formatCurrency($booking['hall_price']);
    }
    
    echo json_encode([
        'success' => true,
        'bookings' => $bookings,
        'count' => count($bookings)
    ]);
    
} catch (Exception $e) {
    error_log("Error in get-date-bookings.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error loading bookings: ' . $e->getMessage()
    ]);
}
