<?php
/**
 * AJAX Image Upload Handler
 * Handles individual image uploads with compression support
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

// Get form data
$title_base = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$section = $_POST['section'] ?? '';
$display_order = intval($_POST['display_order'] ?? 0);
$status = $_POST['status'] ?? 'active';
$event_category = ($section === 'work_photos') ? trim($_POST['event_category'] ?? '') : null;
if ($event_category === '') $event_category = null;

// Validation
if (empty($section)) {
    echo json_encode(['success' => false, 'message' => 'Please select a section.']);
    exit;
}

if ($section === 'work_photos' && empty($event_category)) {
    echo json_encode(['success' => false, 'message' => 'Please select an event category for Our Work photos.']);
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
$max_size = 10 * 1024 * 1024; // 10MB (increased since client compresses)

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
    echo json_encode(['success' => false, 'message' => 'File exceeds 10MB limit.']);
    exit;
}

// Create uploads directory if it doesn't exist
if (!is_dir(UPLOAD_PATH)) {
    if (!mkdir(UPLOAD_PATH, 0755, true)) {
        echo json_encode(['success' => false, 'message' => 'Failed to create upload directory.']);
        exit;
    }
}

// Determine card_id for grouping
$max_card_stmt = $db->prepare(
    "SELECT COALESCE(MAX(card_id), 0) FROM site_images WHERE section = ?"
);
$max_card_stmt->execute([$section]);
$max_card_id = (int)$max_card_stmt->fetchColumn();

if ($max_card_id > 0) {
    $count_stmt = $db->prepare(
        "SELECT COUNT(*) FROM site_images WHERE section = ? AND card_id = ?"
    );
    $count_stmt->execute([$section, $max_card_id]);
    $current_card_count = (int)$count_stmt->fetchColumn();
    if ($current_card_count >= 10) {
        $card_id = $max_card_id + 1;
    } else {
        $card_id = $max_card_id;
    }
} else {
    $card_id = 1;
}

// Generate unique filename using validated mime type (never use client-provided extension)
$mime_to_ext = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/gif' => 'gif',
    'image/webp' => 'webp'
];

// Only use extensions from validated mime types
if (!isset($mime_to_ext[$image_info['mime']])) {
    echo json_encode(['success' => false, 'message' => 'Unsupported image format detected.']);
    exit;
}

$extension = $mime_to_ext[$image_info['mime']];
$filename = $section . '_' . time() . '_' . uniqid() . '.' . $extension;
$upload_path = UPLOAD_PATH . $filename;

// Move uploaded file
if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save file.']);
    exit;
}

// Determine title
$file_title = $title_base ?: pathinfo($file['name'], PATHINFO_FILENAME);

// Insert into database
try {
    $sql = "INSERT INTO site_images (title, description, image_path, section, card_id, event_category, display_order, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $db->prepare($sql);
    $result = $stmt->execute([$file_title, $description, $filename, $section, $card_id, $event_category, $display_order, $status]);
    
    if ($result) {
        $image_id = $db->lastInsertId();
        logActivity($current_user['id'], 'Uploaded new image (AJAX)', 'site_images', $image_id, "Uploaded image: $file_title");
        
        echo json_encode([
            'success' => true,
            'message' => 'Image uploaded successfully!',
            'image' => [
                'id' => $image_id,
                'title' => $file_title,
                'filename' => $filename,
                'section' => $section,
                'card_id' => $card_id,
                'url' => UPLOAD_URL . $filename
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
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
