<?php
/**
 * AJAX Bulk Delete Handler for Shared Photos
 * Deletes multiple photos permanently (both database and file system)
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

// Get photo IDs to delete
$photo_ids_raw = $_POST['photo_ids'] ?? '';
if (empty($photo_ids_raw)) {
    echo json_encode(['success' => false, 'message' => 'No photos selected.']);
    exit;
}

// Parse and validate photo IDs
$photo_ids = array_filter(array_map('intval', explode(',', $photo_ids_raw)));
if (empty($photo_ids)) {
    echo json_encode(['success' => false, 'message' => 'Invalid photo IDs.']);
    exit;
}

$current_user = getCurrentUser();
$db = getDB();

try {
    // Start transaction for atomic deletion
    $db->beginTransaction();
    
    // Fetch all photo paths before deletion
    $placeholders = implode(',', array_fill(0, count($photo_ids), '?'));
    $stmt = $db->prepare("SELECT id, image_path, title FROM shared_photos WHERE id IN ($placeholders)");
    $stmt->execute($photo_ids);
    $photos = $stmt->fetchAll();
    
    if (empty($photos)) {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => 'No photos found.']);
        exit;
    }
    
    $deleted_count = 0;
    $failed_count = 0;
    $deleted_files = [];
    
    foreach ($photos as $photo) {
        // Delete from database
        $delete_stmt = $db->prepare("DELETE FROM shared_photos WHERE id = ?");
        if ($delete_stmt->execute([$photo['id']])) {
            // Delete physical file completely with path validation
            $file_path = UPLOAD_PATH . $photo['image_path'];
            
            // Security: Ensure file is within uploads directory (prevent path traversal)
            $real_upload_path = realpath(UPLOAD_PATH);
            $real_file_path = realpath($file_path);
            
            if ($real_file_path && $real_upload_path && strpos($real_file_path, $real_upload_path) === 0) {
                if (file_exists($file_path) && unlink($file_path)) {
                    $deleted_files[] = $photo['image_path'];
                }
            }
            
            // Log activity for each deletion
            logActivity(
                $current_user['id'],
                'Permanently deleted shared photo',
                'shared_photos',
                $photo['id'],
                "Permanently deleted: " . $photo['title'] . " (" . $photo['image_path'] . ")"
            );
            
            $deleted_count++;
        } else {
            $failed_count++;
        }
    }
    
    $db->commit();
    
    // Prepare response message (use !== 1 for proper pluralization including 0)
    $message = $deleted_count . ' photo' . ($deleted_count !== 1 ? 's' : '') . ' permanently deleted';
    if ($failed_count > 0) {
        $message .= ', ' . $failed_count . ' failed';
    }
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'deleted_count' => $deleted_count,
        'failed_count' => $failed_count,
        'deleted_ids' => array_column($photos, 'id')
    ]);
    
} catch (Exception $e) {
    $db->rollBack();
    error_log('Shared photos bulk delete error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred. Please try again.']);
}
exit;
