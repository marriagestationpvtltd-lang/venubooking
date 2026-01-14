<?php
/**
 * API Endpoint: Get Images by Section
 * Returns active images for a specific section
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

// Get section parameter
$section = isset($_GET['section']) ? trim($_GET['section']) : '';

if (empty($section)) {
    echo json_encode([
        'success' => false,
        'message' => 'Section parameter is required'
    ]);
    exit;
}

try {
    $db = getDB();
    
    // Fetch active images for the section
    $sql = "SELECT id, title, description, image_path, section, display_order 
            FROM site_images 
            WHERE section = ? AND status = 'active' 
            ORDER BY display_order, created_at DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$section]);
    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Build full URLs for images
    foreach ($images as &$image) {
        $image['image_url'] = UPLOAD_URL . $image['image_path'];
        $image['file_exists'] = file_exists(UPLOAD_PATH . $image['image_path']);
    }
    
    echo json_encode([
        'success' => true,
        'section' => $section,
        'count' => count($images),
        'images' => $images
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching images: ' . $e->getMessage()
    ]);
}
