<?php
/**
 * API Endpoint: Get menus assigned to a specific hall
 * Used in admin booking add/edit forms
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

// Validate hall_id parameter
if (!isset($_GET['hall_id']) || empty($_GET['hall_id'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Hall ID is required'
    ]);
    exit;
}

$hall_id = intval($_GET['hall_id']);

if ($hall_id <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid Hall ID'
    ]);
    exit;
}

try {
    // Get menus for this hall
    $menus = getMenusForHall($hall_id);
    
    // Format menus for JSON response
    $formatted_menus = array_map(function($menu) {
        return [
            'id' => intval($menu['id']),
            'name' => $menu['name'],
            'description' => $menu['description'],
            'price_per_person' => floatval($menu['price_per_person']),
            'price_formatted' => formatCurrency($menu['price_per_person'])
        ];
    }, $menus);
    
    echo json_encode([
        'success' => true,
        'menus' => $formatted_menus
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching menus: ' . $e->getMessage()
    ]);
}
