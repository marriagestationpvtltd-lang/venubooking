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

try {
    $halls = getHallsForVenue($venue_id, $guests);
    
    foreach ($halls as &$hall) {
        // Check if hall has any active time slots defined; if so it is "schedulable"
        $slots = getHallTimeSlots($hall['id']);
        $hall['has_time_slots'] = !empty($slots);

        // Legacy availability field: a hall is "available" when it has time slots
        // configured (actual per-slot availability is resolved in the time-slot modal).
        // When no slots are defined the hall is still shown but the button will
        // inform the user that no time slots have been set up yet.
        $hall['available'] = $hall['has_time_slots'];
        
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

        // Include 360° panoramic image URL if available
        $pano_image_url = null;
        if (!empty($hall['pano_image'])) {
            $pano_filename = basename($hall['pano_image']);
            if (preg_match(SAFE_FILENAME_PATTERN, $pano_filename) && file_exists(UPLOAD_PATH . $pano_filename)) {
                $pano_image_url = UPLOAD_URL . rawurlencode($pano_filename);
            }
        }
        $hall['pano_image_url'] = $pano_image_url;
    }
    
    echo json_encode([
        'success' => true,
        'halls' => $halls
    ]);
    
} catch (Exception $e) {
    error_log('get-halls error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error loading halls. Please try again.'
    ]);
}
