<?php
/**
 * Chunked Upload – assemble all received chunks and finalise the upload.
 *
 * POST params:
 *   session_id  string  64-char hex session identifier
 *   folder_id   int     destination folder (used to double-check ownership)
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please log in.']);
    exit;
}

$current_user = getCurrentUser();
$db           = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

// ── Input validation ────────────────────────────────────────────────────────

$session_id = $_POST['session_id'] ?? '';
$folder_id  = intval($_POST['folder_id'] ?? 0);

if (!preg_match('/^[a-f0-9]{64}$/', $session_id)) {
    echo json_encode(['success' => false, 'message' => 'Invalid session ID.']);
    exit;
}

if (!$folder_id) {
    echo json_encode(['success' => false, 'message' => 'Folder ID is required.']);
    exit;
}

// ── Load session ─────────────────────────────────────────────────────────────

$stmt = $db->prepare("SELECT * FROM upload_sessions WHERE id = ? AND folder_id = ?");
$stmt->execute([$session_id, $folder_id]);
$session = $stmt->fetch();

if (!$session) {
    echo json_encode(['success' => false, 'message' => 'Upload session not found.']);
    exit;
}

if ($session['status'] === 'complete') {
    echo json_encode(['success' => false, 'message' => 'Session already completed.']);
    exit;
}

$total_chunks    = intval($session['total_chunks']);
$chunks_received = json_decode($session['chunks_received'], true);
if (!is_array($chunks_received)) {
    $chunks_received = [];
}

// Verify all chunks are present
if (count($chunks_received) < $total_chunks) {
    $missing = array_diff(range(0, $total_chunks - 1), $chunks_received);
    echo json_encode([
        'success'       => false,
        'message'       => 'Missing chunks: ' . implode(', ', $missing),
        'missing_chunks' => array_values($missing),
    ]);
    exit;
}

// ── Verify folder ─────────────────────────────────────────────────────────────

$stmt = $db->prepare("SELECT id, folder_name FROM shared_folders WHERE id = ?");
$stmt->execute([$folder_id]);
$folder = $stmt->fetch();

if (!$folder) {
    echo json_encode(['success' => false, 'message' => 'Folder not found.']);
    exit;
}

// ── Mark as assembling ───────────────────────────────────────────────────────

$db->prepare("UPDATE upload_sessions SET status = 'assembling', updated_at = NOW() WHERE id = ?")
   ->execute([$session_id]);

// ── Assemble chunks into one temp file ───────────────────────────────────────

$temp_dir     = $session['temp_path'];
$assembled    = $temp_dir . 'assembled';
$out = fopen($assembled, 'wb');

if (!$out) {
    echo json_encode(['success' => false, 'message' => 'Failed to create assembled file.']);
    exit;
}

for ($i = 0; $i < $total_chunks; $i++) {
    $chunk_file = $temp_dir . 'chunk_' . $i;
    if (!file_exists($chunk_file)) {
        fclose($out);
        echo json_encode(['success' => false, 'message' => "Chunk {$i} is missing on disk."]);
        exit;
    }
    $in = fopen($chunk_file, 'rb');
    if (!$in) {
        fclose($out);
        echo json_encode(['success' => false, 'message' => "Failed to read chunk {$i}."]);
        exit;
    }
    stream_copy_to_stream($in, $out);
    fclose($in);
}
fclose($out);

// ── Validate assembled file ───────────────────────────────────────────────────

$original_name = $session['original_name'];
$original_ext  = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

$allowed_photo_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
$allowed_video_exts = ['mp4', 'mov', 'avi', 'webm', 'mkv', 'mpg', 'mpeg', '3gp'];

$is_photo = in_array($original_ext, $allowed_photo_exts, true);
$is_video = in_array($original_ext, $allowed_video_exts, true);

if (!$is_photo && !$is_video) {
    @unlink($assembled);
    echo json_encode(['success' => false, 'message' => 'Unsupported file type.']);
    exit;
}

$file_size   = filesize($assembled);
$max_photo   = 20 * 1024 * 1024;        // 20 MB
$max_video   = 8 * 1024 * 1024 * 1024;  // 8 GB

if ($is_photo) {
    $image_info = @getimagesize($assembled);
    if ($image_info === false) {
        @unlink($assembled);
        echo json_encode(['success' => false, 'message' => 'Invalid image file.']);
        exit;
    }
    if ($file_size > $max_photo) {
        @unlink($assembled);
        echo json_encode(['success' => false, 'message' => 'Photo exceeds 20 MB limit.']);
        exit;
    }
}

if ($is_video) {
    if ($file_size > $max_video) {
        @unlink($assembled);
        echo json_encode(['success' => false, 'message' => 'Video exceeds 8 GB limit.']);
        exit;
    }

    // Check file signature
    $handle = fopen($assembled, 'rb');
    $header = $handle ? fread($handle, 12) : '';
    if ($handle) {
        fclose($handle);
    }

    $valid = false;
    if (strlen($header) >= 8  && substr($header, 4, 4) === 'ftyp') {
        $valid = true; // MP4/MOV
    } elseif (strlen($header) >= 12 && substr($header, 0, 4) === 'RIFF' && substr($header, 8, 4) === 'AVI ') {
        $valid = true; // AVI
    } elseif (strlen($header) >= 4  && substr($header, 0, 4) === "\x1a\x45\xdf\xa3") {
        $valid = true; // WebM/MKV
    } elseif (strlen($header) >= 4  && (
            substr($header, 0, 4) === "\x00\x00\x01\xba"
            || substr($header, 0, 4) === "\x00\x00\x01\xb3"
        )) {
        $valid = true; // MPEG
    }

    if (!$valid) {
        @unlink($assembled);
        echo json_encode(['success' => false, 'message' => 'Invalid video file.']);
        exit;
    }
}

// ── Move to final location ────────────────────────────────────────────────────

$folder_upload_dir = UPLOAD_PATH . 'folders/' . $folder_id . '/';
if (!is_dir($folder_upload_dir)) {
    if (!mkdir($folder_upload_dir, 0750, true)) {
        @unlink($assembled);
        echo json_encode(['success' => false, 'message' => 'Failed to create upload directory.']);
        exit;
    }
}

$file_type = $is_photo ? 'photo' : 'video';

if ($is_photo) {
    $mime_to_ext = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    ];
    $extension = $mime_to_ext[$image_info['mime']] ?? $original_ext;
    $filename  = 'photo_' . time() . '_' . uniqid() . '.' . $extension;
} else {
    $video_ext_map = [
        'mp4' => 'mp4', 'mov' => 'mov', 'avi' => 'avi',
        'webm' => 'webm', 'mkv' => 'mkv',
        'mpg' => 'mpg', 'mpeg' => 'mpg', '3gp' => '3gp',
    ];
    $extension = $video_ext_map[$original_ext] ?? 'mp4';
    $filename  = 'video_' . time() . '_' . uniqid() . '.' . $extension;
}

$relative_path = 'folders/' . $folder_id . '/' . $filename;
$final_path    = UPLOAD_PATH . $relative_path;

if (!rename($assembled, $final_path)) {
    @unlink($assembled);
    echo json_encode(['success' => false, 'message' => 'Failed to move file to storage.']);
    exit;
}

// ── Clean up temp chunks ──────────────────────────────────────────────────────

for ($i = 0; $i < $total_chunks; $i++) {
    @unlink($temp_dir . 'chunk_' . $i);
}
@rmdir($temp_dir);

// ── Insert DB record ──────────────────────────────────────────────────────────

$download_token = bin2hex(random_bytes(32));
$title          = pathinfo($original_name, PATHINFO_FILENAME);
if ($title === '') {
    $title = ($is_photo ? 'Photo' : 'Video') . ' ' . date('Y-m-d H:i:s');
}

try {
    $sql = "INSERT INTO shared_photos
                (folder_id, file_type, title, description, image_path, file_size, download_token, status, created_by)
             VALUES (?, ?, ?, '', ?, ?, ?, 'active', ?)";
    $stmt = $db->prepare($sql);
    $stmt->execute([
        $folder_id,
        $file_type,
        $title,
        $relative_path,
        $file_size,
        $download_token,
        $current_user['id'],
    ]);

    $file_id = $db->lastInsertId();

    // Mark session complete
    $db->prepare("UPDATE upload_sessions SET status = 'complete', updated_at = NOW() WHERE id = ?")
       ->execute([$session_id]);

    logActivity(
        $current_user['id'],
        'Uploaded ' . $file_type . ' to folder (chunked)',
        'shared_photos',
        $file_id,
        'Uploaded to folder: ' . $folder['folder_name']
    );

    echo json_encode([
        'success'  => true,
        'message'  => ucfirst($file_type) . ' uploaded successfully!',
        'image'    => [
            'id'        => $file_id,
            'title'     => $title,
            'filename'  => $filename,
            'file_type' => $file_type,
            'file_size' => $file_size,
            'url'       => UPLOAD_URL . $relative_path,
        ],
    ]);
} catch (Exception $e) {
    @unlink($final_path);
    error_log('Complete upload DB error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error. Please try again.']);
}
