<?php
/**
 * Generate ZIP for Shared Folder Download
 *
 * Accepts a folder token and an optional list of photo IDs (for bulk-selected
 * downloads).  Generates the ZIP on disk inside uploads/zip_cache/ and returns
 * a direct file URL so the browser can download it independently of PHP's
 * execution time limit, avoiding the streaming-timeout issue.
 *
 * POST params:
 *   token      – folder download_token (required)
 *   photo_ids  – JSON-encoded array of photo IDs to include (optional;
 *                omit or send [] to include all photos in the folder/album)
 *   album      – subfolder_name filter (optional)
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

// Only POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$token = isset($_POST['token']) ? trim($_POST['token']) : '';
if (empty($token)) {
    echo json_encode(['success' => false, 'error' => 'Missing folder token.']);
    exit;
}

// Parse optional photo_ids – sent as JSON string from JS
$requested_ids = [];
if (!empty($_POST['photo_ids'])) {
    $decoded = json_decode($_POST['photo_ids'], true);
    if (is_array($decoded)) {
        $requested_ids = array_map('intval', $decoded);
        $requested_ids = array_filter($requested_ids, function($id) { return $id > 0; });
        $requested_ids = array_values($requested_ids);
    }
}

$album = isset($_POST['album']) ? trim($_POST['album']) : null;
if ($album === '') {
    $album = null;
}

// ── Verify ZipArchive is available ────────────────────────────────────────────
if (!class_exists('ZipArchive')) {
    echo json_encode(['success' => false, 'error' => 'ZIP generation is not supported on this server. Please contact support.']);
    exit;
}

$db = getDB();

try {
    // ── Fetch & validate folder ────────────────────────────────────────────────
    $stmt = $db->prepare("SELECT * FROM shared_folders WHERE download_token = ?");
    $stmt->execute([$token]);
    $folder = $stmt->fetch();

    if (!$folder) {
        echo json_encode(['success' => false, 'error' => 'Folder not found. The link may be invalid.']);
        exit;
    }
    if ($folder['status'] !== 'active') {
        echo json_encode(['success' => false, 'error' => 'This folder is no longer active.']);
        exit;
    }
    if ($folder['expires_at'] && strtotime($folder['expires_at']) < time()) {
        echo json_encode(['success' => false, 'error' => 'This folder link has expired.']);
        exit;
    }
    if (!$folder['allow_zip_download']) {
        echo json_encode(['success' => false, 'error' => 'ZIP download is not enabled for this folder.']);
        exit;
    }

    // ── Fetch photos ───────────────────────────────────────────────────────────
    if (!empty($requested_ids)) {
        // Specific photo IDs requested (bulk-selected download)
        $placeholders = implode(',', array_fill(0, count($requested_ids), '?'));
        $params = array_merge([$folder['id']], $requested_ids);
        $stmt = $db->prepare(
            "SELECT * FROM shared_photos
             WHERE folder_id = ? AND id IN ($placeholders) AND status = 'active'
             ORDER BY created_at DESC"
        );
        $stmt->execute($params);
    } elseif ($album !== null) {
        // Album-scoped download
        if ($album === '') {
            $stmt = $db->prepare(
                "SELECT * FROM shared_photos
                 WHERE folder_id = ? AND status = 'active'
                   AND (subfolder_name IS NULL OR subfolder_name = '')
                 ORDER BY created_at DESC"
            );
            $stmt->execute([$folder['id']]);
        } else {
            $stmt = $db->prepare(
                "SELECT * FROM shared_photos
                 WHERE folder_id = ? AND status = 'active' AND subfolder_name = ?
                 ORDER BY created_at DESC"
            );
            $stmt->execute([$folder['id'], $album]);
        }
    } else {
        // All photos in folder
        $stmt = $db->prepare(
            "SELECT * FROM shared_photos
             WHERE folder_id = ? AND status = 'active'
             ORDER BY COALESCE(subfolder_name,'') ASC, created_at DESC"
        );
        $stmt->execute([$folder['id']]);
    }
    $photos = $stmt->fetchAll();

    if (empty($photos)) {
        echo json_encode(['success' => false, 'error' => 'No photos found to download.']);
        exit;
    }

    // ── Validate files & build ZIP entry list ──────────────────────────────────
    $real_upload_path = realpath(UPLOAD_PATH);

    $safe_folder_name = preg_replace('/[^a-zA-Z0-9_\-\s]/u', '_', $folder['folder_name']);
    $safe_folder_name = preg_replace('/_+/', '_', $safe_folder_name);
    $safe_folder_name = trim($safe_folder_name, '_');
    if (empty($safe_folder_name)) {
        $safe_folder_name = 'photos';
    }

    // Determine ZIP inner directory (use album name if album-scoped)
    if ($album !== null) {
        $inner_dir = preg_replace('/[^a-zA-Z0-9_\-\s]/u', '_', $album === '' ? 'General' : $album);
        $inner_dir = preg_replace('/_+/', '_', $inner_dir);
        $inner_dir = trim($inner_dir, '_') ?: 'album';
    } else {
        $inner_dir = $safe_folder_name;
    }

    $valid_files     = [];
    $file_counter    = [];

    foreach ($photos as $photo) {
        // Respect max downloads limit
        if ($folder['max_downloads'] && $photo['download_count'] >= $folder['max_downloads']) {
            continue;
        }

        if (empty($photo['image_path'])) {
            continue;
        }

        // Path-traversal guard
        $safe_path = str_replace('\\', '/', $photo['image_path']);
        if (empty($safe_path) || strpos($safe_path, '../') !== false || strpos($safe_path, '/..') !== false || $safe_path[0] === '/') {
            continue;
        }

        $file_path = UPLOAD_PATH . $photo['image_path'];

        if ($real_upload_path !== false) {
            $real_file = realpath($file_path);
            if ($real_file === false || strpos($real_file, $real_upload_path . DIRECTORY_SEPARATOR) !== 0 || !file_exists($file_path)) {
                continue;
            }
        } elseif (!file_exists($file_path)) {
            continue;
        }

        $ext        = pathinfo($photo['image_path'], PATHINFO_EXTENSION);
        $safe_title = preg_replace('/[^a-zA-Z0-9_\-\.\s]/u', '_', $photo['title']);
        $safe_title = preg_replace('/_+/', '_', $safe_title);
        $safe_title = trim($safe_title, '_');
        if (empty($safe_title)) {
            $safe_title = 'photo';
        }

        // Handle duplicate filenames inside the ZIP
        $base_name = $safe_title . '.' . $ext;
        if (isset($file_counter[$base_name])) {
            $file_counter[$base_name]++;
            $zip_entry = $inner_dir . '/' . $safe_title . '_' . $file_counter[$base_name] . '.' . $ext;
        } else {
            $file_counter[$base_name] = 1;
            $zip_entry = $inner_dir . '/' . $base_name;
        }

        $valid_files[] = [
            'path'     => $file_path,
            'zip_name' => $zip_entry,
            'photo_id' => (int) $photo['id'],
        ];
    }

    if (empty($valid_files)) {
        echo json_encode(['success' => false, 'error' => 'No valid files to include in the ZIP.']);
        exit;
    }

    // ── Prepare ZIP cache directory ────────────────────────────────────────────
    $cache_dir = UPLOAD_PATH . 'zip_cache/';
    if (!is_dir($cache_dir)) {
        if (!mkdir($cache_dir, 0755, true)) {
            echo json_encode(['success' => false, 'error' => 'Could not create ZIP cache directory.']);
            exit;
        }
    }

    // Write .htaccess if missing (extra safety on hosts without default protection)
    $htaccess_path = $cache_dir . '.htaccess';
    if (!file_exists($htaccess_path)) {
        $htaccess_result = file_put_contents($htaccess_path,
            "Options -Indexes\n" .
            "<FilesMatch \"\\.php[0-9]?$\">\n    Require all denied\n</FilesMatch>\n"
        );
        if ($htaccess_result === false) {
            error_log('generate-zip.php: could not write .htaccess to zip_cache directory: ' . $htaccess_path);
        }
    }

    // ── Remove stale ZIP files (older than 2 hours) ────────────────────────────
    foreach (glob($cache_dir . '*.zip') ?: [] as $old_zip) {
        if (@filemtime($old_zip) < time() - 7200) {
            @unlink($old_zip);
        }
    }

    // ── Generate ZIP ───────────────────────────────────────────────────────────
    @set_time_limit(0);

    $zip_nonce      = bin2hex(random_bytes(16));
    $zip_basename   = 'zip_' . $folder['id'] . '_' . $zip_nonce . '_' . date('Ymd') . '.zip';
    $zip_path       = $cache_dir . $zip_basename;

    $zip    = new ZipArchive();
    $result = $zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    if ($result !== true) {
        echo json_encode(['success' => false, 'error' => 'Could not create ZIP file (code ' . $result . ').']);
        exit;
    }

    $added_count        = 0;
    $photo_ids_updated  = [];

    foreach ($valid_files as $file_info) {
        if ($zip->addFile($file_info['path'], $file_info['zip_name'])) {
            $added_count++;
            $photo_ids_updated[] = $file_info['photo_id'];
        }
    }

    $zip->close();

    if ($added_count === 0 || !file_exists($zip_path)) {
        @unlink($zip_path);
        echo json_encode(['success' => false, 'error' => 'Failed to add files to the ZIP archive.']);
        exit;
    }

    // ── Update download counters ───────────────────────────────────────────────
    if (!empty($photo_ids_updated)) {
        $ph = implode(',', array_fill(0, count($photo_ids_updated), '?'));
        $db->prepare("UPDATE shared_photos SET download_count = download_count + 1 WHERE id IN ($ph)")
           ->execute($photo_ids_updated);
        $db->prepare("UPDATE shared_folders SET total_downloads = total_downloads + ? WHERE id = ?")
           ->execute([count($photo_ids_updated), $folder['id']]);
    }

    // ── Return direct download URL ─────────────────────────────────────────────
    $download_url      = UPLOAD_URL . 'zip_cache/' . $zip_basename;
    $zip_display_name  = $safe_folder_name . '_' . date('Y-m-d') . '.zip';

    echo json_encode([
        'success'       => true,
        'download_url'  => $download_url,
        'zip_filename'  => $zip_display_name,
        'file_count'    => $added_count,
        'file_size'     => filesize($zip_path),
    ]);

} catch (Exception $e) {
    error_log('generate-zip.php error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'An unexpected error occurred. Please try again.']);
}
