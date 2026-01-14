<?php
/**
 * API: Calculate Price
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['hall_id']) || !isset($input['guests'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$hall_id = intval($input['hall_id']);
$guests = intval($input['guests']);
$menus = $input['menus'] ?? [];
$services = $input['services'] ?? [];

try {
    $totals = calculateBookingTotal($hall_id, $menus, $guests, $services);
    
    echo json_encode([
        'success' => true,
        'totals' => $totals
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error calculating price: ' . $e->getMessage()
    ]);
}
