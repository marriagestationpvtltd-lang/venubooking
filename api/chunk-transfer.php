<?php
/**
 * Public Transfer Chunked Upload Handler
 * Handles large file uploads in 5 MB chunks for the public transfer feature.
 * No login required. Rate-limited by IP.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

// Release session lock early to avoid blocking concurrent chunk requests
$_SESSION['last_activity'] = time();
session_write_close();

$db = getDB();

// ---------------------------------------------------------------
// Verify CSRF token
// ---------------------------------------------------------------
if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token. Please refresh the page and try again.']);
    exit;
}

// ---------------------------------------------------------------
// Rate limiting: max 2000 chunks per IP per hour (≈ 10 GB at 5 MB/chunk)
// ---------------------------------------------------------------
$client_ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$rate_key  = 'transfer_chunk_' . md5($client_ip);
$rate_hour = (int) floor(time() / 3600);

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

if ($stored_count > 2000) {
    echo json_encode(['success' => false, 'message' => 'Too many uploads. Please try again later.']);
    exit;
}

// ---------------------------------------------------------------
// Required parameters
// ---------------------------------------------------------------
$folder_id     = intval($_POST['folder_id']     ?? 0);
$chunk_index   = intval($_POST['chunk_index']   ?? -1);
$total_chunks  = intval($_POST['total_chunks']  ?? 0);
$upload_id     = trim($_POST['upload_id']       ?? '');
$original_name = trim($_POST['original_name']   ?? '');

if ($chunk_index < 0 || $total_chunks < 1 || $upload_id === '' || $original_name === '') {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters.']);
    exit;
}

// upload_id must be a safe alphanumeric/dash string
if (!preg_match('/^[a-zA-Z0-9\-]{10,64}$/', $upload_id)) {
    echo json_encode(['success' => false, 'message' => 'Invalid upload ID.']);
    exit;
}

// ---------------------------------------------------------------
// On the first chunk (chunk_index == 0) with no folder_id,
// create the shared folder and return the folder_id.
// ---------------------------------------------------------------
if (!$folder_id) {
    if ($chunk_index !== 0) {
        echo json_encode(['success' => false, 'message' => 'folder_id required for non-first chunks.']);
        exit;
    }

    $sender_email   = trim($_POST['sender_email']   ?? '');
    $sender_message = trim($_POST['sender_message'] ?? '');
    $expiry_days    = intval($_POST['expiry_days']   ?? 7);

    if ($sender_email !== '' && !filter_var($sender_email, FILTER_VALIDATE_EMAIL)) {
        $sender_email = '';
    }
    $allowed_expiry = [1, 3, 7, 14, 30];
    if (!in_array($expiry_days, $allowed_expiry, true)) {
        $expiry_days = 7;
    }

    $folder_token = bin2hex(random_bytes(32));
    $folder_name  = 'Transfer ' . date('Y-m-d H:i:s');
    $expires_at   = date('Y-m-d H:i:s', strtotime("+{$expiry_days} days"));

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
        error_log('Transfer chunk folder create error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to create transfer. Please try again.']);
        exit;
    }
} else {
    // Verify folder exists and is a public active transfer
    $stmt = $db->prepare(
        "SELECT id, folder_name FROM shared_folders
         WHERE id = ? AND transfer_source = 'public' AND status = 'active'"
    );
    $stmt->execute([$folder_id]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Transfer session not found.']);
        exit;
    }
}

// ---------------------------------------------------------------
// Blocked file extensions
// ---------------------------------------------------------------
$blocked_extensions = [
    'php', 'php3', 'php4', 'php5', 'php7', 'phtml', 'phar',
    'asp', 'aspx', 'cfm', 'cgi', 'pl', 'py', 'rb',
    'sh', 'bash', 'bat', 'cmd', 'ps1', 'vbs',
    'exe', 'dll', 'com', 'scr',
    'htaccess', 'htpasswd',
];

$original_ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
if (in_array($original_ext, $blocked_extensions, true)) {
    echo json_encode(['success' => false, 'message' => 'This file type is not allowed for security reasons.']);
    exit;
}

// ---------------------------------------------------------------
// Validate chunk
// ---------------------------------------------------------------
if (!isset($_FILES['chunk']) || $_FILES['chunk']['error'] !== UPLOAD_ERR_OK) {
    $err = $_FILES['chunk']['error'] ?? UPLOAD_ERR_NO_FILE;
    $msgs = [
        UPLOAD_ERR_INI_SIZE   => 'Chunk exceeds server upload limit.',
        UPLOAD_ERR_FORM_SIZE  => 'Chunk exceeds form upload limit.',
        UPLOAD_ERR_PARTIAL    => 'Chunk was only partially uploaded.',
        UPLOAD_ERR_NO_FILE    => 'No chunk received.',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write chunk to disk.',
        UPLOAD_ERR_EXTENSION  => 'Upload stopped by extension.',
    ];
    echo json_encode(['success' => false, 'message' => $msgs[$err] ?? 'Chunk upload error.']);
    exit;
}

// ---------------------------------------------------------------
// Save chunk to temp directory
// ---------------------------------------------------------------
$chunks_base_dir = UPLOAD_PATH . '_tmp_chunks/';
$chunks_dir      = $chunks_base_dir . $upload_id . '/';

if (!is_dir($chunks_dir)) {
    if (!mkdir($chunks_dir, 0700, true)) {
        echo json_encode(['success' => false, 'message' => 'Failed to create temp directory.']);
        exit;
    }
}

// Deny web access to chunk temp directory
$chunks_htaccess = $chunks_base_dir . '.htaccess';
if (!file_exists($chunks_htaccess)) {
    file_put_contents(
        $chunks_htaccess,
        "Require all denied\n<IfModule mod_rewrite.c>\nRewriteRule ^ - [F,L]\n</IfModule>\n"
    );
}

$chunk_filename = 'chunk_' . str_pad($chunk_index, 8, '0', STR_PAD_LEFT);
$chunk_path     = $chunks_dir . $chunk_filename;

if (!move_uploaded_file($_FILES['chunk']['tmp_name'], $chunk_path)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save chunk.']);
    exit;
}

$received = count(glob($chunks_dir . 'chunk_*'));

if ($received < $total_chunks) {
    echo json_encode([
        'success'         => true,
        'complete'        => false,
        'folder_id'       => $folder_id,
        'chunks_received' => $received,
        'total_chunks'    => $total_chunks,
    ]);
    exit;
}

// ---------------------------------------------------------------
// All chunks received — assemble the final file
// ---------------------------------------------------------------
@set_time_limit(0);
ignore_user_abort(true);

$photo_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
$video_ext = ['mp4', 'mov', 'avi', 'webm', 'mkv', 'mpg', 'mpeg', '3gp'];
$is_photo  = in_array($original_ext, $photo_ext, true);
$is_video  = in_array($original_ext, $video_ext, true);

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
$output_path   = UPLOAD_PATH . $relative_path;

$output = fopen($output_path, 'wb');
if (!$output) {
    echo json_encode(['success' => false, 'message' => 'Failed to create output file.']);
    exit;
}

$total_size       = 0;
$assembly_success = true;

for ($i = 0; $i < $total_chunks; $i++) {
    $cf = $chunks_dir . 'chunk_' . str_pad($i, 8, '0', STR_PAD_LEFT);
    if (!file_exists($cf)) {
        $assembly_success = false;
        break;
    }
    $cs = fopen($cf, 'rb');
    if (!$cs) {
        $assembly_success = false;
        break;
    }
    $copied = stream_copy_to_stream($cs, $output);
    fclose($cs);
    if ($copied === false) {
        $assembly_success = false;
        break;
    }
    $total_size += $copied;
}
fclose($output);

// Clean up temp chunks
foreach (glob($chunks_dir . 'chunk_*') as $f) {
    @unlink($f);
}
@rmdir($chunks_dir);

if (!$assembly_success) {
    @unlink($output_path);
    echo json_encode(['success' => false, 'message' => 'Failed to assemble file from chunks.']);
    exit;
}

if (!file_exists($output_path) || filesize($output_path) === 0) {
    @unlink($output_path);
    echo json_encode(['success' => false, 'message' => 'Assembled file is empty. Check disk space.']);
    exit;
}
@chmod($output_path, 0644);

// Validate assembled photo
if ($is_photo && @getimagesize($output_path) === false) {
    @unlink($output_path);
    echo json_encode(['success' => false, 'message' => 'Assembled file is not a valid image.']);
    exit;
}

// ---------------------------------------------------------------
// Persist to database
// ---------------------------------------------------------------

// Compress oversized photos server-side so stored files stay at a manageable
// size regardless of the original upload dimensions.
if ($is_photo && compressUploadedImage($output_path)) {
    $total_size = filesize($output_path);
}

// Generate a thumbnail for photo uploads to save bandwidth on the public folder page.
$thumbnail_relative_path = null;
if ($is_photo) {
    $thumb_dir_rel = 'folders/' . $folder_id . '/thumbs/';
    $thumb_dir_abs = UPLOAD_PATH . $thumb_dir_rel;
    $thumb_fname   = pathinfo($filename, PATHINFO_FILENAME) . '_thumb.jpg';
    $thumb_abs     = $thumb_dir_abs . $thumb_fname;
    if (generateSharedFolderThumbnail($output_path, $thumb_abs, 600)) {
        @chmod($thumb_abs, 0644);
        $thumbnail_relative_path = $thumb_dir_rel . $thumb_fname;
    }
}

$download_token = bin2hex(random_bytes(32));
$title_raw      = pathinfo($original_name, PATHINFO_FILENAME);
$title          = $title_raw !== '' ? $title_raw : ucfirst($file_type) . ' ' . date('Y-m-d H:i:s');

try {
    $stmt = $db->prepare(
        "INSERT INTO shared_photos
            (folder_id, file_type, title, description, image_path, file_size, thumbnail_path, download_token, status, created_by)
         VALUES (?, ?, ?, '', ?, ?, ?, ?, 'active', NULL)"
    );
    $stmt->execute([
        $folder_id,
        $file_type,
        $title,
        $relative_path,
        $total_size,
        $thumbnail_relative_path,
        $download_token,
    ]);
    $file_id = (int) $db->lastInsertId();
} catch (Exception $e) {
    @unlink($output_path);
    error_log('Transfer chunk DB error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error. Please try again.']);
    exit;
}

// Fetch folder token for the shareable link
$tk_stmt = $db->prepare("SELECT download_token FROM shared_folders WHERE id = ?");
$tk_stmt->execute([$folder_id]);
$folder_row = $tk_stmt->fetch();

echo json_encode([
    'success'      => true,
    'complete'     => true,
    'folder_id'    => $folder_id,
    'folder_token' => $folder_row['download_token'] ?? '',
    'file_id'      => $file_id,
    'file_type'    => $file_type,
    'title'        => htmlspecialchars($title, ENT_QUOTES, 'UTF-8'),
    'file_size'    => $total_size,
    'url'          => UPLOAD_URL . $relative_path,
    'download_url' => BASE_URL . '/folder.php?token=' . ($folder_row['download_token'] ?? ''),
]);
