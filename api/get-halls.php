<?php
/**
 * API: Get Halls for Venue
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_GET['venue_id']) || !isset($_GET['guests'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$venue_id = intval($_GET['venue_id']);
$guests = intval($_GET['guests']);
$date = $_GET['date'] ?? '';
$shift = $_GET['shift'] ?? '';

try {
    $halls = getHallsForVenue($venue_id, $guests);
    
    // Check availability for each hall
    foreach ($halls as &$hall) {
        $hall['available'] = checkHallAvailability($hall['id'], $date, $shift);
        
        // Get primary image
        $db = getDB();
        $stmt = $db->prepare("SELECT image_path FROM hall_images WHERE hall_id = ? AND is_primary = 1 LIMIT 1");
        $stmt->execute([$hall['id']]);
        $image = $stmt->fetch();
        
        // Return full image URL for consistency with other APIs
        if ($image && $image['image_path']) {
            $hall['image'] = $image['image_path'];
            $hall['image_url'] = UPLOAD_URL . $image['image_path'];
        } else {
            $hall['image'] = null;
            $hall['image_url'] = null;
        }
    }
    
    echo json_encode([
        'success' => true,
        'halls' => $halls
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error loading halls: ' . $e->getMessage()
    ]);
}
