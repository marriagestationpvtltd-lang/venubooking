<?php
/**
 * AJAX Bulk Delete Handler for Folder Photos
 * Deletes multiple photos from a folder permanently
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

// Get folder ID and photo IDs
$folder_id = intval($_POST['folder_id'] ?? 0);
$photo_ids_raw = $_POST['photo_ids'] ?? '';

if (!$folder_id) {
    echo json_encode(['success' => false, 'message' => 'Folder ID is required.']);
    exit;
}

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

// Limit batch size to prevent database issues
$max_batch_size = 100;
if (count($photo_ids) > $max_batch_size) {
    $photo_ids = array_slice($photo_ids, 0, $max_batch_size);
}

$current_user = getCurrentUser();
$db = getDB();

try {
    // Start transaction
    $db->beginTransaction();
    
    // Fetch photos that belong to this folder (batch processing)
    $placeholders = implode(',', array_fill(0, count($photo_ids), '?'));
    $params = array_merge($photo_ids, [$folder_id]);
    $stmt = $db->prepare("SELECT id, image_path, thumbnail_path, title FROM shared_photos WHERE id IN ($placeholders) AND folder_id = ?");
    $stmt->execute($params);
    $photos = $stmt->fetchAll();
    
    if (empty($photos)) {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => 'No photos found.']);
        exit;
    }
    
    $deleted_count = 0;
    $deleted_ids = [];
    
    foreach ($photos as $photo) {
        // Delete from database
        $delete_stmt = $db->prepare("DELETE FROM shared_photos WHERE id = ?");
        if ($delete_stmt->execute([$photo['id']])) {
            // Delete physical file
            $file_path = UPLOAD_PATH . $photo['image_path'];
            
            $real_upload_path = realpath(UPLOAD_PATH);
            $real_file_path = realpath($file_path);
            
            if ($real_file_path && $real_upload_path && strpos($real_file_path, $real_upload_path) === 0) {
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
            }

            // Delete thumbnail if it exists
            if (!empty($photo['thumbnail_path'])) {
                $thumb_path = UPLOAD_PATH . $photo['thumbnail_path'];
                $real_thumb_path = realpath($thumb_path);
                if ($real_thumb_path && $real_upload_path && strpos($real_thumb_path, $real_upload_path) === 0) {
                    @unlink($thumb_path);
                }
            }
            
            logActivity(
                $current_user['id'],
                'Deleted photo from folder',
                'shared_photos',
                $photo['id'],
                "Deleted: " . $photo['title']
            );
            
            $deleted_count++;
            $deleted_ids[] = $photo['id'];
        }
    }
    
    $db->commit();
    
    $message = $deleted_count . ' photo' . ($deleted_count !== 1 ? 's' : '') . ' deleted';
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'deleted_count' => $deleted_count,
        'deleted_ids' => $deleted_ids
    ]);
    
} catch (Exception $e) {
    $db->rollBack();
    error_log('Folder photos bulk delete error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred. Please try again.']);
}
exit;
