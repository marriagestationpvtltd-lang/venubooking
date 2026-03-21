<?php
/**
 * API: Set Custom Venue
 * Saves manually-entered venue/hall details to the booking session
 * when the customer is providing their own venue.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Check that the custom venue feature is enabled
$allow_custom_venue = getSetting('allow_custom_venue', '1');
if ($allow_custom_venue !== '1') {
    echo json_encode(['success' => false, 'message' => 'Custom venue entry is not enabled.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$venue_name = trim($input['venue_name'] ?? '');
$hall_name  = trim($input['hall_name']  ?? '');

if (empty($venue_name)) {
    echo json_encode(['success' => false, 'message' => 'Venue name is required.']);
    exit;
}

try {
    $_SESSION['selected_hall'] = [
        'id'          => 0,
        'is_custom'   => true,
        'name'        => $hall_name ?: $venue_name,
        'venue_name'  => $venue_name,
        'base_price'  => 0,
        'capacity'    => 0,
        'custom_venue_name' => $venue_name,
        'custom_hall_name'  => $hall_name,
    ];

    echo json_encode([
        'success' => true,
        'message' => 'Custom venue saved successfully'
    ]);

} catch (Exception $e) {
    error_log('set-custom-venue error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error saving venue. Please try again.'
    ]);
}
