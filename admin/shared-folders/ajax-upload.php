<?php
/**
 * AJAX Upload Handler for Folder Photos
 * Handles photo uploads to a specific folder
 * Supports large files (up to 20MB) and bulk uploads
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

$current_user = getCurrentUser();
$db = getDB();

// Check if this is an AJAX upload request
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['ajax_upload'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

// Get folder ID
$folder_id = intval($_POST['folder_id'] ?? 0);
if (!$folder_id) {
    echo json_encode(['success' => false, 'message' => 'Folder ID is required.']);
    exit;
}

// Verify folder exists
$stmt = $db->prepare("SELECT id, folder_name FROM shared_folders WHERE id = ?");
$stmt->execute([$folder_id]);
$folder = $stmt->fetch();

if (!$folder) {
    echo json_encode(['success' => false, 'message' => 'Folder not found.']);
    exit;
}

// Check if file was uploaded
if (!isset($_FILES['images']) || !isset($_FILES['images']['tmp_name'][0]) || empty($_FILES['images']['tmp_name'][0])) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded.']);
    exit;
}

$file = [
    'name' => $_FILES['images']['name'][0],
    'type' => $_FILES['images']['type'][0],
    'tmp_name' => $_FILES['images']['tmp_name'][0],
    'error' => $_FILES['images']['error'][0],
    'size' => $_FILES['images']['size'][0]
];

// Allowed types
$allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$max_size = 20 * 1024 * 1024; // 20MB for large photos

// Validate upload error
if ($file['error'] !== UPLOAD_ERR_OK) {
    $error_messages = [
        UPLOAD_ERR_INI_SIZE => 'File exceeds server upload limit.',
        UPLOAD_ERR_FORM_SIZE => 'File exceeds form upload limit.',
        UPLOAD_ERR_PARTIAL => 'File was only partially uploaded.',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Server missing temporary folder.',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
        UPLOAD_ERR_EXTENSION => 'File upload stopped by extension.'
    ];
    $error_message = $error_messages[$file['error']] ?? 'Unknown upload error.';
    echo json_encode(['success' => false, 'message' => $error_message]);
    exit;
}

// Validate file type
if (!in_array($file['type'], $allowed_types)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, GIF, and WebP are allowed.']);
    exit;
}

// Validate actual image content
$image_info = getimagesize($file['tmp_name']);
if ($image_info === false) {
    echo json_encode(['success' => false, 'message' => 'Invalid image file.']);
    exit;
}

// Validate file size
if ($file['size'] > $max_size) {
    echo json_encode(['success' => false, 'message' => 'File exceeds 20MB limit.']);
    exit;
}

// Create folder-specific upload directory
$folder_upload_dir = UPLOAD_PATH . 'folders/' . $folder_id . '/';
if (!is_dir($folder_upload_dir)) {
    if (!mkdir($folder_upload_dir, 0755, true)) {
        echo json_encode(['success' => false, 'message' => 'Failed to create upload directory.']);
        exit;
    }
}

// Generate unique filename using validated mime type
$mime_to_ext = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/gif' => 'gif',
    'image/webp' => 'webp'
];

if (!isset($mime_to_ext[$image_info['mime']])) {
    echo json_encode(['success' => false, 'message' => 'Unsupported image format detected.']);
    exit;
}

$extension = $mime_to_ext[$image_info['mime']];
$filename = 'photo_' . time() . '_' . uniqid() . '.' . $extension;
$relative_path = 'folders/' . $folder_id . '/' . $filename;
$upload_path = UPLOAD_PATH . $relative_path;

// Move uploaded file
if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save file.']);
    exit;
}

// Generate unique download token for this photo
$download_token = bin2hex(random_bytes(32));

// Generate title from original filename
$original_name = pathinfo($file['name'], PATHINFO_FILENAME);
$title = !empty($original_name) ? $original_name : 'Photo ' . date('Y-m-d H:i:s');

// Insert into database
try {
    $sql = "INSERT INTO shared_photos (folder_id, title, description, image_path, download_token, status, created_by) 
            VALUES (?, ?, '', ?, ?, 'active', ?)";
    $stmt = $db->prepare($sql);
    $result = $stmt->execute([
        $folder_id,
        $title, 
        $relative_path, 
        $download_token, 
        $current_user['id']
    ]);
    
    if ($result) {
        $photo_id = $db->lastInsertId();
        
        logActivity($current_user['id'], 'Uploaded photo to folder', 'shared_photos', $photo_id, "Uploaded to folder: " . $folder['folder_name']);
        
        echo json_encode([
            'success' => true,
            'message' => 'Photo uploaded successfully!',
            'image' => [
                'id' => $photo_id,
                'title' => $title,
                'filename' => $filename,
                'url' => UPLOAD_URL . $relative_path
            ]
        ]);
    } else {
        // Delete the uploaded file on failure
        if (file_exists($upload_path)) {
            unlink($upload_path);
        }
        echo json_encode(['success' => false, 'message' => 'Failed to save to database.']);
    }
} catch (Exception $e) {
    // Delete the uploaded file on error
    if (file_exists($upload_path)) {
        unlink($upload_path);
    }
    error_log('Folder photo upload error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred. Please try again.']);
}
