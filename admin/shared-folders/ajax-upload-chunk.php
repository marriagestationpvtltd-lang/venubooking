<?php
/**
 * Chunked Upload – receive a single chunk.
 *
 * POST params (multipart/form-data):
 *   session_id     string  64-char hex session identifier
 *   folder_id      int     destination folder
 *   original_name  string  original file name
 *   file_size      int     total file size in bytes
 *   total_chunks   int     total number of chunks
 *   chunk_index    int     0-based index of this chunk
 *   chunk          file    raw binary chunk data
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

$session_id    = $_POST['session_id']    ?? '';
$folder_id     = intval($_POST['folder_id']     ?? 0);
$original_name = trim($_POST['original_name']   ?? '');
$file_size     = intval($_POST['file_size']     ?? 0);
$total_chunks  = intval($_POST['total_chunks']  ?? 1);
$chunk_index   = intval($_POST['chunk_index']   ?? 0);

// Validate session ID – must be exactly 64 lowercase hex chars
if (!preg_match('/^[a-f0-9]{64}$/', $session_id)) {
    echo json_encode(['success' => false, 'message' => 'Invalid session ID.']);
    exit;
}

if (!$folder_id) {
    echo json_encode(['success' => false, 'message' => 'Folder ID is required.']);
    exit;
}

if ($original_name === '' || strlen($original_name) > 255) {
    echo json_encode(['success' => false, 'message' => 'Invalid file name.']);
    exit;
}

// Strip any directory traversal from original_name
$original_name = basename($original_name);

if ($total_chunks < 1 || $chunk_index < 0 || $chunk_index >= $total_chunks) {
    echo json_encode(['success' => false, 'message' => 'Invalid chunk parameters.']);
    exit;
}

// Verify chunk was uploaded
if (
    !isset($_FILES['chunk'])
    || $_FILES['chunk']['error'] !== UPLOAD_ERR_OK
    || !is_uploaded_file($_FILES['chunk']['tmp_name'])
) {
    $err_map = [
        UPLOAD_ERR_INI_SIZE   => 'Chunk exceeds server upload limit.',
        UPLOAD_ERR_FORM_SIZE  => 'Chunk exceeds form upload limit.',
        UPLOAD_ERR_PARTIAL    => 'Chunk was only partially uploaded.',
        UPLOAD_ERR_NO_FILE    => 'No chunk was uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Server missing temporary folder.',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write chunk to disk.',
    ];
    $err_code = $_FILES['chunk']['error'] ?? -1;
    $msg      = $err_map[$err_code] ?? 'Unknown upload error.';
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}

// ── Verify folder ────────────────────────────────────────────────────────────

$stmt = $db->prepare("SELECT id FROM shared_folders WHERE id = ?");
$stmt->execute([$folder_id]);
if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Folder not found.']);
    exit;
}

// ── Temp directory for this session ─────────────────────────────────────────

$temp_base = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'venubooking_chunks' . DIRECTORY_SEPARATOR;
$temp_dir  = $temp_base . $session_id . DIRECTORY_SEPARATOR;

if (!is_dir($temp_dir)) {
    if (!mkdir($temp_dir, 0700, true)) {
        echo json_encode(['success' => false, 'message' => 'Failed to create temporary upload directory.']);
        exit;
    }
}

// ── Save chunk ───────────────────────────────────────────────────────────────

$chunk_path = $temp_dir . 'chunk_' . $chunk_index;

if (!move_uploaded_file($_FILES['chunk']['tmp_name'], $chunk_path)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save chunk.']);
    exit;
}

// ── Update / create session record ──────────────────────────────────────────

try {
    $stmt = $db->prepare("SELECT id, chunks_received FROM upload_sessions WHERE id = ?");
    $stmt->execute([$session_id]);
    $session = $stmt->fetch();

    if ($session) {
        $chunks_received = json_decode($session['chunks_received'], true);
        if (!is_array($chunks_received)) {
            $chunks_received = [];
        }
        if (!in_array($chunk_index, $chunks_received, true)) {
            $chunks_received[] = $chunk_index;
        }

        $stmt = $db->prepare(
            "UPDATE upload_sessions
                SET chunks_received = ?,
                    status          = 'uploading',
                    updated_at      = NOW()
              WHERE id = ?"
        );
        $stmt->execute([json_encode($chunks_received), $session_id]);
    } else {
        // Create the session on first chunk
        $chunks_received = [$chunk_index];

        $stmt = $db->prepare(
            "INSERT INTO upload_sessions
                (id, folder_id, original_name, file_size, total_chunks, chunks_received, temp_path, status, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'uploading', ?)"
        );
        $stmt->execute([
            $session_id,
            $folder_id,
            $original_name,
            $file_size,
            $total_chunks,
            json_encode($chunks_received),
            $temp_dir,
            $current_user['id'],
        ]);
    }

    echo json_encode([
        'success'         => true,
        'session_id'      => $session_id,
        'chunk_index'     => $chunk_index,
        'chunks_received' => count($chunks_received),
        'total_chunks'    => $total_chunks,
    ]);
} catch (Exception $e) {
    error_log('Chunk upload DB error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error. Please try again.']);
}
