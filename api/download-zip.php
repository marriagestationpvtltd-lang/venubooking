<?php
/**
 * Serve a pre-generated ZIP file using a signed token (TransferNow-style step 2).
 *
 * GET  ?zip_token=TOKEN[&fileName=photos.zip]
 *
 * The ZIP file must have been created by api/generate-zip.php.
 * It is served once and then deleted (along with its metadata sidecar).
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// ── Validate token ────────────────────────────────────────────────────────────

$zip_token = isset($_GET['zip_token']) ? trim($_GET['zip_token']) : '';

// Tokens are exactly 32 lowercase hex characters (bin2hex(random_bytes(16)))
if ($zip_token === '' || !preg_match('/^[a-f0-9]{32}$/', $zip_token)) {
    http_response_code(400);
    exit('Invalid download token.');
}

// ── Locate files ──────────────────────────────────────────────────────────────

$zip_cache_dir = rtrim(UPLOAD_PATH, '/\\') . DIRECTORY_SEPARATOR . 'zip_cache';
$zip_file      = $zip_cache_dir . DIRECTORY_SEPARATOR . $zip_token . '.zip';
$meta_file     = $zip_cache_dir . DIRECTORY_SEPARATOR . $zip_token . '.meta.json';

if (!file_exists($zip_file) || !file_exists($meta_file)) {
    http_response_code(404);
    exit('Download link not found or already used. Please request a new download.');
}

// ── Validate metadata / expiry ────────────────────────────────────────────────

$meta = json_decode(@file_get_contents($meta_file) ?: '{}', true);

if (empty($meta) || empty($meta['expires_at']) || time() > (int)$meta['expires_at']) {
    @unlink($zip_file);
    @unlink($meta_file);
    http_response_code(410);
    exit('This download link has expired. Please request a new download.');
}

// ── Determine filename ────────────────────────────────────────────────────────

$zip_filename = !empty($meta['zip_filename']) ? $meta['zip_filename'] : 'photos.zip';

// Allow override via the fileName query parameter embedded in the signed URL
if (!empty($_GET['fileName'])) {
    $candidate = basename(trim($_GET['fileName']));
    if ($candidate !== '' && preg_match('/\.zip$/i', $candidate)) {
        $zip_filename = $candidate;
    }
}

// ── Get file size ─────────────────────────────────────────────────────────────

clearstatcache(true, $zip_file);
$zip_size = filesize($zip_file);

if ($zip_size === false || $zip_size === 0) {
    error_log(sprintf(
        'download-zip: file is empty or unreadable (filesize=%s) for token %s, path %s',
        var_export($zip_size, true),
        $zip_token,
        $zip_file
    ));
    @unlink($zip_file);
    @unlink($meta_file);
    http_response_code(500);
    exit('ZIP file is empty or corrupted.');
}

// ── Update download statistics ────────────────────────────────────────────────

$db = getDB();

if (!empty($meta['photo_ids'])) {
    try {
        $photo_ids    = array_map('intval', (array)$meta['photo_ids']);
        $placeholders = implode(',', array_fill(0, count($photo_ids), '?'));
        $db->prepare(
            "UPDATE shared_photos SET download_count = download_count + 1 WHERE id IN ($placeholders)"
        )->execute($photo_ids);
    } catch (Throwable $e) {
        error_log('download-zip: download count update failed: ' . $e->getMessage());
    }
}

if (!empty($meta['folder_id']) && !empty($meta['added_count'])) {
    try {
        $db->prepare(
            "UPDATE shared_folders SET total_downloads = total_downloads + ? WHERE id = ?"
        )->execute([(int)$meta['added_count'], (int)$meta['folder_id']]);
    } catch (Throwable $e) {
        error_log('download-zip: folder total_downloads update failed: ' . $e->getMessage());
    }
}

// ── Stream the ZIP ────────────────────────────────────────────────────────────

// Release session lock so the user can continue browsing while the file downloads
session_write_close();

@set_time_limit(0);
@ini_set('zlib.output_compression', '0');

// Clear any output buffers that could corrupt the binary stream
while (ob_get_level() > 0) {
    if (!ob_end_clean()) {
        break;
    }
}

// apache_setenv() only exists when PHP runs as a mod_php Apache module.
// On PHP-FPM (and other SAPI backends), calling it throws an Error in PHP 8
// that is not silenced by @ and would reach the global exception handler.
// The .htaccess SetEnvIfNoCase rules already cover Apache mod_deflate/mod_brotli,
// so this call is only a belt-and-suspenders measure for mod_php environments.
if (function_exists('apache_setenv')) {
    apache_setenv('no-gzip', '1');
    apache_setenv('dont-vary', '1');
}

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . str_replace(['"', "\r", "\n"], ['\"', '', ''], $zip_filename) . '"');
header('Content-Length: ' . $zip_size);
header('Content-Transfer-Encoding: binary');
header('Content-Encoding: identity');
header('Accept-Ranges: bytes');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
flush();

readfile($zip_file);

// ── Clean up after serving ────────────────────────────────────────────────────

@unlink($zip_file);
@unlink($meta_file);

exit;
