<?php
/**
 * AJAX Upload Handler for Folder Photos and Videos
 * Handles photo uploads (up to 20MB) and video uploads (up to 8GB)
 * Supports bulk uploads for both photos and videos
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

// Handle duplicate check request (lightweight pre-upload check)
if (isset($_POST['ajax_check_duplicate'])) {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        echo json_encode(['exists' => false]);
        exit;
    }

    $check_folder_id = intval($_POST['folder_id'] ?? 0);
    $check_filename  = trim($_POST['filename'] ?? '');

    if ($check_folder_id && $check_filename !== '') {
        $title_to_check = pathinfo($check_filename, PATHINFO_FILENAME);

        $fstmt = $db->prepare("SELECT id FROM shared_folders WHERE id = ?");
        $fstmt->execute([$check_folder_id]);
        if ($fstmt->fetch()) {
            $dup_stmt = $db->prepare("SELECT id, title FROM shared_photos WHERE folder_id = ? AND title = ?");
            $dup_stmt->execute([$check_folder_id, $title_to_check]);
            $existing = $dup_stmt->fetch();
            if ($existing) {
                echo json_encode([
                    'exists'         => true,
                    'existing_id'    => $existing['id'],
                    'existing_title' => $existing['title'],
                ]);
                exit;
            }
        }
    }

    echo json_encode(['exists' => false]);
    exit;
}

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

// Check if file was uploaded - support both 'images' and 'files' field names
$files_key = isset($_FILES['files']) ? 'files' : 'images';
if (!isset($_FILES[$files_key]) || !isset($_FILES[$files_key]['tmp_name'][0]) || empty($_FILES[$files_key]['tmp_name'][0])) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded.']);
    exit;
}

$file = [
    'name' => $_FILES[$files_key]['name'][0],
    'type' => $_FILES[$files_key]['type'][0],
    'tmp_name' => $_FILES[$files_key]['tmp_name'][0],
    'error' => $_FILES[$files_key]['error'][0],
    'size' => $_FILES[$files_key]['size'][0]
];

// Dangerous extensions that must never be uploaded (server-side executables)
$blocked_extensions = [
    'php', 'php3', 'php4', 'php5', 'php7', 'phtml', 'phar',
    'asp', 'aspx', 'cfm', 'cgi', 'pl', 'py', 'rb',
    'sh', 'bash', 'bat', 'cmd', 'ps1', 'vbs',
    'exe', 'dll', 'com', 'scr',
    'htaccess', 'htpasswd',
];

// Allowed MIME types for photos (used for image-specific validation)
$allowed_photo_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$allowed_video_types = ['video/mp4', 'video/quicktime', 'video/x-msvideo', 'video/webm', 'video/x-matroska', 'video/mpeg', 'video/3gpp'];

$max_file_size = 50 * 1024 * 1024 * 1024; // 50GB for any file

// Determine file type by MIME
$is_photo = in_array($file['type'], $allowed_photo_types);
$is_video = in_array($file['type'], $allowed_video_types);

// Validate upload error
if ($file['error'] !== UPLOAD_ERR_OK) {
    $error_messages = [
        UPLOAD_ERR_INI_SIZE => 'File exceeds server upload limit. Contact server administrator to increase upload_max_filesize and post_max_size in php.ini.',
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

// Validate extension against blacklist
$original_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (in_array($original_ext, $blocked_extensions)) {
    echo json_encode(['success' => false, 'message' => 'This file type is not allowed for security reasons.']);
    exit;
}

// Validate file size
if ($file['size'] > $max_file_size) {
    echo json_encode(['success' => false, 'message' => 'File exceeds 50GB limit.']);
    exit;
}

// For photos, validate actual image content
if ($is_photo) {
    $image_info = getimagesize($file['tmp_name']);
    if ($image_info === false) {
        // Treat as generic file if getimagesize fails despite photo MIME type
        $is_photo = false;
    }
}

// For videos, validate actual video content using file signatures
if ($is_video) {
    $handle = fopen($file['tmp_name'], 'rb');
    if ($handle) {
        $header = fread($handle, 12);
        fclose($handle);

        $valid_video = false;
        if (strlen($header) >= 8 && substr($header, 4, 4) === 'ftyp') {
            $valid_video = true;
        } elseif (strlen($header) >= 12 && substr($header, 0, 4) === 'RIFF' && substr($header, 8, 4) === 'AVI ') {
            $valid_video = true;
        } elseif (strlen($header) >= 4 && substr($header, 0, 4) === "\x1a\x45\xdf\xa3") {
            $valid_video = true;
        } elseif (strlen($header) >= 4 && (substr($header, 0, 4) === "\x00\x00\x01\xba" || substr($header, 0, 4) === "\x00\x00\x01\xb3")) {
            $valid_video = true;
        }

        if (!$valid_video) {
            // Treat as generic file if signature check fails
            $is_video = false;
        }
    }
}

// Create folder-specific upload directory
$folder_upload_dir = UPLOAD_PATH . 'folders/' . $folder_id . '/';
if (!is_dir($folder_upload_dir)) {
    if (!mkdir($folder_upload_dir, 0755, true)) {
        echo json_encode(['success' => false, 'message' => 'Failed to create upload directory.']);
        exit;
    }
}

// Generate unique filename
if ($is_photo) {
    $file_type = 'photo';
    $mime_to_ext = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp'
    ];
    $extension = $mime_to_ext[$image_info['mime']] ?? 'jpg';
    $filename = 'photo_' . time() . '_' . uniqid() . '.' . $extension;
} elseif ($is_video) {
    $file_type = 'video';
    $video_mime_to_ext = [
        'video/mp4' => 'mp4',
        'video/quicktime' => 'mov',
        'video/x-msvideo' => 'avi',
        'video/webm' => 'webm',
        'video/x-matroska' => 'mkv',
        'video/mpeg' => 'mpg',
        'video/3gpp' => '3gp'
    ];
    $extension = $video_mime_to_ext[$file['type']] ?? 'mp4';
    $filename = 'video_' . time() . '_' . uniqid() . '.' . $extension;
} else {
    $file_type = 'file';
    // Use original extension (already validated against blacklist above)
    $extension = $original_ext !== '' ? $original_ext : 'bin';
    $filename = 'file_' . time() . '_' . uniqid() . '.' . $extension;
}

$relative_path = 'folders/' . $folder_id . '/' . $filename;
$upload_path = UPLOAD_PATH . $relative_path;

// Move uploaded file
if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save file.']);
    exit;
}

$thumbnail_relative_path = null;

// If replacing an existing file, delete the old physical file and database record
$replace_existing_id = 0;
$old_image_path = null;
if (isset($_POST['replace_existing']) && $_POST['replace_existing'] === '1') {
    $replace_existing_id = intval($_POST['existing_id'] ?? 0);
}
if ($replace_existing_id) {
    $old_stmt = $db->prepare("SELECT id, image_path, thumbnail_path FROM shared_photos WHERE id = ? AND folder_id = ?");
    $old_stmt->execute([$replace_existing_id, $folder_id]);
    $old_photo = $old_stmt->fetch();
    if ($old_photo) {
        $old_image_path = $old_photo['image_path'];
        $del_stmt = $db->prepare("DELETE FROM shared_photos WHERE id = ?");
        $del_stmt->execute([$replace_existing_id]);
    }
}

// Generate unique download token
$download_token = bin2hex(random_bytes(32));

// Generate title from original filename
$original_name = pathinfo($file['name'], PATHINFO_FILENAME);
$title = !empty($original_name) ? $original_name : ucfirst($file_type) . ' ' . date('Y-m-d H:i:s');

// Get optional sub-folder/album name
$subfolder_name = isset($_POST['subfolder_name']) ? trim($_POST['subfolder_name']) : null;
if ($subfolder_name === '') {
    $subfolder_name = null;
}

// Insert into database
try {
    $sql = "INSERT INTO shared_photos (folder_id, subfolder_name, file_type, title, description, image_path, file_size, thumbnail_path, download_token, status, created_by) 
            VALUES (?, ?, ?, ?, '', ?, ?, ?, ?, 'active', ?)";
    $stmt = $db->prepare($sql);
    $result = $stmt->execute([
        $folder_id,
        $subfolder_name,
        $file_type,
        $title, 
        $relative_path, 
        $file['size'],
        $thumbnail_relative_path,
        $download_token, 
        $current_user['id']
    ]);
    
    if ($result) {
        $file_id = $db->lastInsertId();

        // Delete old physical file now that the new record is committed
        if ($old_image_path) {
            $old_file_path = UPLOAD_PATH . $old_image_path;
            $real_upload_path = realpath(UPLOAD_PATH);
            $real_old_path    = realpath($old_file_path);
            if ($real_old_path && $real_upload_path && strpos($real_old_path, $real_upload_path . DIRECTORY_SEPARATOR) === 0) {
                if (file_exists($old_file_path)) {
                    @unlink($old_file_path);
                }
            }
            // Delete old thumbnail if it exists
            if (!empty($old_photo['thumbnail_path'])) {
                $old_thumb_path = UPLOAD_PATH . $old_photo['thumbnail_path'];
                $real_old_thumb = realpath($old_thumb_path);
                if ($real_old_thumb && $real_upload_path && strpos($real_old_thumb, $real_upload_path . DIRECTORY_SEPARATOR) === 0) {
                    @unlink($old_thumb_path);
                }
            }
        }

        $action_word = ($replace_existing_id && $old_image_path) ? 'replaced' : 'uploaded';
        logActivity($current_user['id'], ucfirst($action_word) . ' ' . $file_type . ' in folder', 'shared_photos', $file_id, ucfirst($action_word) . " in folder: " . $folder['folder_name']);
        
        echo json_encode([
            'success' => true,
            'message' => ucfirst($file_type) . ' ' . $action_word . ' successfully!',
            'image' => [
                'id' => $file_id,
                'title' => $title,
                'filename' => $filename,
                'file_type' => $file_type,
                'file_size' => $file['size'],
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
    error_log('Folder file upload error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred. Please try again.']);
}
