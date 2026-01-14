<?php
/**
 * API: Select Hall
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['id'])) {
    echo json_encode(['success' => false, 'message' => 'Hall ID is required']);
    exit;
}

try {
    $_SESSION['selected_hall'] = [
        'id' => $input['id'],
        'name' => $input['name'],
        'venue_name' => $input['venue_name'],
        'base_price' => $input['base_price'],
        'capacity' => $input['capacity']
    ];
    
    echo json_encode([
        'success' => true,
        'message' => 'Hall selected successfully'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error selecting hall: ' . $e->getMessage()
    ]);
}
