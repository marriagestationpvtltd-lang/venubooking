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
        
        // Return full image URL with proper URL encoding
        if ($image && !empty($image['image_path'])) {
            // Validate that the image file actually exists
            $image_file_path = UPLOAD_PATH . $image['image_path'];
            if (file_exists($image_file_path) && is_file($image_file_path)) {
                $hall['image'] = $image['image_path'];
                // Properly URL-encode the filename for safe URL construction
                $hall['image_url'] = UPLOAD_URL . rawurlencode($image['image_path']);
            } else {
                // Image record exists but file is missing - use placeholder
                $hall['image'] = null;
                $hall['image_url'] = getPlaceholderImageUrl();
            }
        } else {
            // No image uploaded - use placeholder
            $hall['image'] = null;
            $hall['image_url'] = getPlaceholderImageUrl();
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
