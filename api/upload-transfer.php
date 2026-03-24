<?php
/**
 * Public Transfer Upload Handler (Standard Upload)
 * Allows anyone (no login required) to upload files to a public transfer.
 * Rate-limited by IP. Creates or reuses a shared_folder for the transfer session.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

// Release session lock early so the progress polling does not block
$_SESSION['last_activity'] = time();
session_write_close();

$db = getDB();

// ---------------------------------------------------------------
// Verify CSRF token (transfer page generates its own token)
// ---------------------------------------------------------------
if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token. Please refresh the page and try again.']);
    exit;
}

// ---------------------------------------------------------------
// Rate limiting: max 10 uploads per IP per hour
// ---------------------------------------------------------------
$client_ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$rate_key  = 'transfer_rate_' . md5($client_ip);
$rate_hour = (int) floor(time() / 3600);

// Re-open session briefly to read/write rate limit counters
session_start();
$stored_hour  = $_SESSION[$rate_key . '_hour']  ?? 0;
$stored_count = $_SESSION[$rate_key . '_count'] ?? 0;
if ($stored_hour !== $rate_hour) {
    $stored_hour  = $rate_hour;
    $stored_count = 0;
}
$stored_count++;
$_SESSION[$rate_key . '_hour']  = $stored_hour;
$_SESSION[$rate_key . '_count'] = $stored_count;
session_write_close();

if ($stored_count > 200) { // generous limit: 200 files per IP per hour
    echo json_encode(['success' => false, 'message' => 'Too many uploads. Please try again later.']);
    exit;
}

// ---------------------------------------------------------------
// Obtain or create the shared folder for this transfer
// ---------------------------------------------------------------
$folder_id = intval($_POST['folder_id'] ?? 0);

if (!$folder_id) {
    // First file: create the shared folder now
    $sender_email   = trim($_POST['sender_email']   ?? '');
    $sender_message = trim($_POST['sender_message'] ?? '');
    $expiry_days    = intval($_POST['expiry_days']   ?? 7);

    // Sanitise email
    if ($sender_email !== '' && !filter_var($sender_email, FILTER_VALIDATE_EMAIL)) {
        $sender_email = '';
    }
    // Clamp expiry
    $allowed_expiry = [1, 3, 7, 14, 30];
    if (!in_array($expiry_days, $allowed_expiry, true)) {
        $expiry_days = 7;
    }

    $folder_token  = bin2hex(random_bytes(32));
    $folder_name   = 'Transfer ' . date('Y-m-d H:i:s');
    $expires_at    = date('Y-m-d H:i:s', strtotime("+{$expiry_days} days"));

    try {
        $stmt = $db->prepare(
            "INSERT INTO shared_folders
                (folder_name, download_token, expires_at, status, allow_zip_download, show_preview,
                 sender_email, sender_message, transfer_source, created_by)
             VALUES (?, ?, ?, 'active', 1, 1, ?, ?, 'public', NULL)"
        );
        $stmt->execute([
            $folder_name,
            $folder_token,
            $expires_at,
            $sender_email !== '' ? $sender_email : null,
            $sender_message !== '' ? $sender_message : null,
        ]);
        $folder_id = (int) $db->lastInsertId();
    } catch (Exception $e) {
        error_log('Transfer folder create error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to create transfer. Please try again.']);
        exit;
    }
} else {
    // Subsequent files: verify this folder exists, is public, and is active
    $stmt = $db->prepare(
        "SELECT id, folder_name FROM shared_folders
         WHERE id = ? AND transfer_source = 'public' AND status = 'active'"
    );
    $stmt->execute([$folder_id]);
    $folder = $stmt->fetch();
    if (!$folder) {
        echo json_encode(['success' => false, 'message' => 'Transfer session not found. Please start a new transfer.']);
        exit;
    }
}

// ---------------------------------------------------------------
// Validate uploaded file
// ---------------------------------------------------------------
$files_key = isset($_FILES['files']) ? 'files' : (isset($_FILES['file']) ? 'file' : null);
if (!$files_key || !isset($_FILES[$files_key]['tmp_name'])) {
    echo json_encode(['success' => false, 'message' => 'No file received.']);
    exit;
}

// Normalise single-file vs multi-file upload structure
if (is_array($_FILES[$files_key]['tmp_name'])) {
    $file = [
        'name'     => $_FILES[$files_key]['name'][0],
        'type'     => $_FILES[$files_key]['type'][0],
        'tmp_name' => $_FILES[$files_key]['tmp_name'][0],
        'error'    => $_FILES[$files_key]['error'][0],
        'size'     => $_FILES[$files_key]['size'][0],
    ];
} else {
    $file = [
        'name'     => $_FILES[$files_key]['name'],
        'type'     => $_FILES[$files_key]['type'],
        'tmp_name' => $_FILES[$files_key]['tmp_name'],
        'error'    => $_FILES[$files_key]['error'],
        'size'     => $_FILES[$files_key]['size'],
    ];
}

// Check upload error
if ($file['error'] !== UPLOAD_ERR_OK) {
    $error_messages = [
        UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload limit.',
        UPLOAD_ERR_FORM_SIZE  => 'File exceeds form upload limit.',
        UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
        UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Server missing temporary folder.',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
        UPLOAD_ERR_EXTENSION  => 'Upload stopped by extension.',
    ];
    echo json_encode(['success' => false, 'message' => $error_messages[$file['error']] ?? 'Upload error.']);
    exit;
}

// Blocked extensions (never executable server-side files)
$blocked_extensions = [
    'php', 'php3', 'php4', 'php5', 'php7', 'phtml', 'phar',
    'asp', 'aspx', 'cfm', 'cgi', 'pl', 'py', 'rb',
    'sh', 'bash', 'bat', 'cmd', 'ps1', 'vbs',
    'exe', 'dll', 'com', 'scr',
    'htaccess', 'htpasswd',
];

$original_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (in_array($original_ext, $blocked_extensions, true)) {
    echo json_encode(['success' => false, 'message' => 'This file type is not allowed for security reasons.']);
    exit;
}

// Detect MIME type reliably
$detected_mime = detectMimeType($file['tmp_name']);

$photo_mime = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$video_mime = ['video/mp4', 'video/quicktime', 'video/x-msvideo', 'video/webm', 'video/x-matroska', 'video/mpeg', 'video/3gpp'];

$is_photo = in_array($detected_mime, $photo_mime, true);
$is_video = in_array($detected_mime, $video_mime, true);

// For photos, verify image content
if ($is_photo && @getimagesize($file['tmp_name']) === false) {
    $is_photo = false;
}

// Size limits — for standard (non-chunked) uploads.
// Videos > PHP's upload_max_filesize must use chunked upload (api/chunk-transfer.php).
$max_photo_size  = 50 * 1024 * 1024;        // 50 MB
$max_video_size  = 512 * 1024 * 1024;        // 512 MB (server limit enforced by PHP ini anyway)
$max_file_size   = 5  * 1024 * 1024 * 1024; // 5 GB for generic files

if ($is_photo && $file['size'] > $max_photo_size) {
    echo json_encode(['success' => false, 'message' => 'Photo exceeds 50 MB limit.']);
    exit;
}
if ($is_video && $file['size'] > $max_video_size) {
    echo json_encode(['success' => false, 'message' => 'Video exceeds 512 MB. Large videos are automatically uploaded in chunks — please use the drag & drop area on the transfer page.']);
    exit;
}
if (!$is_photo && !$is_video && $file['size'] > $max_file_size) {
    echo json_encode(['success' => false, 'message' => 'File exceeds 5 GB limit. Use chunked upload for large files.']);
    exit;
}

// ---------------------------------------------------------------
// Save file to disk
// ---------------------------------------------------------------
$folder_upload_dir = UPLOAD_PATH . 'folders/' . $folder_id . '/';
if (!is_dir($folder_upload_dir)) {
    if (!mkdir($folder_upload_dir, 0755, true)) {
        echo json_encode(['success' => false, 'message' => 'Failed to create upload directory.']);
        exit;
    }
    @chmod($folder_upload_dir, 0755);
    $folders_base = UPLOAD_PATH . 'folders/';
    if (is_dir($folders_base)) {
        @chmod($folders_base, 0755);
    }
}

$file_type = $is_photo ? 'photo' : ($is_video ? 'video' : 'file');
$safe_ext  = preg_replace('/[^a-z0-9]/', '', $original_ext) ?: 'bin';
$prefix    = $is_photo ? 'photo_' : ($is_video ? 'video_' : 'file_');
$filename  = $prefix . time() . '_' . uniqid() . '.' . $safe_ext;
$relative_path = 'folders/' . $folder_id . '/' . $filename;
$dest_path     = UPLOAD_PATH . $relative_path;

if (!move_uploaded_file($file['tmp_name'], $dest_path)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save file.']);
    exit;
}
@chmod($dest_path, 0644);

if (!file_exists($dest_path) || filesize($dest_path) === 0) {
    @unlink($dest_path);
    echo json_encode(['success' => false, 'message' => 'File could not be saved. Check server disk space.']);
    exit;
}

// ---------------------------------------------------------------
// Persist to database
// ---------------------------------------------------------------
$download_token = bin2hex(random_bytes(32));
$title_raw      = pathinfo($file['name'], PATHINFO_FILENAME);
$title          = $title_raw !== '' ? $title_raw : ucfirst($file_type) . ' ' . date('Y-m-d H:i:s');

try {
    $stmt = $db->prepare(
        "INSERT INTO shared_photos
            (folder_id, file_type, title, description, image_path, file_size, download_token, status, created_by)
         VALUES (?, ?, ?, '', ?, ?, ?, 'active', NULL)"
    );
    $stmt->execute([
        $folder_id,
        $file_type,
        $title,
        $relative_path,
        $file['size'],
        $download_token,
    ]);
    $file_id = (int) $db->lastInsertId();
} catch (Exception $e) {
    @unlink($dest_path);
    error_log('Transfer upload DB error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error. Please try again.']);
    exit;
}

// Fetch the folder's download token for the shareable link
$tk_stmt = $db->prepare("SELECT download_token FROM shared_folders WHERE id = ?");
$tk_stmt->execute([$folder_id]);
$folder_row = $tk_stmt->fetch();

echo json_encode([
    'success'        => true,
    'folder_id'      => $folder_id,
    'folder_token'   => $folder_row['download_token'] ?? '',
    'file_id'        => $file_id,
    'file_type'      => $file_type,
    'title'          => htmlspecialchars($title, ENT_QUOTES, 'UTF-8'),
    'file_size'      => $file['size'],
    'url'            => UPLOAD_URL . $relative_path,
    'download_url'   => BASE_URL . '/folder.php?token=' . ($folder_row['download_token'] ?? ''),
]);
