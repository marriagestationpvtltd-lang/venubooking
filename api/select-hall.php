<?php
/**
 * API: Select Hall
 * Validates the requested hall against the database before saving to session,
 * ensuring data integrity and preventing foreign-key constraint violations.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['id']) || !is_numeric($input['id'])) {
    echo json_encode(['success' => false, 'message' => 'Hall ID is required']);
    exit;
}

$hall_id = intval($input['id']);

try {
    $db = getDB();

    // Fetch the hall and its venue from the database to ensure the data is
    // authoritative and the hall_id foreign-key constraint will be satisfied.
    $stmt = $db->prepare(
        "SELECT h.id, h.name, h.base_price, h.capacity,
                v.name AS venue_name
         FROM halls h
         JOIN venues v ON v.id = h.venue_id
         WHERE h.id = ? AND h.status = 'active'"
    );
    $stmt->execute([$hall_id]);
    $hall = $stmt->fetch();

    if (!$hall) {
        echo json_encode(['success' => false, 'message' => 'Selected hall is not available. Please choose another hall.']);
        exit;
    }

    $_SESSION['selected_hall'] = [
        'id'         => (int)$hall['id'],
        'name'       => $hall['name'],
        'venue_name' => $hall['venue_name'],
        'base_price' => (float)$hall['base_price'],
        'capacity'   => (int)$hall['capacity'],
    ];

    echo json_encode([
        'success' => true,
        'message' => 'Hall selected successfully'
    ]);

} catch (Exception $e) {
    error_log('select-hall error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error selecting hall. Please try again.'
    ]);
}
