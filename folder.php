<?php
/**
 * Public Folder Download Page
 * Users can view all photos and videos in a shared folder and download them
 * Features:
 * - View all photos and videos in a grid
 * - Download individual photos/videos
 * - Download ALL files as a single ZIP file (one-click download)
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/ZipStream.php';

$db = getDB();
$error_message = '';
$zip_error_message = ''; // Non-fatal error shown as alert on the folder page
$download_error_message = ''; // Non-fatal error for a single-photo download attempt
$folder = null;
$photos = [];

// Sub-folder/album navigation: current album selected by user (null = top-level view)
$current_album = isset($_GET['album']) ? trim($_GET['album']) : null;
// Empty string is treated as "no album selected" (top-level view)
if ($current_album === '') {
    $current_album = null;
}

// Get download token from URL
$token = isset($_GET['token']) ? trim($_GET['token']) : '';

if (empty($token)) {
    $error_message = 'Invalid folder link.';
} else {
    try {
        // Fetch folder by token
        $stmt = $db->prepare("SELECT * FROM shared_folders WHERE download_token = ?");
        $stmt->execute([$token]);
        $folder = $stmt->fetch();
        
        if (!$folder) {
            $error_message = 'Folder not found. The link may be invalid or expired.';
        } elseif ($folder['status'] === 'inactive' || $folder['status'] === 'expired') {
            $error_message = 'This folder link is no longer active.';
        } elseif ($folder['expires_at'] && strtotime($folder['expires_at']) < time()) {
            $error_message = 'This folder link has expired.';
            // Update status to expired
            $update_stmt = $db->prepare("UPDATE shared_folders SET status = 'expired' WHERE id = ?");
            $update_stmt->execute([$folder['id']]);
        } else {
            // Fetch all active photos in this folder
            $photos_stmt = $db->prepare("SELECT * FROM shared_photos WHERE folder_id = ? AND status = 'active' ORDER BY COALESCE(subfolder_name,'') ASC, created_at DESC");
            $photos_stmt->execute([$folder['id']]);
            $photos = $photos_stmt->fetchAll();

            // Filter out records whose physical files are missing from disk.
            // This prevents broken-image 404 errors on the public folder page when a
            // file has been removed from storage but its database record still exists.
            // As a side-effect, also ensure existing files have world-readable permissions
            // so that Apache/Nginx can serve them (fixes the "Image unavailable" issue
            // caused by files uploaded with restrictive umask on some shared-hosting servers).
            $real_upload_base = realpath(UPLOAD_PATH);
            $photos = array_values(array_filter($photos, function ($photo) use ($real_upload_base) {
                if (empty($photo['image_path'])) {
                    return false;
                }
                // Basic path-traversal guard regardless of realpath availability
                $safe_path = str_replace('\\', '/', $photo['image_path']);
                if (strpos($safe_path, '../') !== false || strpos($safe_path, '/..') !== false || $safe_path[0] === '/') {
                    return false;
                }
                $abs_path = UPLOAD_PATH . $photo['image_path'];
                if ($real_upload_base !== false) {
                    $real_path = realpath($abs_path);
                    if ($real_path === false || strpos($real_path, $real_upload_base . DIRECTORY_SEPARATOR) !== 0 || !file_exists($abs_path)) {
                        return false;
                    }
                    // Fix permissions if file is not world-readable
                    if ((fileperms($abs_path) & 0004) !== 0004) {
                        @chmod($abs_path, 0644);
                    }
                    return true;
                }
                // Fallback when realpath() cannot resolve the uploads base path
                if (!file_exists($abs_path)) {
                    return false;
                }
                // Fix permissions if file is not world-readable
                if ((fileperms($abs_path) & 0004) !== 0004) {
                    @chmod($abs_path, 0644);
                }
                return true;
            }));
        }
    } catch (Throwable $e) {
        error_log('Folder page error: ' . $e->getMessage());
        $error_message = 'Unable to load folder. Please try again later.';
    }
}

// Determine if this folder uses sub-folder organisation
$has_subfolders = false;
$subfolders = []; // [ name => [ photos ] ]
if (!$error_message && $folder) {
    foreach ($photos as $photo) {
        $sf = (isset($photo['subfolder_name']) && $photo['subfolder_name'] !== null && $photo['subfolder_name'] !== '')
            ? $photo['subfolder_name'] : null;
        if ($sf !== null) {
            $has_subfolders = true;
        }
        $key = $sf ?? '';
        $subfolders[$key][] = $photo;
    }
    ksort($subfolders);
}

// Photos shown in the current view (depends on mode)
$visible_photos = [];
if ($has_subfolders && $current_album !== null) {
    // Drill-down view: show only photos in the selected album
    $visible_photos = $subfolders[$current_album] ?? [];
} elseif (!$has_subfolders) {
    // Flat view (no sub-folders at all): show all photos
    $visible_photos = $photos;
}
// If $has_subfolders && $current_album === null → top-level subfolder card view (no $visible_photos needed)

// Shared MIME-type → file-extension map used when generating download filenames.
// Using the server-detected MIME type (rather than the stored image_path extension)
// ensures the downloaded file always has an extension that matches its real format.
// Files uploaded via chunk or transfer handlers preserve the original filename
// extension which may not match the actual file content.
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

// Handle individual photo download
if (!$error_message && isset($_GET['download_photo']) && is_numeric($_GET['download_photo'])) {
    try {
        $photo_id = intval($_GET['download_photo']);
        
        // Find the photo
        $photo_stmt = $db->prepare("SELECT * FROM shared_photos WHERE id = ? AND folder_id = ? AND status = 'active'");
        $photo_stmt->execute([$photo_id, $folder['id']]);
        $photo = $photo_stmt->fetch();
        
        if ($photo) {
            // Check max downloads if set
            if ($folder['max_downloads'] && $photo['download_count'] >= $folder['max_downloads']) {
                $download_error_message = 'Maximum download limit reached for this photo.';
            } else {
                $file_path = UPLOAD_PATH . $photo['image_path'];
                
                // Security: Verify file is within uploads directory.
                // Uses realpath() for strict boundary checking when available.
                // Falls back to a path-traversal guard when realpath() cannot
                // resolve paths (e.g. open_basedir restrictions on shared hosting).
                $real_upload_path = realpath(UPLOAD_PATH);
                $real_file_path   = realpath($file_path);

                $is_safe_path = false;
                if ($real_file_path !== false && $real_upload_path !== false) {
                    $is_safe_path = strpos($real_file_path, rtrim($real_upload_path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR) === 0
                                    && file_exists($file_path);
                } elseif (file_exists($file_path)) {
                    // realpath() unavailable: use same path-traversal guard as the
                    // photo filter applied on page load (lines 66-70).
                    $safe_p = str_replace('\\', '/', $photo['image_path']);
                    $is_safe_path = $safe_p !== ''
                                    && strpos($safe_p, '../') === false
                                    && strpos($safe_p, '/..') === false
                                    && $safe_p[0] !== '/';
                }

                if ($is_safe_path) {
                    // Detect MIME type using robust helper (handles missing/broken finfo extension)
                    $mime_type = detectMimeType($file_path);

                    // Generate download filename.
                    // Derive the extension from the actual detected MIME type so that the
                    // downloaded file always has an extension that matches its real format.
                    // Files uploaded via chunk or transfer handlers store the original
                    // filename extension (e.g. .jpg for a file that is actually WebP), so
                    // relying solely on image_path would produce a format mismatch.
                    $ext = pathinfo($photo['image_path'], PATHINFO_EXTENSION);
                    if (!empty($mime_ext_map[$mime_type])) {
                        $ext = $mime_ext_map[$mime_type];
                    }
                    $safe_title = preg_replace('/[^a-zA-Z0-9_\-\.\s]/u', '_', $photo['title']);
                    $safe_title = preg_replace('/_+/', '_', $safe_title);
                    $safe_title = trim($safe_title, '_');
                    $download_filename = (!empty($safe_title) ? $safe_title : 'photo') . '.' . $ext;
                    
                    // Prepare for large file download
                    // Disable time limit for large file transfers
                    @set_time_limit(0);

                    // Disable PHP's zlib output compression first — must be done
                    // before clearing output buffers so the hidden zlib layer is
                    // removed before we flush any content to the client.
                    @ini_set('zlib.output_compression', '0');

                    // Disable ALL output buffering levels (there can be more than
                    // one: PHP's own ob layer plus a possible zlib/gzip handler).
                    while (ob_get_level()) {
                        ob_end_clean();
                    }

                    // Prevent Apache/Nginx from re-compressing the file response.
                    @apache_setenv('no-gzip', '1');
                    @apache_setenv('dont-vary', '1');

                    // Get file size as an unsigned value to handle files larger than 2 GB
                    // correctly on 32-bit PHP builds where filesize() can overflow.
                    $file_size = sprintf('%u', filesize($file_path));

                    // ── HTTP Range (resumable download) support ──────────────────────────
                    // Mirrors the chunked-upload approach: the client can request any byte
                    // range so interrupted downloads can be resumed without restarting.
                    $range_start  = 0;
                    $range_end    = (int)$file_size - 1;
                    $is_range_req = false;

                    $http_range = isset($_SERVER['HTTP_RANGE']) ? trim($_SERVER['HTTP_RANGE']) : '';
                    if ($http_range !== '') {
                        if (preg_match('/^bytes=(\d*)-(\d*)$/i', $http_range, $rm)) {
                            $req_start = ($rm[1] !== '') ? (int)$rm[1] : 0;
                            $req_end   = ($rm[2] !== '') ? (int)$rm[2] : (int)$file_size - 1;

                            if ($req_end >= (int)$file_size) {
                                $req_end = (int)$file_size - 1;
                            }

                            if ($req_start > $req_end || $req_start >= (int)$file_size) {
                                header('HTTP/1.1 416 Requested Range Not Satisfiable');
                                header('Content-Range: bytes */' . $file_size);
                                exit;
                            }

                            $range_start  = $req_start;
                            $range_end    = $req_end;
                            $is_range_req = true;
                        }
                    }

                    $content_length = $range_end - $range_start + 1;

                    // Only count a new download for the first (or only) request.
                    // Wrapped in its own try-catch: a missing column (e.g. total_downloads
                    // on installations upgraded before the column was added) or any other
                    // DB error must never prevent the actual file from being served.
                    if (!$is_range_req || $range_start === 0) {
                        try {
                            $update_stmt = $db->prepare("UPDATE shared_photos SET download_count = download_count + 1 WHERE id = ?");
                            $update_stmt->execute([$photo['id']]);
                        } catch (Throwable $e) {
                            error_log('Download count update (photo) failed: ' . $e->getMessage());
                        }
                        try {
                            // Increment folder total downloads
                            $db->prepare("UPDATE shared_folders SET total_downloads = total_downloads + 1 WHERE id = ?")->execute([$folder['id']]);
                        } catch (Throwable $e) {
                            error_log('Download count update (folder total_downloads) failed: ' . $e->getMessage());
                        }
                    }

                    // Release the PHP session lock before the potentially long file
                    // transfer so that other requests from the same user are not blocked
                    // while the download is in progress.
                    session_write_close();

                    // Send response headers
                    header('Content-Type: ' . $mime_type);
                    header('Content-Disposition: attachment; filename="' . $download_filename . '"');
                    header('Content-Transfer-Encoding: binary');
                    // Tell proxies/servers not to encode (compress) the response;
                    // this ensures the browser receives the exact byte count and
                    // that the download starts immediately without buffering.
                    header('Content-Encoding: identity');
                    // Advertise range support so browsers and download managers know
                    // they can resume interrupted transfers.
                    header('Accept-Ranges: bytes');
                    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
                    header('Cache-Control: post-check=0, pre-check=0', false);
                    header('Pragma: no-cache');
                    header('Expires: 0');

                    if ($is_range_req) {
                        header('HTTP/1.1 206 Partial Content');
                        header('Content-Range: bytes ' . $range_start . '-' . $range_end . '/' . $file_size);
                        header('Content-Length: ' . $content_length);
                    } else {
                        header('Content-Length: ' . $file_size);
                    }

                    // Flush headers to browser immediately
                    flush();

                    // Stream the requested byte range in 1 MB chunks.
                    $handle = fopen($file_path, 'rb');
                    if ($handle !== false) {
                        if ($range_start > 0) {
                            fseek($handle, $range_start);
                        }
                        $remaining  = $content_length;
                        $chunk_size = 1024 * 1024; // 1 MB
                        while ($remaining > 0 && !feof($handle)) {
                            $read = min($chunk_size, $remaining);
                            echo fread($handle, $read);
                            $remaining -= $read;
                            // Flush output buffer to send data immediately
                            flush();
                            // Check if connection is still alive
                            if (connection_aborted()) {
                                break;
                            }
                        }
                        fclose($handle);
                    } else {
                        // Log error if file cannot be opened
                        error_log('Failed to open file for streaming: ' . $file_path);
                    }
                    exit;
                } else {
                    $download_error_message = 'File not found.';
                }
            }
        } else {
            $download_error_message = 'Photo not found or no longer available.';
        }
    } catch (Throwable $e) {
        error_log('Photo download error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
        // headers_sent() is true only when an exception occurs AFTER flush() has
        // already pushed the Content-Type/binary headers to the browser (e.g. a DB
        // error mid-stream).  In that case we cannot safely send an HTML page, so
        // we stop immediately.  When the exception occurs before flush() (e.g. a
        // PDOException from the download-count update), headers_sent() is false and
        // we fall through to show a user-friendly error on the folder page instead.
        if (headers_sent()) {
            // Cannot output HTML after binary headers were sent – just stop.
            exit;
        }
        $download_error_message = 'Download failed. Please try again.';
    }
}

