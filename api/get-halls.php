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
        
        // Get all images for this hall (for carousel)
        $db = getDB();
        $img_stmt = $db->prepare("SELECT image_path, is_primary FROM hall_images WHERE hall_id = ? ORDER BY is_primary DESC, display_order ASC");
        $img_stmt->execute([$hall['id']]);
        $hall_images = $img_stmt->fetchAll();

        $hall['image_urls'] = [];
        foreach ($hall_images as $hi) {
            if (!empty($hi['image_path'])) {
                $hall['image_urls'][] = UPLOAD_URL . $hi['image_path'];
            }
        }

        // Backward-compatible single image_url (primary or first)
        if (!empty($hall['image_urls'])) {
            $hall['image_url'] = $hall['image_urls'][0];
            $hall['image'] = $hall_images[0]['image_path'];
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
