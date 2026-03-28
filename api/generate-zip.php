<?php
/**
 * Generate ZIP and return a signed download URL (TransferNow-style step 1).
 *
 * GET  ?token=FOLDER_TOKEN[&ids=1,2,3]
 *
 * Response (JSON):
 *   { "success": true,  "url": "/api/download-zip.php?zip_token=XXX&fileName=...", "filename": "...", "size": 12345 }
 *   { "success": false, "error": "..." }
 *
 * The generated ZIP is stored in uploads/zip_cache/ and expires after 2 hours.
 * Expired ZIPs from previous requests are cleaned up on each call.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// ── Helper ──────────────────────────────────────────────────────────────────

function _gz_json(bool $success, array $extra = [], int $status = 200): void
{
    http_response_code($status);
    echo json_encode(array_merge(['success' => $success], $extra));
    exit;
}

// Release the session lock before doing potentially expensive work, so the
// browser can make the follow-up download-zip.php request without being blocked.
session_write_close();

// ── Input ────────────────────────────────────────────────────────────────────

$folder_token = isset($_GET['token']) ? trim($_GET['token']) : '';
$ids_raw      = isset($_GET['ids'])   ? trim($_GET['ids'])   : '';

if ($folder_token === '') {
    _gz_json(false, ['error' => 'Missing folder token'], 400);
}

// ── Validate folder ──────────────────────────────────────────────────────────

$db = getDB();

try {
    $stmt = $db->prepare("SELECT * FROM shared_folders WHERE download_token = ?");
    $stmt->execute([$folder_token]);
    $folder = $stmt->fetch();
} catch (Throwable $e) {
    error_log('generate-zip: DB error: ' . $e->getMessage());
    _gz_json(false, ['error' => 'Database error'], 500);
}

if (!$folder) {
    _gz_json(false, ['error' => 'Folder not found'], 404);
}
if (in_array($folder['status'], ['inactive', 'expired'], true)) {
    _gz_json(false, ['error' => 'Folder link is no longer active'], 403);
}
if ($folder['expires_at'] && strtotime($folder['expires_at']) < time()) {
    _gz_json(false, ['error' => 'Folder link has expired'], 403);
}

$has_selected_ids = $ids_raw !== '' && preg_match('/\d/', $ids_raw);

if (!$folder['allow_zip_download'] && !$has_selected_ids) {
    _gz_json(false, ['error' => 'ZIP download is not permitted for this folder'], 403);
}

// ── Load photos ───────────────────────────────────────────────────────────────

try {
    $photos_stmt = $db->prepare(
        "SELECT * FROM shared_photos
          WHERE folder_id = ? AND status = 'active'
          ORDER BY COALESCE(subfolder_name,'') ASC, created_at DESC"
    );
    $photos_stmt->execute([$folder['id']]);
    $photos = $photos_stmt->fetchAll();
} catch (Throwable $e) {
    error_log('generate-zip: photo load error: ' . $e->getMessage());
    _gz_json(false, ['error' => 'Failed to load photos'], 500);
}

// Optional: filter to selected IDs
if ($has_selected_ids) {
    $filter_ids = [];
    foreach (explode(',', $ids_raw) as $_raw_id) {
        $_id = intval(trim($_raw_id));
        if ($_id > 0) {
            $filter_ids[] = $_id;
        }
    }
    if (!empty($filter_ids)) {
        $photos = array_values(array_filter($photos, function ($p) use ($filter_ids) {
            return in_array((int)$p['id'], $filter_ids, true);
        }));
    }
}

if (empty($photos)) {
    _gz_json(false, ['error' => 'No photos to download'], 404);
}

// ── MIME → extension map (mirrors folder.php) ─────────────────────────────────

$mime_ext_map = [
    'image/jpeg'                   => 'jpg',
    'image/pjpeg'                  => 'jpg',
    'image/png'                    => 'png',
    'image/gif'                    => 'gif',
    'image/webp'                   => 'webp',
    'image/bmp'                    => 'bmp',
    'image/tiff'                   => 'tiff',
    'image/heic'                   => 'heic',
    'image/heif'                   => 'heif',
    'image/svg+xml'                => 'svg',
    'image/x-icon'                 => 'ico',
    'video/mp4'                    => 'mp4',
    'video/quicktime'              => 'mov',
    'video/x-msvideo'              => 'avi',
    'video/x-ms-wmv'               => 'wmv',
    'video/webm'                   => 'webm',
    'video/x-matroska'             => 'mkv',
    'video/mpeg'                   => 'mpg',
    'video/3gpp'                   => '3gp',
    'video/x-m4v'                  => 'm4v',
    'video/ogg'                    => 'ogv',
    'audio/mpeg'                   => 'mp3',
    'audio/wav'                    => 'wav',
    'audio/aac'                    => 'aac',
    'audio/flac'                   => 'flac',
    'audio/ogg'                    => 'ogg',
    'audio/x-m4a'                  => 'm4a',
    'application/pdf'              => 'pdf',
    'application/zip'              => 'zip',
    'application/x-rar-compressed' => 'rar',
    'application/x-7z-compressed'  => '7z',
];

// ── Pre-validate files ────────────────────────────────────────────────────────

$safe_folder_name = preg_replace('/[^a-zA-Z0-9_\-\s]/u', '_', $folder['folder_name']);
$safe_folder_name = preg_replace('/_+/', '_', $safe_folder_name);
$safe_folder_name = trim($safe_folder_name, '_');
if (empty($safe_folder_name)) {
    $safe_folder_name = 'photos';
}

$real_upload_path = realpath(UPLOAD_PATH);
$valid_files      = [];
$file_counter     = [];

foreach ($photos as $photo) {
    $file_path      = UPLOAD_PATH . $photo['image_path'];
    $real_file_path = realpath($file_path);

    $file_is_safe = false;
    if ($real_file_path !== false && $real_upload_path !== false) {
        $file_is_safe = strpos(
            $real_file_path,
            rtrim($real_upload_path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
        ) === 0 && file_exists($file_path);
    } elseif (file_exists($file_path)) {
        $safe_p = str_replace('\\', '/', $photo['image_path']);
        $file_is_safe = $safe_p !== ''
            && strpos($safe_p, '../') === false
            && strpos($safe_p, '/..') === false
            && $safe_p[0] !== '/';
    }

    if (!$file_is_safe) {
        continue;
    }

    // Use MIME-detected extension for correct filenames inside the ZIP
    $ext         = pathinfo($photo['image_path'], PATHINFO_EXTENSION);
    $actual_mime = detectMimeType($file_path);
    if (!empty($mime_ext_map[$actual_mime])) {
        $ext = $mime_ext_map[$actual_mime];
    }

    $safe_title = preg_replace('/[^a-zA-Z0-9_\-\.\s]/u', '_', $photo['title']);
    $safe_title = preg_replace('/_+/', '_', $safe_title);
    $safe_title = trim($safe_title, '_');
    if (empty($safe_title)) {
        $safe_title = 'photo';
    }

    // Deduplicate filenames inside the ZIP
    $base_name = $safe_title . '.' . $ext;
    if (isset($file_counter[$base_name])) {
        $file_counter[$base_name]++;
        $zip_entry_name = $safe_folder_name . '/' . $safe_title . '_' . $file_counter[$base_name] . '.' . $ext;
    } else {
        $file_counter[$base_name] = 1;
        $zip_entry_name           = $safe_folder_name . '/' . $base_name;
    }

    $valid_files[] = [
        'path'     => $file_path,
        'zip_name' => $zip_entry_name,
        'photo_id' => $photo['id'],
    ];
}

if (empty($valid_files)) {
    _gz_json(false, ['error' => 'No valid files found'], 404);
}

if (!class_exists('ZipArchive')) {
    // ZipArchive not available – let the caller fall back to the streaming approach
    _gz_json(false, ['error' => 'ZipArchive not available on this server'], 501);
}

// ── Prepare zip_cache directory ───────────────────────────────────────────────

$zip_cache_dir = rtrim(UPLOAD_PATH, '/\\') . DIRECTORY_SEPARATOR . 'zip_cache';
if (!is_dir($zip_cache_dir)) {
    @mkdir($zip_cache_dir, 0755, true);
    @chmod($zip_cache_dir, 0755);
}

// Security guards (idempotent – safe to recreate if missing)
$_htaccess = $zip_cache_dir . DIRECTORY_SEPARATOR . '.htaccess';
// The marker line "# v2" distinguishes the Apache 2.4-compatible version from
// the old "Deny from all" (Apache 2.2) version.  We rewrite if missing or outdated
// so already-deployed servers heal automatically on the next ZIP generation.
$_htaccess_content = "# v2\nRequire all denied\nOptions -Indexes\n";
if (!file_exists($_htaccess) || strpos(@file_get_contents($_htaccess) ?: '', '# v2') === false) {
    @file_put_contents($_htaccess, $_htaccess_content);
}
$_guard = $zip_cache_dir . DIRECTORY_SEPARATOR . 'index.php';
if (!file_exists($_guard)) {
    @file_put_contents($_guard, "<?php http_response_code(403); exit;\n");
}

// ── Clean up expired ZIPs ─────────────────────────────────────────────────────

try {
    foreach (glob($zip_cache_dir . DIRECTORY_SEPARATOR . '*.meta.json') ?: [] as $meta_file) {
        $meta = json_decode(@file_get_contents($meta_file) ?: '{}', true);
        if (!empty($meta['expires_at']) && time() > (int)$meta['expires_at']) {
            $stale_zip = $zip_cache_dir . DIRECTORY_SEPARATOR
                       . basename($meta_file, '.meta.json') . '.zip';
            @unlink($stale_zip);
            @unlink($meta_file);
        }
    }
} catch (Throwable $e) {
    // Cleanup failure is non-fatal; continue with the new generation
}

// ── Build the ZIP ─────────────────────────────────────────────────────────────

@set_time_limit(0);
@ini_set('zlib.output_compression', '0');

$zip_token     = bin2hex(random_bytes(16));          // 32-char hex, cryptographically random
$zip_cache_file = $zip_cache_dir . DIRECTORY_SEPARATOR . $zip_token . '.zip';

$unique_id    = substr(uniqid(), -6);
$zip_filename = $safe_folder_name . '_' . date('Y-m-d') . '_' . $unique_id . '.zip';

try {
    $zip    = new ZipArchive();
    $opened = $zip->open($zip_cache_file, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    if ($opened !== true) {
        throw new RuntimeException('ZipArchive::open failed with code ' . $opened);
    }

    $added_count = 0;
    foreach ($valid_files as $file_info) {
        if ($zip->addFile($file_info['path'], $file_info['zip_name'])) {
            $added_count++;
        }
    }

    $zip_closed = $zip->close();

    // Ensure the file is readable by the web server even if the process umask
    // is restrictive (e.g. 0027 or 0077) — download-zip.php runs in a separate
    // request and needs read access via readfile().
    @chmod($zip_cache_file, 0644);

    if ($added_count === 0 || $zip_closed === false) {
        @unlink($zip_cache_file);
        _gz_json(false, ['error' => 'Failed to create ZIP (no files added)'], 500);
    }

    clearstatcache(true, $zip_cache_file);
    $zip_size = filesize($zip_cache_file);
    if ($zip_size === false || $zip_size === 0) {
        @unlink($zip_cache_file);
        _gz_json(false, ['error' => 'ZIP creation failed (empty file)'], 500);
    }

    // ── Store metadata alongside the ZIP ─────────────────────────────────────
    $photo_ids = array_column($valid_files, 'photo_id');
    $meta_data = [
        'folder_id'    => (int)$folder['id'],
        'zip_filename' => $zip_filename,
        'expires_at'   => time() + 7200, // 2 hours
        'photo_ids'    => $photo_ids,
        'added_count'  => $added_count,
    ];
    @file_put_contents(
        $zip_cache_dir . DIRECTORY_SEPARATOR . $zip_token . '.meta.json',
        json_encode($meta_data)
    );

    // ── Build the signed download URL ─────────────────────────────────────────
    // Resolve the base URL from the current request so this works regardless
    // of sub-directory deployments (e.g. /venubooking/).
    $scheme      = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host        = $_SERVER['HTTP_HOST'] ?? 'localhost';
    // SCRIPT_NAME is /api/generate-zip.php → depth-2 parent is the site root
    $script_dir  = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '', 2), '/');
    $download_url = $scheme . '://' . $host . $script_dir
                  . '/api/download-zip.php?zip_token=' . urlencode($zip_token)
                  . '&fileName=' . urlencode($zip_filename);

    _gz_json(true, [
        'url'      => $download_url,
        'filename' => $zip_filename,
        'size'     => (int)$zip_size,
    ]);
} catch (Throwable $e) {
    error_log('generate-zip: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    @unlink($zip_cache_file);
    _gz_json(false, ['error' => 'ZIP generation failed'], 500);
}
