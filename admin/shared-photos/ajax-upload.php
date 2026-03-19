<?php
/**
 * AJAX Shared File Upload Handler
 * Handles individual file uploads for sharing (photos, videos, ZIPs, PDFs, etc.)
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
$title = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$expires_in = intval($_POST['expires_in'] ?? 0);
$max_downloads = !empty($_POST['max_downloads']) ? intval($_POST['max_downloads']) : null;

// Validation
if (empty($title)) {
    echo json_encode(['success' => false, 'message' => 'Please enter a title for the photo.']);
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
$allowed_photo_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$allowed_video_types = ['video/mp4', 'video/quicktime', 'video/x-msvideo', 'video/webm', 'video/x-matroska', 'video/mpeg', 'video/3gpp'];

// Dangerous extensions that must never be uploaded
$blocked_extensions = [
    'php', 'php3', 'php4', 'php5', 'php7', 'phtml', 'phar',
    'asp', 'aspx', 'ashx', 'asmx',
    'jsp', 'jspx',
    'cgi', 'pl', 'py', 'rb',
    'sh', 'bash', 'bat', 'cmd', 'ps1',
    'exe', 'dll', 'com', 'msi', 'vbs', 'js', 'jar',
    'htaccess', 'htpasswd',
];

$max_size = 2 * 1024 * 1024 * 1024; // 2GB for any file type

$file_ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$is_photo  = in_array($file['type'], $allowed_photo_types);
$is_video  = in_array($file['type'], $allowed_video_types);

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

// Validate file type — block dangerous extensions regardless of MIME type
if (in_array($file_ext, $blocked_extensions)) {
    echo json_encode(['success' => false, 'message' => 'This file type is not allowed for security reasons.']);
    exit;
}

// Validate file size
if ($file['size'] > $max_size) {
    echo json_encode(['success' => false, 'message' => 'File exceeds 2GB limit.']);
    exit;
}

// For photos, validate actual image content
if ($is_photo) {
    $image_info = getimagesize($file['tmp_name']);
    if ($image_info === false) {
        echo json_encode(['success' => false, 'message' => 'Invalid image file.']);
        exit;
    }
}

// Create uploads directory if it doesn't exist
if (!is_dir(UPLOAD_PATH)) {
    if (!mkdir(UPLOAD_PATH, 0755, true)) {
        echo json_encode(['success' => false, 'message' => 'Failed to create upload directory.']);
        exit;
    }
}

// Generate unique filename
if ($is_photo && isset($image_info)) {
    $mime_to_ext = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    ];
    if (!isset($mime_to_ext[$image_info['mime']])) {
        echo json_encode(['success' => false, 'message' => 'Unsupported image format detected.']);
        exit;
    }
    $extension = $mime_to_ext[$image_info['mime']];
} else {
    $extension = $file_ext ?: 'bin';
}

$filename = 'shared_' . time() . '_' . uniqid() . '.' . $extension;
$upload_path = UPLOAD_PATH . $filename;

// Move uploaded file
if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save file.']);
    exit;
}

// Generate unique download token (64 characters, URL-safe)
$download_token = bin2hex(random_bytes(32));

// Calculate expiration date if set
$expires_at = null;
if ($expires_in > 0) {
    $expires_at = date('Y-m-d H:i:s', strtotime("+{$expires_in} days"));
}

// Insert into database
try {
    $sql = "INSERT INTO shared_photos (title, description, image_path, download_token, max_downloads, expires_at, status, created_by) 
            VALUES (?, ?, ?, ?, ?, ?, 'active', ?)";
    $stmt = $db->prepare($sql);
    $result = $stmt->execute([
        $title, 
        $description, 
        $filename, 
        $download_token, 
        $max_downloads, 
        $expires_at, 
        $current_user['id']
    ]);
    
    if ($result) {
        $photo_id = $db->lastInsertId();
        $download_url = BASE_URL . '/download.php?token=' . urlencode($download_token);
        
        logActivity($current_user['id'], 'Uploaded shared file', 'shared_photos', $photo_id, "Uploaded file for sharing: $title");
        
        echo json_encode([
            'success' => true,
            'message' => 'File uploaded successfully! Download link generated.',
            'image' => [
                'id' => $photo_id,
                'title' => $title,
                'filename' => $filename,
                'download_token' => $download_token,
                'download_url' => $download_url,
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
    error_log('Shared photo upload error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred. Please try again.']);
}
