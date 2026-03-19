<?php
/**
 * Chunked Upload Handler for Any File Type
 * Handles uploads in 5 MB chunks, then assembles the full file.
 * Supports videos up to 50 GB, photos up to 50 MB, and any other file type up to 50 GB.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');

// Must be authenticated
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please log in.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

// Verify CSRF token
if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token.']);
    exit;
}

$current_user = getCurrentUser();
$db = getDB();

// Required parameters
$folder_id    = intval($_POST['folder_id']    ?? 0);
$chunk_index  = intval($_POST['chunk_index']  ?? -1);
$total_chunks = intval($_POST['total_chunks'] ?? 0);
$upload_id    = trim($_POST['upload_id']      ?? '');
$original_name = trim($_POST['original_name'] ?? '');

if (!$folder_id || $chunk_index < 0 || $total_chunks < 1 || $upload_id === '' || $original_name === '') {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters.']);
    exit;
}

// upload_id must be a safe alphanumeric/dash string (max 64 chars)
if (!preg_match('/^[a-zA-Z0-9\-]{10,64}$/', $upload_id)) {
    echo json_encode(['success' => false, 'message' => 'Invalid upload ID.']);
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

// Validate the chunk file
if (!isset($_FILES['chunk']) || $_FILES['chunk']['error'] !== UPLOAD_ERR_OK) {
    $err = $_FILES['chunk']['error'] ?? UPLOAD_ERR_NO_FILE;
    $messages = [
        UPLOAD_ERR_INI_SIZE   => 'Chunk exceeds server upload limit.',
        UPLOAD_ERR_FORM_SIZE  => 'Chunk exceeds form upload limit.',
        UPLOAD_ERR_PARTIAL    => 'Chunk was only partially uploaded.',
        UPLOAD_ERR_NO_FILE    => 'No chunk received.',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write chunk to disk.',
        UPLOAD_ERR_EXTENSION  => 'Upload stopped by extension.',
    ];
    echo json_encode(['success' => false, 'message' => $messages[$err] ?? 'Chunk upload error.']);
    exit;
}

// Determine file type from extension
$photo_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
$video_ext = ['mp4', 'mov', 'avi', 'webm', 'mkv', 'mpg', 'mpeg', '3gp'];

$original_ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
$is_photo = in_array($original_ext, $photo_ext);
$is_video = in_array($original_ext, $video_ext);
// $is_other covers any other extension (zip, pdf, docx, rar, etc.)

// ---------------------------------------------------------------
// Save this chunk to a temp directory
// ---------------------------------------------------------------
$chunks_base_dir = sys_get_temp_dir() . '/vb_chunks/';
$chunks_dir = $chunks_base_dir . $upload_id . '/';

if (!is_dir($chunks_dir)) {
    if (!mkdir($chunks_dir, 0700, true)) {
        echo json_encode(['success' => false, 'message' => 'Failed to create temp directory.']);
        exit;
    }
}

// Chunk filenames are zero-padded so glob sorts them numerically
$chunk_filename = 'chunk_' . str_pad($chunk_index, 8, '0', STR_PAD_LEFT);
$chunk_path = $chunks_dir . $chunk_filename;

if (!move_uploaded_file($_FILES['chunk']['tmp_name'], $chunk_path)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save chunk.']);
    exit;
}

// Count received chunks
$received = count(glob($chunks_dir . 'chunk_*'));

if ($received < $total_chunks) {
    // Still waiting for more chunks
    echo json_encode([
        'success'         => true,
        'complete'        => false,
        'chunks_received' => $received,
        'total_chunks'    => $total_chunks,
    ]);
    exit;
}

// ---------------------------------------------------------------
// All chunks received – assemble the final file
// ---------------------------------------------------------------
$folder_upload_dir = UPLOAD_PATH . 'folders/' . $folder_id . '/';
if (!is_dir($folder_upload_dir)) {
    if (!mkdir($folder_upload_dir, 0755, true)) {
        echo json_encode(['success' => false, 'message' => 'Failed to create upload directory.']);
        exit;
    }
}

$file_type_label = $is_photo ? 'photo' : ($is_video ? 'video' : 'file');
$prefix          = $is_photo ? 'photo_' : ($is_video ? 'video_' : 'file_');
// Sanitize extension for non-photo/non-video files
$safe_ext        = preg_replace('/[^a-z0-9]/', '', $original_ext) ?: 'bin';
$filename        = $prefix . time() . '_' . uniqid() . '.' . $safe_ext;
$relative_path   = 'folders/' . $folder_id . '/' . $filename;
$output_path     = UPLOAD_PATH . $relative_path;

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
    $data = file_get_contents($cf);
    if ($data === false) {
        $assembly_success = false;
        break;
    }
    fwrite($output, $data);
    $total_size += strlen($data);
}
fclose($output);

// Always clean up temp chunks
foreach (glob($chunks_dir . 'chunk_*') as $f) {
    @unlink($f);
}
@rmdir($chunks_dir);

if (!$assembly_success) {
    if (file_exists($output_path)) {
        unlink($output_path);
    }
    echo json_encode(['success' => false, 'message' => 'Failed to assemble file from chunks.']);
    exit;
}

// ---------------------------------------------------------------
// Validate assembled file
// ---------------------------------------------------------------
if ($is_photo) {
    $image_info = @getimagesize($output_path);
    if ($image_info === false) {
        unlink($output_path);
        echo json_encode(['success' => false, 'message' => 'Assembled file is not a valid image.']);
        exit;
    }
    $max_photo_size = 50 * 1024 * 1024; // 50 MB
    if ($total_size > $max_photo_size) {
        unlink($output_path);
        echo json_encode(['success' => false, 'message' => 'Photo exceeds 50 MB limit.']);
        exit;
    }
}

if ($is_video) {
    $max_video_size = 50 * 1024 * 1024 * 1024; // 50 GB
    if ($total_size > $max_video_size) {
        unlink($output_path);
        echo json_encode(['success' => false, 'message' => 'Video exceeds 50 GB limit.']);
        exit;
    }

    // Validate video file signature
    $handle = fopen($output_path, 'rb');
    if ($handle) {
        $header      = fread($handle, 12);
        fclose($handle);
        $valid_video = false;

        // MP4 / MOV: 'ftyp' at byte offset 4
        if (strlen($header) >= 8 && substr($header, 4, 4) === 'ftyp') {
            $valid_video = true;
        }
        // AVI: 'RIFF' + 'AVI '
        elseif (strlen($header) >= 12
            && substr($header, 0, 4) === 'RIFF'
            && substr($header, 8, 4) === 'AVI ') {
            $valid_video = true;
        }
        // WebM / MKV: EBML magic bytes
        elseif (strlen($header) >= 4 && substr($header, 0, 4) === "\x1a\x45\xdf\xa3") {
            $valid_video = true;
        }
        // MPEG-PS / MPEG-ES
        elseif (strlen($header) >= 4
            && (substr($header, 0, 4) === "\x00\x00\x01\xba"
                || substr($header, 0, 4) === "\x00\x00\x01\xb3")) {
            $valid_video = true;
        }

        if (!$valid_video) {
            unlink($output_path);
            echo json_encode(['success' => false, 'message' => 'Assembled file is not a valid video.']);
            exit;
        }
    }
}

// For other file types, only validate maximum size (50 GB)
if (!$is_photo && !$is_video) {
    $max_other_size = 50 * 1024 * 1024 * 1024; // 50 GB
    if ($total_size > $max_other_size) {
        unlink($output_path);
        echo json_encode(['success' => false, 'message' => 'File exceeds 50 GB limit.']);
        exit;
    }
}

// ---------------------------------------------------------------
// Persist to database
// ---------------------------------------------------------------

$thumbnail_relative_path = null;

$download_token = bin2hex(random_bytes(32));
$title_raw      = pathinfo($original_name, PATHINFO_FILENAME);
$default_label  = $file_type_label === 'photo' ? 'Photo' : ($file_type_label === 'video' ? 'Video' : 'File');
$title          = $title_raw !== ''
    ? $title_raw
    : $default_label . ' ' . date('Y-m-d H:i:s');

// If replacing an existing file, remove its record and physical file
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

try {
    $sql = "INSERT INTO shared_photos
                (folder_id, file_type, title, description, image_path, file_size, thumbnail_path, download_token, status, created_by)
            VALUES (?, ?, ?, '', ?, ?, ?, ?, 'active', ?)";
    $stmt   = $db->prepare($sql);
    $result = $stmt->execute([
        $folder_id,
        $file_type_label,
        $title,
        $relative_path,
        $total_size,
        $thumbnail_relative_path,
        $download_token,
        $current_user['id'],
    ]);

    if ($result) {
        $file_id = $db->lastInsertId();

        // Delete old physical file now that the new record is committed
        if ($old_image_path) {
            $old_file_path = UPLOAD_PATH . $old_image_path;
            $real_upload_base = realpath(UPLOAD_PATH);
            $real_old_path    = realpath($old_file_path);
            if ($real_old_path && $real_upload_base && strpos($real_old_path, $real_upload_base . DIRECTORY_SEPARATOR) === 0) {
                if (file_exists($old_file_path)) {
                    @unlink($old_file_path);
                }
            }
            // Delete old thumbnail if it exists
            if (!empty($old_photo['thumbnail_path'])) {
                $old_thumb_path = UPLOAD_PATH . $old_photo['thumbnail_path'];
                $real_old_thumb = realpath($old_thumb_path);
                if ($real_old_thumb && $real_upload_base && strpos($real_old_thumb, $real_upload_base . DIRECTORY_SEPARATOR) === 0) {
                    @unlink($old_thumb_path);
                }
            }
        }

        $action_word = ($replace_existing_id && $old_image_path) ? 'replaced' : 'uploaded';
        logActivity(
            $current_user['id'],
            'Chunked ' . $action_word . ' ' . $file_type_label . ' in folder',
            'shared_photos',
            $file_id,
            ucfirst($action_word) . ' in folder: ' . $folder['folder_name']
        );

        echo json_encode([
            'success'  => true,
            'complete' => true,
            'message'  => ucfirst($file_type_label) . ' ' . $action_word . ' successfully!',
            'image'    => [
                'id'        => $file_id,
                'title'     => $title,
                'filename'  => $filename,
                'file_type' => $file_type_label,
                'file_size' => $total_size,
                'url'       => UPLOAD_URL . $relative_path,
            ],
        ]);
    } else {
        unlink($output_path);
        echo json_encode(['success' => false, 'message' => 'Failed to save file record to database.']);
    }
} catch (Exception $e) {
    if (file_exists($output_path)) {
        unlink($output_path);
    }
    error_log('Chunked upload DB error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error. Please try again.']);
}
