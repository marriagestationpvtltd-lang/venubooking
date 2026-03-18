<?php
/**
 * AJAX Bulk Delete Handler for Gallery Images
 * Deletes multiple images at once via checkboxes
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

// Set JSON response headers
header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please log in.']);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

// Verify CSRF token
if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token.']);
    exit;
}

// Get image IDs to delete
$image_ids_raw = $_POST['image_ids'] ?? '';
if (empty($image_ids_raw)) {
    echo json_encode(['success' => false, 'message' => 'No images selected.']);
    exit;
}

// Parse and validate image IDs (expecting comma-separated values)
$image_ids = array_filter(array_map('intval', explode(',', $image_ids_raw)));
if (empty($image_ids)) {
    echo json_encode(['success' => false, 'message' => 'Invalid image IDs.']);
    exit;
}

$current_user = getCurrentUser();
$db = getDB();

try {
    // Start transaction for atomic deletion
    $db->beginTransaction();
    
    // Fetch all image paths before deletion
    $placeholders = implode(',', array_fill(0, count($image_ids), '?'));
    $stmt = $db->prepare("SELECT id, image_path, title FROM site_images WHERE id IN ($placeholders)");
    $stmt->execute($image_ids);
    $images = $stmt->fetchAll();
    
    if (empty($images)) {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => 'No images found.']);
        exit;
    }
    
    $deleted_count = 0;
    $failed_count = 0;
    $deleted_files = [];
    
    foreach ($images as $image) {
        // Delete from database
        $delete_stmt = $db->prepare("DELETE FROM site_images WHERE id = ?");
        if ($delete_stmt->execute([$image['id']])) {
            // Delete physical file
            $file_path = UPLOAD_PATH . $image['image_path'];
            if (file_exists($file_path)) {
                if (unlink($file_path)) {
                    $deleted_files[] = $image['image_path'];
                }
            }
            
            // Log activity for each deletion
            logActivity(
                $current_user['id'],
                'Bulk deleted image',
                'site_images',
                $image['id'],
                "Deleted image: " . $image['title'] . " (" . $image['image_path'] . ")"
            );
            
            $deleted_count++;
        } else {
            $failed_count++;
        }
    }
    
    $db->commit();
    
    // Prepare response message
    $message = $deleted_count . ' image' . ($deleted_count > 1 ? 's' : '') . ' deleted successfully';
    if ($failed_count > 0) {
        $message .= ', ' . $failed_count . ' failed';
    }
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'deleted_count' => $deleted_count,
        'failed_count' => $failed_count,
        'deleted_ids' => array_column($images, 'id')
    ]);
    
} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
exit;
