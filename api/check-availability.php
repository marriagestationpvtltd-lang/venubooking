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
$date = $_GET['date'];
$shift = $_GET['shift'];

try {
    $available = checkHallAvailability($hall_id, $date, $shift);
    
    echo json_encode([
        'success' => true,
        'available' => $available
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error checking availability: ' . $e->getMessage()
    ]);
}