// Handle Download All as ZIP - Using streaming for instant downloads (like Google Drive)
// For selection-based downloads (specific photo IDs provided via &ids=), allow ZIP even
// when the folder's allow_zip_download flag is disabled so that "Download Now" for
// multiple selected photos always produces a single ZIP file (one save dialog, no blob
// corruption).  Require at least one valid positive integer to be present in ids so
// that an empty or malformed param does not inadvertently bypass the setting.
$_has_selected_ids = !empty($_GET['ids']) && preg_match('/\d/', (string)$_GET['ids']);
if (!$error_message && isset($_GET['download_all']) && $_GET['download_all'] === '1' && ($folder['allow_zip_download'] || $_has_selected_ids)) {
    // Outer try-catch covers the entire ZIP handler so that any unexpected
    // exception (e.g. a PHP 8 TypeError from a missing column, a PDOException
    // from the download-count update, or an I/O error reading the temp file)
    // is caught here instead of reaching the global exception handler in
    // production.php which would return an HTTP 500 response to the browser.
    try {
    // Disable PHP execution time limit as early as possible so the file-validation
    // loop (which calls detectMimeType() for every photo) cannot timeout on large
    // folders before the ZIP creation even starts.  This is especially important on
    // shared-hosting servers where the default max_execution_time is 30–60 seconds.
    @set_time_limit(0);

    // When inside an album, only ZIP photos from that album; otherwise ZIP everything
    $photos_to_zip = ($has_subfolders && $current_album !== null)
        ? $visible_photos
        : $photos;

    // Optional: filter to only selected photo IDs (passed as comma-separated integers)
    if (!empty($_GET['ids'])) {
        $filter_ids = [];
        foreach (explode(',', $_GET['ids']) as $_raw_id) {
            $_id = intval(trim($_raw_id));
            if ($_id > 0) {
                $filter_ids[] = $_id;
            }
        }
        if (!empty($filter_ids)) {
            $photos_to_zip = array_values(array_filter($photos_to_zip, function ($p) use ($filter_ids) {
                return in_array((int)$p['id'], $filter_ids, true);
            }));
        }
    }

    if (empty($photos_to_zip)) {
        $zip_error_message = 'No photos to download.';
    } else {
        // Create safe folder name for ZIP
        $safe_folder_name = preg_replace('/[^a-zA-Z0-9_\-\s]/u', '_', $folder['folder_name']);
        $safe_folder_name = preg_replace('/_+/', '_', $safe_folder_name);
        $safe_folder_name = trim($safe_folder_name, '_');
        if (empty($safe_folder_name)) {
            $safe_folder_name = 'photos';
        }
        
        // Generate unique filename for the ZIP download
        $unique_id = substr(uniqid(), -6);
        $zip_filename = $safe_folder_name . '_' . date('Y-m-d') . '_' . $unique_id . '.zip';
        
        // Pre-validate all files before starting the stream
        // This ensures we don't start a download that will fail mid-stream
        $valid_files = [];
        $real_upload_path = realpath(UPLOAD_PATH);
        $file_counter = [];

        foreach ($photos_to_zip as $photo) {
            $file_path = UPLOAD_PATH . $photo['image_path'];
            $real_file_path = realpath($file_path);

            // Security check: verify file is within uploads directory.
            // Falls back to a path-traversal guard when realpath() cannot resolve
            // paths (e.g. open_basedir restrictions on shared hosting).
            $file_is_safe = false;
            if ($real_file_path !== false && $real_upload_path !== false) {
                $file_is_safe = strpos($real_file_path, rtrim($real_upload_path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR) === 0
                                && file_exists($file_path);
            } elseif (file_exists($file_path)) {
                $safe_p = str_replace('\\', '/', $photo['image_path']);
                $file_is_safe = $safe_p !== ''
                                && strpos($safe_p, '../') === false
                                && strpos($safe_p, '/..') === false
                                && $safe_p[0] !== '/';
            }

            if ($file_is_safe) {
                // Derive extension from actual file MIME type so ZIP entries have
                // the correct extension even if image_path used the original
                // uploaded filename extension (which may not match the content).
                $ext = pathinfo($photo['image_path'], PATHINFO_EXTENSION);
                $actual_mime = detectMimeType($file_path);
                if (!empty($mime_ext_map[$actual_mime])) {
                    $ext = $mime_ext_map[$actual_mime];
                }

                // Generate safe filename for inside ZIP
                $safe_title = preg_replace('/[^a-zA-Z0-9_\-\.\s]/u', '_', $photo['title']);
                $safe_title = preg_replace('/_+/', '_', $safe_title);
                $safe_title = trim($safe_title, '_');
                if (empty($safe_title)) {
                    $safe_title = 'photo';
                }
                
                // Handle duplicate filenames by adding counter
                $base_name = $safe_title . '.' . $ext;
                if (isset($file_counter[$base_name])) {
                    $file_counter[$base_name]++;
                    $zip_entry_name = $safe_folder_name . '/' . $safe_title . '_' . $file_counter[$base_name] . '.' . $ext;
                } else {
                    $file_counter[$base_name] = 1;
                    $zip_entry_name = $safe_folder_name . '/' . $base_name;
                }
                
                $valid_files[] = [
                    'path' => $file_path,
                    'zip_name' => $zip_entry_name,
                    'photo_id' => $photo['id']
                ];
            }
        }
        
        if (empty($valid_files)) {
            $zip_error_message = 'No valid files to download.';
        } else {
            // Disable output compression before any ZIP work begins so neither the
            // build step (ZipArchive) nor the streaming step (ZipStream) is
            // interrupted.
            @ini_set('zlib.output_compression', '0');
            $ob_max = ob_get_level() + 1; // safety cap
            while (ob_get_level() > 0 && --$ob_max > 0) {
                if (!ob_end_clean()) {
                    break;
                }
            }
            @apache_setenv('no-gzip', '1');
            @apache_setenv('dont-vary', '1');

            // Prefer ZipArchive (PHP built-in) – creates the ZIP to a temp file then
            // serves it with readfile().  This is more reliable than streaming on
            // shared-hosting servers where output buffering / gzip middleware can
            // corrupt a live binary stream.  Falls back to the custom ZipStream
            // streamer when the zip extension is not available.
            if (class_exists('ZipArchive')) {
                // ── ZipArchive path ──────────────────────────────────────────────
                // Prefer uploads/zip_cache/ as the temp directory because it is
                // guaranteed to be writable (photos are already uploaded there) and
                // avoids open_basedir restrictions that often block sys_get_temp_dir()
                // on shared-hosting servers.
                $_zip_cache_dir = rtrim(UPLOAD_PATH, '/\\') . DIRECTORY_SEPARATOR . 'zip_cache';
                if (!is_dir($_zip_cache_dir)) {
                    @mkdir($_zip_cache_dir, 0755, true);
                    // Protect generated ZIPs from direct HTTP access on Apache servers
                    $_htaccess_path = $_zip_cache_dir . DIRECTORY_SEPARATOR . '.htaccess';
                    if (!file_exists($_htaccess_path)) {
                        @file_put_contents($_htaccess_path, "Deny from all\nOptions -Indexes\n");
                    }
                    // Universal guard for nginx and other servers: serve a 403 for any request
                    $_guard_path = $_zip_cache_dir . DIRECTORY_SEPARATOR . 'index.php';
                    if (!file_exists($_guard_path)) {
                        @file_put_contents($_guard_path, "<?php http_response_code(403); exit;\n");
                    }
                }
                $_zip_tmp_dir = (is_dir($_zip_cache_dir) && is_writable($_zip_cache_dir))
                    ? $_zip_cache_dir
                    : sys_get_temp_dir();
                $temp_zip = @tempnam($_zip_tmp_dir, 'vb_zip_');
                if ($temp_zip === false) {
                    $zip_error_message = 'ZIP download failed. Click the Download Now button to try downloading photos individually.';
                } else {
                    try {
                        $zip = new ZipArchive();
                        $opened = $zip->open($temp_zip, ZipArchive::CREATE | ZipArchive::OVERWRITE);
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

                        if ($added_count === 0) {
                            @unlink($temp_zip);
                            $zip_error_message = 'ZIP download failed. Click the Download Now button to try downloading photos individually.';
                        } elseif ($zip_closed === false) {
                            @unlink($temp_zip);
                            error_log('ZIP download error: ZipArchive::close() returned false for ' . $temp_zip);
                            $zip_error_message = 'ZIP download failed. Click the Download Now button to try downloading photos individually.';
                        } else {
                            // Clear PHP's stat cache so filesize() reads the actual on-disk
                            // size of the freshly-written ZIP and does not return a stale 0.
                            clearstatcache(true, $temp_zip);
                            $zip_size = filesize($temp_zip);
                            if ($zip_size === false || $zip_size === 0) {
                                @unlink($temp_zip);
                                $zip_error_message = 'ZIP download failed. Click the Download Now button to try downloading photos individually.';
                            } else {

                                // Release session lock before streaming so other
                                // requests from the same user are not blocked during
                                // the potentially long file transfer.
                                session_write_close();

                                header('Content-Type: application/zip');
                                header('Content-Disposition: attachment; filename="' . $zip_filename . '"');
                                header('Content-Length: ' . $zip_size);
                                header('Content-Transfer-Encoding: binary');
                                header('Content-Encoding: identity');
                                header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
                                header('Cache-Control: post-check=0, pre-check=0', false);
                                header('Pragma: no-cache');
                                header('Expires: 0');
                                flush();

                                readfile($temp_zip);
                                @unlink($temp_zip);

                                // Batch-update download counts in a single query
                                $photo_ids = array_column($valid_files, 'photo_id');
                                $placeholders = implode(',', array_fill(0, count($photo_ids), '?'));
                                $db->prepare("UPDATE shared_photos SET download_count = download_count + 1 WHERE id IN ($placeholders)")->execute($photo_ids);
                                $db->prepare("UPDATE shared_folders SET total_downloads = total_downloads + ? WHERE id = ?")->execute([$added_count, $folder['id']]);

                                exit;
                            }
                        }
                    } catch (Throwable $e) {
                        error_log('ZIP download error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
                        @unlink($temp_zip);
                        if (headers_sent()) {
                            exit;
                        }
                        $zip_error_message = 'ZIP download failed. Click the Download Now button to try downloading photos individually.';
                    }
                }
            } else {
                // ── ZipStream fallback (streaming, no temp file) ─────────────────
                $zip_stream_started = false;

                try {
                    $zipStream = new ZipStream($zip_filename);
                    // Release session lock before streaming starts so other
                    // requests from the same user are not blocked during the
                    // potentially long file transfer.
                    session_write_close();
                    $zipStream->begin();
                    $zip_stream_started = true;

                    $added_count = 0;
                    foreach ($valid_files as $file_info) {
                        if ($zipStream->addFile($file_info['path'], $file_info['zip_name'])) {
                            $added_count++;
                            $update_stmt = $db->prepare("UPDATE shared_photos SET download_count = download_count + 1 WHERE id = ?");
                            $update_stmt->execute([$file_info['photo_id']]);
                        }
                        if (connection_aborted()) {
                            break;
                        }
                    }

                    $zipStream->finish();

                    if ($added_count > 0) {
                        $db->prepare("UPDATE shared_folders SET total_downloads = total_downloads + ? WHERE id = ?")->execute([$added_count, $folder['id']]);
                    }

                    exit;
                } catch (Throwable $e) {
                    error_log('ZIP download error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
                    if ($zip_stream_started || headers_sent()) {
                        exit;
                    }
                    $zip_error_message = 'ZIP download failed. Click the Download Now button to try downloading photos individually.';
                }
            }
        }
    }
    } catch (Throwable $e) {
        // Outer catch: handles any exception not caught by the inner try blocks
        // above (e.g. PHP 8 TypeErrors from missing DB columns, unexpected I/O
        // errors during temp-file creation, or any other unforeseen runtime error).
        error_log('ZIP download (outer) error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
        if (headers_sent()) {
            // ZIP streaming already started – cannot safely output HTML.
            exit;
        }
        $zip_error_message = 'ZIP download failed. Click the Download Now button to try downloading photos individually.';
    }
}

// Get site settings
$site_name = getSetting('site_name') ?: 'Photo Folder';
$site_logo = getSetting('site_logo');
$contact_phone = getSetting('contact_phone');
$contact_email = getSetting('contact_email');
$whatsapp_number = getSetting('whatsapp_number');

// Get banner ad settings
$banner_a_image = getSetting('folder_banner_a');
$banner_a_link = getSetting('folder_banner_a_link');
$banner_a_enabled = getSetting('folder_banner_a_enabled') === '1';
$banner_b_image = getSetting('folder_banner_b');
$banner_b_link = getSetting('folder_banner_b_link');
$banner_b_enabled = getSetting('folder_banner_b_enabled') === '1';

// WhatsApp deletion request message (bilingual) with folder reference
$_whatsapp_folder_name = !empty($folder['folder_name']) ? strip_tags($folder['folder_name']) : '';
$_whatsapp_folder_url  = BASE_URL . '/folder.php?token=' . urlencode($token);
$whatsapp_delete_message  = "Photo Deletion Request\n\n";
if (!empty($_whatsapp_folder_name)) {
    $whatsapp_delete_message .= "Folder: *" . $_whatsapp_folder_name . "*\n";
    $whatsapp_delete_message .= "Link: " . $_whatsapp_folder_url . "\n\n";
}
$whatsapp_delete_message .= "I have downloaded my photos and request that they be removed from your system for privacy reasons.\n\n";
$whatsapp_delete_message .= "Thank you";
$whatsapp_delete_url = '';
if ($whatsapp_number) {
    $clean_whatsapp = preg_replace('/[^0-9]/', '', $whatsapp_number);
    $whatsapp_delete_url = 'https://wa.me/' . $clean_whatsapp . '?text=' . rawurlencode($whatsapp_delete_message);
}

// Build lists of download URLs for bulk individual download (no ZIP)
// $bulk_all_urls   – all photos in this folder (used in flat view & top-level subfolder view)
// $bulk_album_urls – only photos in the currently selected album (used in album drill-down view)
// $bulk_all_files  – same as above but as [{url, filename}] for File System Access API downloads
// $bulk_album_files – same but for current album

/**
 * Generate a unique safe filename for a photo, deduplicating against $seen.
 * $seen is passed by reference and updated with the new name.
 */
function _safe_photo_filename($title, $ext, array &$seen) {
    $safe  = preg_replace('/[^a-zA-Z0-9_\-\.\s]/u', '_', $title);
    $safe  = preg_replace('/_+/', '_', $safe);
    $safe  = trim($safe, '_');
    $base  = !empty($safe) ? $safe : 'photo';
    $fname = $base . '.' . $ext;
    if (isset($seen[$fname])) {
        $seen[$fname]++;
        $fname = $base . '_' . $seen[$fname] . '.' . $ext;
    } else {
        $seen[$fname] = 1;
    }
    return $fname;
}

$bulk_all_urls    = [];
$bulk_album_urls  = [];
$bulk_all_files   = [];
$bulk_album_files = [];
if ($folder && !$error_message) {
    $_seen_names_all = [];
    foreach ($photos as $_p) {
        if (!$folder['max_downloads'] || $_p['download_count'] < $folder['max_downloads']) {
            $_url = '?token=' . urlencode($token) . '&download_photo=' . $_p['id'];
            $bulk_all_urls[] = $_url;
            $_ext   = strtolower(pathinfo($_p['image_path'], PATHINFO_EXTENSION));
            $bulk_all_files[] = [
                'url'      => $_url,
                'filename' => _safe_photo_filename($_p['title'], $_ext, $_seen_names_all),
            ];
        }
    }
    $_seen_names_album = [];
    foreach ($visible_photos as $_p) {
        if (!$folder['max_downloads'] || $_p['download_count'] < $folder['max_downloads']) {
            $_url = '?token=' . urlencode($token) . '&download_photo=' . $_p['id'];
            $bulk_album_urls[] = $_url;
            $_ext   = strtolower(pathinfo($_p['image_path'], PATHINFO_EXTENSION));
            $bulk_album_files[] = [
                'url'      => $_url,
                'filename' => _safe_photo_filename($_p['title'], $_ext, $_seen_names_album),
            ];
        }
    }
}

/**
 * Render a photo grid for the given array of photos.
 */
function renderPhotoGrid(array $photos, string $token, array $folder, array $image_extensions, array $video_extensions): void {
    if (empty($photos)) {
        echo '<div class="text-center py-5" style="color:var(--text-secondary);"><i class="fas fa-photo-video fa-4x mb-3" style="opacity:0.35;"></i><p>No files in this folder yet.</p></div>';
        return;
    }
    echo '<div class="photo-grid">';
    foreach ($photos as $photo) {
        $file_url    = UPLOAD_URL . $photo['image_path'];
        $preview_url = (!empty($photo['thumbnail_path'])) ? UPLOAD_URL . $photo['thumbnail_path'] : $file_url;
        $pf_ext      = strtolower(pathinfo($photo['image_path'], PATHINFO_EXTENSION));
        $is_video    = isset($photo['file_type']) && $photo['file_type'] === 'video';
        $is_generic  = isset($photo['file_type']) && $photo['file_type'] === 'file';
        if ($is_generic && in_array($pf_ext, $image_extensions)) { $is_generic = false; }
        if ($is_generic && in_array($pf_ext, $video_extensions)) { $is_generic = false; $is_video = true; }
        $can_download    = !$folder['max_downloads'] || $photo['download_count'] < $folder['max_downloads'];
        $pf_icon         = getFileTypeIcon($pf_ext);
        $download_url_qs = '?token=' . urlencode($token) . '&download_photo=' . $photo['id'];
        $photo_title_js  = json_encode($photo['title'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        $dl_attr         = $can_download ? ' data-download-url="' . htmlspecialchars($download_url_qs, ENT_QUOTES, 'UTF-8') . '"' : '';

        echo '<div class="photo-card"' . $dl_attr . ' onclick="handleCardClick(this,event)">';
        echo '<div class="photo-media">';

        if ($can_download) {
            echo '<div class="photo-select-overlay" onclick="event.stopPropagation();togglePhotoSelection(this.closest(\'.photo-card\'))">';
            echo '<input type="checkbox" class="photo-checkbox" aria-label="Select" onclick="event.stopPropagation();togglePhotoSelection(this.closest(\'.photo-card\'))">';
            echo '</div>';
        }

        if ($is_video) {
            $dl_json = $can_download ? json_encode($download_url_qs, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) : 'null';
            echo '<div class="video-container" onclick="handleMediaClick(event,function(){openVideoLightbox(\'' . htmlspecialchars($file_url, ENT_QUOTES, 'UTF-8') . '\',' . $dl_json . ',' . $photo_title_js . ');})">';
            echo '<div class="video-play-overlay"><i class="fas fa-play-circle"></i></div>';
            echo '<span class="badge bg-danger file-type-badge">VIDEO</span>';
            echo '</div>';
        } elseif ($is_generic) {
            echo '<div class="video-container d-flex flex-column align-items-center justify-content-center" style="background:#f8f9fa;">';
            echo '<i class="fas ' . $pf_icon . '" style="font-size:4rem;color:#888;"></i>';
            echo '<small class="mt-2 text-muted text-uppercase" style="font-size:0.8rem;">' . htmlspecialchars($pf_ext ?: 'FILE') . '</small>';
            echo '<span class="badge bg-secondary file-type-badge">FILE</span>';
            echo '</div>';
        } else {
            $dl_json = $can_download ? json_encode($download_url_qs, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) : 'null';
            echo '<img src="' . htmlspecialchars($preview_url, ENT_QUOTES, 'UTF-8') . '"';
            echo ' alt="' . htmlspecialchars($photo['title'], ENT_QUOTES, 'UTF-8') . '"';
            echo ' onclick="handleMediaClick(event,function(){openLightbox(\'' . htmlspecialchars($file_url, ENT_QUOTES, 'UTF-8') . '\',' . $dl_json . ',' . $photo_title_js . ');})"';
            echo ' loading="lazy" class="lazy-img" onload="this.classList.add(\'loaded\')" onerror="handleImageError(this)" style="cursor:pointer;">';
        }

        if ($can_download) {
            echo '<a href="' . htmlspecialchars($download_url_qs, ENT_QUOTES, 'UTF-8') . '" class="photo-media-download" title="Download" onclick="event.stopPropagation();singlePhotoDownload(this.href,' . $photo_title_js . ');return false;"><i class="fas fa-arrow-down"></i></a>';
        } else {
            echo '<span class="photo-media-download dl-limit-reached" title="Download limit reached"><i class="fas fa-ban"></i></span>';
        }

        echo '</div>';
        echo '<div class="photo-info"><div class="photo-title" title="' . htmlspecialchars($photo['title']) . '">' . htmlspecialchars($photo['title']) . '</div></div>';
        echo '</div>';
    }
    echo '</div>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php
        if ($folder) {
            echo htmlspecialchars($folder['folder_name']);
            if ($has_subfolders && $current_album !== null) {
                echo ' › ' . htmlspecialchars($current_album === '' ? 'General' : $current_album);
            }
        } else {
            echo 'Folder';
        }
        echo ' - ' . htmlspecialchars($site_name);
    ?></title>
    <link href="<?= BASE_URL ?>/admin/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/admin/vendor/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/css/share.css">
</head>
<body>
<?php
$show_banner_a = $banner_a_enabled && !empty($banner_a_image) && file_exists(UPLOAD_PATH . $banner_a_image);
$show_banner_b = $banner_b_enabled && !empty($banner_b_image) && file_exists(UPLOAD_PATH . $banner_b_image);
$has_any_banner = $show_banner_a || $show_banner_b;
?>
<?php if ($has_any_banner): ?>
<div class="page-wrapper">
    <?php if ($show_banner_a): ?>
    <div class="banner-ad banner-ad-desktop">
        <?php if (!empty($banner_a_link)): ?>
        <a href="<?= htmlspecialchars($banner_a_link) ?>" target="_blank" rel="noopener noreferrer">
            <img src="<?= UPLOAD_URL . htmlspecialchars($banner_a_image) ?>" alt="Sponsored Banner">
        </a>
        <?php else: ?>
        <img src="<?= UPLOAD_URL . htmlspecialchars($banner_a_image) ?>" alt="Sponsored Banner">
        <?php endif; ?>
        <div class="banner-ad-label">Sponsored</div>
    </div>
    <?php endif; ?>
    <div class="main-content">
<?php endif; ?>

<div class="folder-container">
<?php if ($error_message): ?>
    <div class="error-container">
        <div class="error-icon"><i class="fas fa-exclamation-circle"></i></div>
        <h3 class="fw-bold mb-2" style="color:var(--text-primary);">Access Denied</h3>
        <p class="mb-4" style="color:var(--text-secondary);"><?= htmlspecialchars($error_message) ?></p>
        <p style="color:var(--text-secondary);"><small>If you believe this is an error, please contact the sender.</small></p>
    </div>
<?php else: ?>

<?php if ($zip_error_message): ?>
<div class="alert alert-warning alert-dismissible d-flex align-items-center gap-2 mb-3" role="alert">
    <i class="fas fa-exclamation-triangle"></i>
    <span><?= htmlspecialchars($zip_error_message) ?></span>
    <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<?php if ($download_error_message): ?>
<div class="alert alert-warning alert-dismissible d-flex align-items-center gap-2 mb-3" role="alert">
    <i class="fas fa-exclamation-triangle"></i>
    <span><?= htmlspecialchars($download_error_message) ?></span>
    <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<?php if ($show_banner_a): ?>
<div class="mobile-banner-top">
    <div class="mobile-banner-item">
        <?php if (!empty($banner_a_link)): ?>
        <a href="<?= htmlspecialchars($banner_a_link) ?>" target="_blank" rel="noopener noreferrer">
            <img src="<?= UPLOAD_URL . htmlspecialchars($banner_a_image) ?>" alt="Sponsored Banner" loading="lazy">
            <span class="banner-badge">Ad</span>
        </a>
        <?php else: ?>
        <img src="<?= UPLOAD_URL . htmlspecialchars($banner_a_image) ?>" alt="Sponsored Banner" loading="lazy">
        <span class="banner-badge">Ad</span>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Folder Header -->
<?php
$_ind_urls  = ($has_subfolders && $current_album !== null) ? $bulk_album_urls  : $bulk_all_urls;
$_ind_files = ($has_subfolders && $current_album !== null) ? $bulk_album_files : $bulk_all_files;
?>
<div class="folder-header">
    <div class="folder-header-brand">
        <?php if ($site_logo && file_exists(UPLOAD_PATH . $site_logo)): ?>
        <a href="<?= BASE_URL ?>/" class="brand-link" title="<?= htmlspecialchars($site_name) ?>">
            <img src="<?= UPLOAD_URL . htmlspecialchars($site_logo) ?>" alt="<?= htmlspecialchars($site_name) ?>" class="brand-logo">
        </a>
        <?php else: ?>
        <span class="brand-name-text"><?= htmlspecialchars($site_name) ?></span>
        <?php endif; ?>
    </div>
    <div class="folder-header-body">
        <div class="folder-title-wrap">
            <span class="folder-title-icon"><i class="fas fa-images"></i></span>
            <h1 class="folder-title">
                <?php if ($has_subfolders && $current_album !== null): ?>
                    <?= htmlspecialchars($current_album === '' ? 'General' : $current_album) ?>
                <?php else: ?>
                    <?= htmlspecialchars($folder['folder_name']) ?>
                <?php endif; ?>
            </h1>
        </div>
        <?php if (!$has_subfolders || $current_album === null): ?>
            <?php if ($folder['description']): ?>
                <p class="folder-description"><?= nl2br(htmlspecialchars($folder['description'])) ?></p>
            <?php endif; ?>
            <?php if (($folder['transfer_source'] ?? 'admin') === 'public'): ?>
                <div class="transfer-notice">
                    <i class="fas fa-paper-plane"></i>
                    <strong>Shared via file transfer</strong>
                    <?php if (!empty($folder['sender_email'])): ?> — from <em><?= htmlspecialchars($folder['sender_email']) ?></em><?php endif; ?>
                    <?php if (!empty($folder['sender_message'])): ?>
                        <p class="transfer-message">"<?= nl2br(htmlspecialchars($folder['sender_message'])) ?>"</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        <div class="stats-badges">
            <?php if ($has_subfolders && $current_album === null): ?>
                <span class="stats-badge"><i class="fas fa-folder" style="color:#d97706;"></i> <?= count($subfolders) ?> Album<?= count($subfolders) !== 1 ? 's' : '' ?></span>
                <span class="stats-badge"><i class="fas fa-images" style="color:#3b82f6;"></i> <?= count($photos) ?> File<?= count($photos) !== 1 ? 's' : '' ?></span>
            <?php else: ?>
                <span class="stats-badge"><i class="fas fa-images" style="color:#3b82f6;"></i> <?= count($visible_photos) ?> File<?= count($visible_photos) !== 1 ? 's' : '' ?></span>
            <?php endif; ?>
            <?php if ($folder['expires_at']): ?>
                <span class="stats-badge"><i class="fas fa-clock" style="color:#f59e0b;"></i> Expires: <?= date('M d, Y', strtotime($folder['expires_at'])) ?> (<?= convertToNepaliDate($folder['expires_at']) ?>)</span>
            <?php endif; ?>
        </div>
        <?php if (!empty($_ind_urls)): ?>
        <div class="header-actions">
            <button type="button" class="btn-download-all" id="downloadNowBtn" onclick="return downloadNowSelected()">
                <i class="fas fa-download"></i>
                Download All Photos
                <span class="dl-badge"><?= count($_ind_urls) ?></span>
            </button>
            <?php if ($whatsapp_delete_url): ?>
            <a href="<?= htmlspecialchars($whatsapp_delete_url) ?>" target="_blank" rel="noopener noreferrer" class="whatsapp-delete-btn">
                <i class="fab fa-whatsapp"></i> Done downloading? Request deletion
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div><!-- end folder-header -->

<?php
$show_preview = !isset($folder['show_preview']) || $folder['show_preview'];
if (!$show_preview):
?>
<!-- Download Only View -->
<div class="download-only-view text-center py-5">
    <div class="mb-4" style="width:90px;height:90px;border-radius:50%;background:#dcfce7;display:flex;align-items:center;justify-content:center;margin:0 auto;">
        <i class="fas fa-file-archive" style="font-size:2.8rem;color:var(--primary-green);"></i>
    </div>
    <h4 class="mb-2 fw-bold" style="color:var(--text-primary);">Download Files</h4>
    <?php $file_count = count($photos); ?>
    <p style="color:var(--text-secondary);" class="mb-4">
        This folder contains <?= $file_count ?> <?= $file_count !== 1 ? 'files' : 'file' ?>.<br>Click the button below to download.
    </p>
    <?php if (!empty($bulk_all_urls)): ?>
        <button type="button" class="btn btn-success btn-lg download-all-btn mt-3 px-5 py-3"
                onclick="return confirmAndDownloadNow(<?= json_encode($bulk_all_files, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)">
            <i class="fas fa-download me-2"></i> Download Now (<?= count($bulk_all_urls) ?>)
        </button>
        <p style="color:var(--text-secondary);" class="mt-3 mb-0"><small><i class="fas fa-download me-1"></i> Download all files at once</small></p>
        <?php if ($whatsapp_delete_url): ?>
        <div class="whatsapp-delete-request mt-4">
            <a href="<?= htmlspecialchars($whatsapp_delete_url) ?>" target="_blank" rel="noopener noreferrer" class="whatsapp-delete-btn">
                <i class="fab fa-whatsapp"></i> I downloaded my photos — please delete them
            </a>
            <p class="whatsapp-delete-note"><i class="fas fa-info-circle"></i> Press this button after you finish downloading</p>
        </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="alert alert-info d-inline-block"><i class="fas fa-folder-open me-2"></i> This folder is empty.</div>
    <?php endif; ?>
</div>
<?php else: ?>

<!-- Toolbar -->
<?php if (!empty($_ind_urls)): ?>
<div class="drive-toolbar">
    <button type="button" id="selectModeBtn" class="btn btn-outline-primary btn-sm select-mode-btn" onclick="toggleSelectMode()">
        <i class="fas fa-check-square me-1"></i> Select Photos
    </button>
    <div class="ms-auto d-flex gap-2">
        <button id="viewGridBtn" class="btn btn-outline-secondary btn-sm active" onclick="setViewMode('grid')" title="Grid view">
            <i class="fas fa-th"></i>
        </button>
        <button id="viewListBtn" class="btn btn-outline-secondary btn-sm" onclick="setViewMode('list')" title="List view">
            <i class="fas fa-list"></i>
        </button>
        <?php if ($folder['allow_zip_download'] && !empty($visible_photos)): ?>
        <button type="button" class="btn btn-outline-success btn-sm" onclick="return downloadNow(_dlFiles)">
            <i class="fas fa-file-archive me-1"></i> Download ZIP
        </button>
        <?php elseif ($folder['allow_zip_download'] && !$has_subfolders && !empty($photos)): ?>
        <button type="button" class="btn btn-outline-success btn-sm" onclick="return downloadNow(_dlFiles)">
            <i class="fas fa-file-archive me-1"></i> Download ZIP
        </button>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php if ($has_subfolders && $current_album !== null): ?>
<!-- Breadcrumb -->
<div class="breadcrumb-nav">
    <a href="?token=<?= urlencode($token) ?>"><i class="fas fa-folder"></i> <?= htmlspecialchars($folder['folder_name']) ?></a>
    <span class="separator"><i class="fas fa-chevron-right" style="font-size:0.75rem;"></i></span>
    <span><i class="fas fa-folder-open text-warning"></i> <?= htmlspecialchars($current_album === '' ? 'General' : $current_album) ?></span>
</div>
<?php endif; ?>

<?php
$image_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
$video_extensions = ['mp4', 'mov', 'avi', 'webm', 'mkv', 'mpg', 'mpeg', '3gp', 'm4v', 'ogg'];

if ($has_subfolders && $current_album === null):
    // Album cards view
    if (empty($subfolders)):
?>
    <div class="text-center py-5" style="color:var(--text-secondary);">
        <i class="fas fa-folder-open fa-4x mb-3" style="opacity:0.35;"></i><p>No albums in this folder yet.</p>
    </div>
<?php else: ?>
    <div class="subfolder-grid">
        <?php foreach ($subfolders as $sf_name => $sf_photos):
            $display_name = ($sf_name === '') ? 'General' : $sf_name;
            $album_url = '?token=' . urlencode($token) . '&album=' . urlencode($sf_name);
        ?>
        <a href="<?= htmlspecialchars($album_url, ENT_QUOTES, 'UTF-8') ?>" class="subfolder-card">
            <div class="subfolder-thumb"><i class="fas fa-folder folder-icon"></i></div>
            <div class="subfolder-info">
                <div class="subfolder-name" title="<?= htmlspecialchars($display_name) ?>"><?= htmlspecialchars($display_name) ?></div>
                <div class="subfolder-count"><i class="fas fa-photo-video"></i> <?= count($sf_photos) ?> file<?= count($sf_photos) !== 1 ? 's' : '' ?></div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
<?php
    endif;
elseif (!empty($visible_photos)):
    renderPhotoGrid($visible_photos, $token, $folder, $image_extensions, $video_extensions);
elseif (!$has_subfolders && !empty($photos)):
    renderPhotoGrid($photos, $token, $folder, $image_extensions, $video_extensions);
else:
?>
    <div class="text-center py-5" style="color:var(--text-secondary);">
        <i class="fas fa-photo-video fa-4x mb-3" style="opacity:0.35;"></i><p>No files in this folder yet.</p>
    </div>
<?php endif; ?>

<?php endif; ?> <!-- end show_preview else -->

<?php if ($has_any_banner): ?>
<div class="mobile-banner-section">
    <div class="mobile-banner-container">
        <div class="mobile-banner-header"><span>Sponsored</span></div>
        <div class="mobile-banners-grid <?= ($show_banner_a && $show_banner_b) ? 'has-two-banners' : '' ?>">
            <?php if ($show_banner_a): ?>
            <div class="mobile-banner-item mobile-banner-a-in-bottom">
                <?php if (!empty($banner_a_link)): ?>
                <a href="<?= htmlspecialchars($banner_a_link) ?>" target="_blank" rel="noopener noreferrer">
                    <img src="<?= UPLOAD_URL . htmlspecialchars($banner_a_image) ?>" alt="Sponsored Banner" loading="lazy">
                    <span class="banner-badge">Ad</span>
                </a>
                <?php else: ?>
                <img src="<?= UPLOAD_URL . htmlspecialchars($banner_a_image) ?>" alt="Sponsored Banner" loading="lazy">
                <span class="banner-badge">Ad</span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <?php if ($show_banner_b): ?>
            <div class="mobile-banner-item">
                <?php if (!empty($banner_b_link)): ?>
                <a href="<?= htmlspecialchars($banner_b_link) ?>" target="_blank" rel="noopener noreferrer">
                    <img src="<?= UPLOAD_URL . htmlspecialchars($banner_b_image) ?>" alt="Sponsored Banner" loading="lazy">
                    <span class="banner-badge">Ad</span>
                </a>
                <?php else: ?>
                <img src="<?= UPLOAD_URL . htmlspecialchars($banner_b_image) ?>" alt="Sponsored Banner" loading="lazy">
                <span class="banner-badge">Ad</span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="footer-text">
    <?php if ($contact_phone || $contact_email || $whatsapp_number): ?>
    <div class="footer-contact">
        <?php if ($contact_phone): ?><a href="tel:<?= htmlspecialchars($contact_phone) ?>"><i class="fas fa-phone"></i> <?= htmlspecialchars($contact_phone) ?></a><?php endif; ?>
        <?php if ($contact_email): ?><a href="mailto:<?= htmlspecialchars($contact_email) ?>"><i class="fas fa-envelope"></i> <?= htmlspecialchars($contact_email) ?></a><?php endif; ?>
        <?php if ($whatsapp_number): ?><a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $whatsapp_number) ?>" target="_blank" rel="noopener noreferrer"><i class="fab fa-whatsapp"></i> WhatsApp</a><?php endif; ?>
    </div>
    <?php endif; ?>
    <div class="footer-security">
        <i class="fas fa-shield-alt"></i>
        <span>Secure file sharing by <strong><?= htmlspecialchars($site_name) ?></strong></span>
        <span>&nbsp;·&nbsp;</span>
        <i class="fas fa-lock"></i>
        <span>Your files are private &amp; protected</span>
    </div>
</div>
<?php endif; ?> <!-- end error_message else -->
</div><!-- end folder-container -->

<?php if ($has_any_banner): ?>
    </div><!-- end main-content -->
    <?php if ($show_banner_b): ?>
    <div class="banner-ad banner-ad-desktop">
        <?php if (!empty($banner_b_link)): ?>
        <a href="<?= htmlspecialchars($banner_b_link) ?>" target="_blank" rel="noopener noreferrer">
            <img src="<?= UPLOAD_URL . htmlspecialchars($banner_b_image) ?>" alt="Sponsored Banner">
        </a>
        <?php else: ?>
        <img src="<?= UPLOAD_URL . htmlspecialchars($banner_b_image) ?>" alt="Sponsored Banner">
        <?php endif; ?>
        <div class="banner-ad-label">Sponsored</div>
    </div>
    <?php endif; ?>
</div><!-- end page-wrapper -->
<?php endif; ?>

<!-- Image Lightbox -->
<div class="lightbox" id="lightbox" onclick="closeLightbox()">
    <span class="lightbox-close">&times;</span>
    <a id="lightbox-download-btn" class="lightbox-download-btn" title="Download" onclick="event.stopPropagation()">
        <i class="fas fa-arrow-down"></i>
    </a>
    <img src="" alt="Preview" id="lightbox-image">
</div>

<!-- Video Lightbox -->
<div class="lightbox" id="video-lightbox" onclick="closeVideoLightbox()">
    <span class="lightbox-close">&times;</span>
    <a id="video-lightbox-download-btn" class="lightbox-download-btn" title="Download" onclick="event.stopPropagation()">
        <i class="fas fa-arrow-down"></i>
    </a>
    <video id="lightbox-video" controls onclick="event.stopPropagation()">
        <source src="" id="lightbox-video-src" type="video/mp4">
    </video>
</div>

<script src="<?= BASE_URL ?>/admin/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

<!-- Download Progress Overlay -->
<div id="downloadProgressOverlay">
    <div class="dl-card">
        <div class="dl-icon"><i class="fas fa-download" id="dlIcon"></i></div>
        <div class="dl-title" id="dlTitle">Preparing Download…</div>
        <div class="dl-filename" id="dlFilename"></div>
        <div class="dl-bar-wrap"><div class="dl-bar-fill" id="dlBar"></div></div>
        <div class="dl-stats">
            <span id="dlPercent">0%</span>
            <span id="dlEta">Calculating…</span>
            <span id="dlSpeed"></span>
        </div>
        <div class="dl-size-info" id="dlSizeInfo"></div>
    </div>
</div>

<!-- Selection Bar -->
<div id="selectionBar" role="toolbar" aria-label="Photo selection actions">
    <span class="sel-count"><i class="fas fa-check-circle me-1"></i><span id="selCount">0</span> selected</span>
    <button class="btn btn-success btn-sm" onclick="downloadNowSelected()" aria-label="Download selected photos">
        <i class="fas fa-download me-1"></i> Download Now
    </button>
    <button class="btn btn-outline-secondary btn-sm" onclick="selectAllPhotos()" aria-label="Select all photos">
        <i class="fas fa-check-double me-1"></i> Select All
    </button>
    <button class="btn btn-outline-danger btn-sm" onclick="deselectAllPhotos()" aria-label="Deselect all photos">
        <i class="fas fa-times me-1"></i> Deselect All
    </button>
</div>

<!-- Resume Modal -->

<!-- Confirm Modal -->

<!-- Page Share Button -->
<div class="page-share-wrap" aria-label="Share this page">
    <button class="page-share-btn" type="button" aria-haspopup="true" aria-expanded="false">
        <i class="fas fa-share-alt" aria-hidden="true"></i>
        <span>Share</span>
    </button>
    <div class="page-share-dropdown" role="menu" aria-label="Share options">
        <button class="page-share-opt page-share-copy" type="button" role="menuitem">
            <i class="fas fa-link" aria-hidden="true"></i> Copy link
        </button>
        <a class="page-share-opt page-share-whatsapp" href="#" role="menuitem" target="_blank" rel="noopener noreferrer">
            <i class="fab fa-whatsapp" aria-hidden="true"></i> Share on WhatsApp
        </a>
        <a class="page-share-opt page-share-facebook" href="#" role="menuitem" target="_blank" rel="noopener noreferrer">
            <i class="fab fa-facebook-f" aria-hidden="true"></i> Share on Facebook
        </a>
    </div>
</div>

<script>
var _dlFiles = <?= json_encode(
    ($has_subfolders && $current_album !== null) ? $bulk_album_files : $bulk_all_files,
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
) ?>;
var BLOB_REVOKE_DELAY = 10000;
window._folderToken = <?= json_encode($token, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="<?= BASE_URL ?>/js/folder-share.js"></script>
<script src="<?= BASE_URL ?>/js/share.js"></script>
</body>
</html>
