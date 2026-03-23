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
    } catch (Exception $e) {
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

// Handle individual photo download
if (!$error_message && isset($_GET['download_photo']) && is_numeric($_GET['download_photo'])) {
    $photo_id = intval($_GET['download_photo']);
    
    // Find the photo
    $photo_stmt = $db->prepare("SELECT * FROM shared_photos WHERE id = ? AND folder_id = ? AND status = 'active'");
    $photo_stmt->execute([$photo_id, $folder['id']]);
    $photo = $photo_stmt->fetch();
    
    if ($photo) {
        // Check max downloads if set
        if ($folder['max_downloads'] && $photo['download_count'] >= $folder['max_downloads']) {
            $error_message = 'Maximum download limit reached for this photo.';
        } else {
            $file_path = UPLOAD_PATH . $photo['image_path'];
            
            // Security: Verify file is within uploads directory
            $real_upload_path = realpath(UPLOAD_PATH);
            $real_file_path = realpath($file_path);
            
            if ($real_file_path && $real_upload_path && strpos($real_file_path, $real_upload_path) === 0 && file_exists($file_path)) {
                // Increment download count
                $update_stmt = $db->prepare("UPDATE shared_photos SET download_count = download_count + 1 WHERE id = ?");
                $update_stmt->execute([$photo['id']]);
                
                // Increment folder total downloads
                $db->prepare("UPDATE shared_folders SET total_downloads = total_downloads + 1 WHERE id = ?")->execute([$folder['id']]);
                
                // Get file info
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime_type = finfo_file($finfo, $file_path);
                finfo_close($finfo);
                
                // Generate download filename
                $ext = pathinfo($photo['image_path'], PATHINFO_EXTENSION);
                $safe_title = preg_replace('/[^a-zA-Z0-9_\-\.\s]/u', '_', $photo['title']);
                $safe_title = preg_replace('/_+/', '_', $safe_title);
                $safe_title = trim($safe_title, '_');
                $download_filename = (!empty($safe_title) ? $safe_title : 'photo') . '.' . $ext;
                
                // Prepare for large file download
                // Disable time limit for large file transfers
                @set_time_limit(0);
                
                // Disable output buffering to allow immediate streaming
                if (ob_get_level()) {
                    ob_end_clean();
                }
                
                // Get file size
                $file_size = filesize($file_path);
                
                // Send file for download with proper headers
                header('Content-Type: ' . $mime_type);
                header('Content-Disposition: attachment; filename="' . $download_filename . '"');
                header('Content-Length: ' . $file_size);
                header('Content-Transfer-Encoding: binary');
                header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
                header('Cache-Control: post-check=0, pre-check=0', false);
                header('Pragma: no-cache');
                header('Expires: 0');
                
                // Flush headers to browser immediately
                flush();
                
                // Stream the file in chunks for large files
                $handle = fopen($file_path, 'rb');
                if ($handle !== false) {
                    // Use 8KB chunks for efficient streaming
                    $chunk_size = 8 * 1024;
                    while (!feof($handle)) {
                        echo fread($handle, $chunk_size);
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
                $error_message = 'File not found.';
            }
        }
    }
}

// Handle Download All as ZIP - Using streaming for instant downloads (like Google Drive)
if (!$error_message && isset($_GET['download_all']) && $_GET['download_all'] === '1' && $folder['allow_zip_download']) {
    // When inside an album, only ZIP photos from that album; otherwise ZIP everything
    $photos_to_zip = ($has_subfolders && $current_album !== null)
        ? $visible_photos
        : $photos;
    if (empty($photos_to_zip)) {
        $error_message = 'No photos to download.';
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
            
            // Security check: verify file is within uploads directory
            if ($real_file_path && $real_upload_path && strpos($real_file_path, $real_upload_path) === 0 && file_exists($file_path)) {
                // Generate safe filename for inside ZIP
                $ext = pathinfo($photo['image_path'], PATHINFO_EXTENSION);
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
            $error_message = 'No valid files to download.';
        } else {
            // Use streaming ZIP for instant download (like Google Drive)
            // Download starts immediately without waiting for full ZIP to be created
            $zipStream = new ZipStream($zip_filename);
            
            // Start streaming - this sends headers and begins the download immediately
            $zipStream->begin();
            
            $added_count = 0;
            
            // Stream each file directly to the browser
            foreach ($valid_files as $file_info) {
                if ($zipStream->addFile($file_info['path'], $file_info['zip_name'])) {
                    $added_count++;
                    
                    // Increment download count for this photo
                    $update_stmt = $db->prepare("UPDATE shared_photos SET download_count = download_count + 1 WHERE id = ?");
                    $update_stmt->execute([$file_info['photo_id']]);
                }
                
                // Check if connection is still alive
                if (connection_aborted()) {
                    break;
                }
            }
            
            // Finish the ZIP file (write central directory)
            $zipStream->finish();
            
            // Increment folder total downloads
            if ($added_count > 0) {
                $db->prepare("UPDATE shared_folders SET total_downloads = total_downloads + ? WHERE id = ?")->execute([$added_count, $folder['id']]);
            }
            
            exit;
        }
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

// WhatsApp deletion request message (Nepali)
$whatsapp_delete_message = 'तपाईँहरूलाई धेरै धेरै धन्यवाद! मैले मेरो फोटो डाउनलोड गरिसकेँ। कृपया प्राइभेसीको कारण मेरो फोटो तपाईँहरूको सिस्टमबाट हटाइदिनुहोला।';
$whatsapp_delete_url = '';
if ($whatsapp_number) {
    $clean_whatsapp = preg_replace('/[^0-9]/', '', $whatsapp_number);
    $whatsapp_delete_url = 'https://wa.me/' . $clean_whatsapp . '?text=' . rawurlencode($whatsapp_delete_message);
}
?>
<!DOCTYPE html>
<html lang="ne">
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
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/share.css">
    
    <style>
        :root {
            --primary-green: #16a34a;
            --light-green: #22c55e;
            --dark-green: #14532d;
            --accent: #dcfce7;
            --surface: #ffffff;
            --bg: #f1f5f9;
            --text-primary: #0f172a;
            --text-secondary: #64748b;
            --border: #e2e8f0;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.07), 0 1px 2px rgba(0,0,0,0.05);
            --shadow-md: 0 4px 16px rgba(0,0,0,0.08), 0 2px 6px rgba(0,0,0,0.05);
            --shadow-lg: 0 10px 40px rgba(0,0,0,0.1), 0 4px 12px rgba(0,0,0,0.06);
            --radius: 16px;
            --radius-sm: 10px;
        }

        * { box-sizing: border-box; }

        body {
            background: var(--bg);
            min-height: 100vh;
            padding: 20px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            color: var(--text-primary);
        }

        .folder-container {
            max-width: 1400px;
            margin: 0 auto;
        }

        /* ── Folder Header Card ── */
        .folder-header {
            background: var(--surface);
            border-radius: var(--radius);
            box-shadow: var(--shadow-lg);
            padding: 0;
            margin-bottom: 24px;
            overflow: hidden;
        }

        .folder-header-accent {
            height: 5px;
            background: linear-gradient(90deg, var(--primary-green) 0%, var(--light-green) 60%, #86efac 100%);
        }

        .folder-header-body {
            padding: 28px 30px 24px;
        }

        .folder-title {
            font-size: 1.65rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .folder-title-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: linear-gradient(135deg, #fef9c3 0%, #fef08a 100%);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 2px 8px rgba(234,179,8,0.25);
        }

        .folder-title-icon i {
            color: #d97706;
            font-size: 1.3rem;
        }

        .folder-description {
            color: var(--text-secondary);
            margin-bottom: 16px;
            font-size: 0.93rem;
            line-height: 1.6;
        }

        /* ── Stats Badges ── */
        .stats-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
        }

        .stats-badge {
            background: #f8fafc;
            border: 1px solid var(--border);
            padding: 5px 14px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.82rem;
            font-weight: 500;
            color: var(--text-secondary);
        }

        .stats-badge i { font-size: 0.85rem; }

        /* ── Download Buttons ── */
        .download-all-btn {
            background: linear-gradient(135deg, var(--primary-green) 0%, var(--light-green) 100%);
            border: none;
            padding: 13px 32px;
            font-size: 1.05rem;
            font-weight: 600;
            border-radius: 50px;
            transition: all 0.25s;
            box-shadow: 0 4px 18px rgba(22,163,74,0.35);
            letter-spacing: 0.01em;
        }

        .download-all-btn:hover {
            background: linear-gradient(135deg, var(--dark-green) 0%, var(--primary-green) 100%);
            transform: translateY(-2px);
            box-shadow: 0 8px 28px rgba(22,163,74,0.45);
        }

        /* ── Photo Grid ── */
        .photo-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 18px;
        }

        .photo-card {
            background: var(--surface);
            border-radius: var(--radius-sm);
            overflow: hidden;
            box-shadow: var(--shadow-md);
            transition: transform 0.28s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.28s ease;
            cursor: pointer;
            border: 1px solid var(--border);
        }

        .photo-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 18px 45px rgba(0,0,0,0.14);
            border-color: #cbd5e1;
        }

        .photo-card img {
            width: 100%;
            aspect-ratio: 4/3;
            object-fit: cover;
            display: block;
        }

        /* Prevent blinking: hide images until loaded, then show them */
        .photo-card img.lazy-img {
            opacity: 0;
            transition: opacity 0.4s ease;
        }
        .photo-card img.lazy-img.loaded {
            opacity: 1;
        }

        /* Error state for failed images - show placeholder instead of hiding */
        .photo-card .img-error-placeholder {
            width: 100%;
            aspect-ratio: 4/3;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #94a3b8;
            border-bottom: 1px solid var(--border);
        }
        .photo-card .img-error-placeholder i {
            font-size: 2.5rem;
            margin-bottom: 8px;
            opacity: 0.6;
        }
        .photo-card .img-error-placeholder span {
            font-size: 0.78rem;
            font-weight: 500;
        }

        .photo-card .photo-info {
            padding: 10px 14px 12px;
        }

        .photo-card .photo-title {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-size: 0.84rem;
        }

        /* ── Photo media wrapper (positions overlay button) ── */
        .photo-media {
            position: relative;
            overflow: hidden;
        }

        /* ── WhatsApp-style circular download overlay button ── */
        .photo-media-download {
            position: absolute;
            bottom: 10px;
            right: 10px;
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: rgba(0, 0, 0, 0.55);
            border: 2px solid rgba(255, 255, 255, 0.75);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.95rem;
            opacity: 0;
            transition: opacity 0.2s ease, background 0.2s ease, transform 0.15s ease;
            z-index: 20;
            text-decoration: none;
            cursor: pointer;
        }
        .photo-card:hover .photo-media-download {
            opacity: 1;
        }
        .photo-media-download:hover {
            background: rgba(22,163,74, 0.9);
            border-color: #fff;
            color: #fff;
            transform: scale(1.12);
        }
        .photo-media-download.dl-limit-reached {
            background: rgba(80, 80, 80, 0.55);
            border-color: rgba(255, 255, 255, 0.3);
            cursor: not-allowed;
            pointer-events: none;
        }
        /* On mobile/touch: always show the overlay download button */
        @media (max-width: 768px) {
            .photo-media-download {
                opacity: 0.85;
            }
        }

        /* ── Lightbox download button ── */
        .lightbox-download-btn {
            position: absolute;
            top: 18px;
            right: 70px;
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.18);
            border: 2px solid rgba(255, 255, 255, 0.6);
            color: #fff;
            display: none;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            text-decoration: none;
            transition: background 0.2s ease, transform 0.15s ease;
            z-index: 1010;
            cursor: pointer;
        }
        .lightbox-download-btn.visible {
            display: flex;
        }
        .lightbox-download-btn:hover {
            background: rgba(22,163,74, 0.85);
            border-color: #fff;
            color: #fff;
            transform: scale(1.08);
        }

        /* Video card styles */
        .photo-card .video-container {
            position: relative;
            width: 100%;
            aspect-ratio: 4/3;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }

        .photo-card .video-play-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.3s;
        }

        .photo-card .video-container:hover .video-play-overlay {
            background: rgba(255,255,255,0.08);
        }

        .photo-card .video-play-overlay i {
            font-size: 3.2rem;
            color: rgba(255,255,255,0.85);
            text-shadow: 0 2px 16px rgba(0,0,0,0.6);
            transition: transform 0.2s, color 0.2s;
        }

        .photo-card .video-container:hover .video-play-overlay i {
            transform: scale(1.1);
            color: #fff;
        }

        .file-type-badge {
            position: absolute;
            top: 8px;
            left: 8px;
            font-size: 0.7rem;
            padding: 3px 8px;
            z-index: 10;
        }

        .file-size-info {
            font-size: 0.75rem;
            color: #888;
            margin-bottom: 8px;
        }

        /* ── Error Container ── */
        .error-container {
            background: var(--surface);
            border-radius: var(--radius);
            box-shadow: var(--shadow-lg);
            padding: 50px 40px;
            text-align: center;
            max-width: 540px;
            margin: 60px auto;
        }

        .error-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: #fef2f2;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }

        .error-icon i {
            font-size: 2.5rem;
            color: #ef4444;
        }

        .footer-text {
            text-align: center;
            padding: 30px;
            color: #999;
            font-size: 0.85rem;
        }

        /* ── Download Progress Overlay ── */
        #downloadProgressOverlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.6);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(4px);
        }
        #downloadProgressOverlay.dl-active {
            display: flex;
        }
        .dl-card {
            background: #fff;
            border-radius: 20px;
            padding: 35px 30px;
            max-width: 420px;
            width: 90%;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: dlFadeIn 0.3s ease;
        }
        @keyframes dlFadeIn {
            from { opacity:0; transform:scale(0.9); }
            to   { opacity:1; transform:scale(1);   }
        }
        .dl-icon {
            font-size: 3rem;
            color: var(--primary-green);
            margin-bottom: 15px;
        }
        .dl-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 4px;
        }
        .dl-filename {
            font-size: 0.85rem;
            color: #888;
            margin-bottom: 18px;
            word-break: break-all;
        }
        .dl-bar-wrap {
            height: 10px;
            background: #dcfce7;
            border-radius: 6px;
            overflow: hidden;
            margin-bottom: 10px;
        }
        .dl-bar-fill {
            height: 100%;
            width: 0%;
            background: linear-gradient(90deg, var(--primary-green), var(--light-green));
            border-radius: 6px;
            transition: width 0.3s ease;
        }
        .dl-stats {
            display: flex;
            justify-content: space-between;
            font-size: 0.82rem;
            color: #666;
            margin-bottom: 6px;
        }
        .dl-size-info {
            font-size: 0.78rem;
            color: #aaa;
        }
        @keyframes dlIndeterminate {
            0%   { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }

        /* Lightbox styles */
        .lightbox {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.92);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .lightbox.active {
            display: flex;
        }

        .lightbox img,
        .lightbox video {
            max-width: 90%;
            max-height: 90%;
            border-radius: 8px;
        }

        .lightbox-close {
            position: absolute;
            top: 20px;
            right: 30px;
            color: white;
            font-size: 2rem;
            cursor: pointer;
        }

        @media (max-width: 768px) {
            .folder-header-body {
                padding: 20px;
            }

            .folder-title {
                font-size: 1.35rem;
            }

            .download-all-btn {
                width: 100%;
                padding: 12px 24px;
                font-size: 0.97rem;
            }

            .photo-grid {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
                gap: 12px;
            }

            /* Mobile: Stack brand bar items vertically */
            .folder-brand-bar {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }
            .folder-brand-bar .go-home-btn {
                margin-top: 10px;
                width: 100%;
                text-align: center;
            }

            /* Mobile: WhatsApp button full width */
            .whatsapp-delete-btn {
                width: 100%;
                justify-content: center;
                font-size: 0.9rem;
                padding: 10px 20px;
            }
        }

        /* ── Company Brand Bar ── */
        .folder-brand-bar {
            display: flex;
            align-items: center;
            gap: 12px;
            padding-bottom: 18px;
            margin-bottom: 20px;
            border-bottom: 1px solid var(--border);
            flex-wrap: wrap;
        }
        .folder-brand-bar .brand-link {
            display: inline-block;
            transition: transform 0.2s, opacity 0.2s;
        }
        .folder-brand-bar .brand-link:hover {
            transform: scale(1.05);
            opacity: 0.9;
        }
        .folder-brand-bar .brand-logo {
            height: 40px;
            max-width: 140px;
            object-fit: contain;
        }
        .brand-text .brand-name {
            font-size: 0.97rem;
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1.2;
            margin: 0;
        }
        .brand-text .brand-tagline {
            font-size: 0.74rem;
            color: var(--text-secondary);
            margin: 2px 0 0;
        }
        .go-home-btn {
            font-size: 0.82rem;
            border-radius: 20px;
            padding: 6px 16px;
            border-color: var(--border);
            color: var(--text-secondary);
            transition: all 0.25s;
        }
        .go-home-btn:hover {
            background: var(--primary-green);
            color: white;
            border-color: var(--primary-green);
        }

        /* ── WhatsApp Deletion Request Button ── */
        .whatsapp-delete-request {
            margin-top: 14px;
        }
        .whatsapp-delete-btn {
            background: #25D366;
            border: none;
            color: white;
            padding: 11px 22px;
            border-radius: 50px;
            font-size: 0.92rem;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.25s;
            box-shadow: 0 3px 12px rgba(37, 211, 102, 0.3);
        }
        .whatsapp-delete-btn:hover {
            background: #128C7E;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(37, 211, 102, 0.4);
        }
        .whatsapp-delete-btn i {
            font-size: 1.15rem;
        }
        .whatsapp-delete-note {
            font-size: 0.78rem;
            color: var(--text-secondary);
            margin-top: 7px;
        }

        /* ── Security Panel ── */
        .security-panel {
            background: var(--accent);
            border: 1px solid #bbf7d0;
            border-radius: var(--radius-sm);
            padding: 11px 18px;
            margin-bottom: 22px;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 14px;
        }
        .security-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.8rem;
            color: var(--primary-green);
            font-weight: 600;
        }
        .security-item i {
            font-size: 0.9rem;
        }
        .security-note {
            margin-left: auto;
            font-size: 0.74rem;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .security-note i {
            color: #94a3b8;
        }

        /* ── Enhanced Footer ── */
        .footer-text {
            text-align: center;
            padding: 30px 20px;
            color: #94a3b8;
            font-size: 0.84rem;
        }
        .footer-contact {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 12px;
        }
        .footer-contact a {
            color: var(--text-secondary);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.84rem;
            transition: color 0.2s;
        }
        .footer-contact a:hover {
            color: var(--primary-green);
        }
        .footer-security {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 6px;
            color: #94a3b8;
            font-size: 0.79rem;
            flex-wrap: wrap;
        }

        @media (max-width: 576px) {
            .security-note { display: none; }
            .folder-brand-bar { padding-bottom: 14px; margin-bottom: 16px; }
        }

        /* ── Sub-folder / Album Grid ── */
        .subfolder-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
        }

        /* Single-folder view: centre one large card */
        .subfolder-grid.single-folder-view {
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            justify-content: center;
            padding: 20px 0;
        }

        .subfolder-card {
            background: var(--surface);
            border-radius: var(--radius-sm);
            overflow: hidden;
            box-shadow: var(--shadow-md);
            transition: transform 0.28s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.28s ease;
            text-decoration: none;
            color: inherit;
            display: block;
            border: 1px solid var(--border);
        }

        .subfolder-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 18px 45px rgba(0,0,0,0.12);
            color: inherit;
            border-color: #cbd5e1;
        }

        .subfolder-card .subfolder-thumb {
            position: relative;
            height: 155px;
            background: linear-gradient(145deg, #fffbeb 0%, #fef3c7 60%, #fde68a 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .subfolder-card .subfolder-thumb::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(ellipse at 60% 40%, rgba(255,255,255,0.5) 0%, transparent 70%);
        }

        .subfolder-card .subfolder-thumb .folder-icon {
            font-size: 5rem;
            color: #d97706;
            filter: drop-shadow(0 4px 10px rgba(217,119,6,0.3));
            position: relative;
            z-index: 1;
            transition: transform 0.3s ease;
        }

        .subfolder-card:hover .subfolder-thumb .folder-icon {
            transform: scale(1.08) translateY(-3px);
        }

        /* Kept for legacy rendering but hidden by default */
        .subfolder-card .subfolder-thumb .thumb-preview {
            display: none;
        }

        .subfolder-card .subfolder-thumb .folder-icon-overlay {
            display: none;
        }

        .subfolder-card .subfolder-info {
            padding: 14px 16px;
        }

        .subfolder-card .subfolder-name {
            font-weight: 700;
            font-size: 0.92rem;
            color: var(--text-primary);
            margin-bottom: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .subfolder-card .subfolder-count {
            font-size: 0.78rem;
            color: #9ca3af;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .subfolder-card .subfolder-count i {
            color: #d97706;
        }

        .breadcrumb-nav {
            background: var(--surface);
            border-radius: var(--radius-sm);
            padding: 11px 18px;
            margin-bottom: 18px;
            box-shadow: var(--shadow-sm);
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            border: 1px solid var(--border);
        }

        .breadcrumb-nav a {
            color: var(--primary-green);
            text-decoration: none;
            font-weight: 500;
        }

        .breadcrumb-nav a:hover { text-decoration: underline; }

        .breadcrumb-nav .separator { color: #cbd5e1; }

        .album-download-btn {
            background: linear-gradient(135deg, var(--primary-green) 0%, var(--light-green) 100%);
            border: none;
            padding: 10px 24px;
            font-size: 0.97rem;
            font-weight: 600;
            border-radius: 50px;
            transition: all 0.25s;
            box-shadow: 0 3px 14px rgba(22,163,74,0.3);
        }

        .album-download-btn:hover {
            background: linear-gradient(135deg, var(--dark-green) 0%, var(--primary-green) 100%);
            transform: translateY(-2px);
        }

        @media (max-width: 576px) {
            .subfolder-grid {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
                gap: 16px;
            }
            .subfolder-card .subfolder-thumb { height: 130px; }
            .subfolder-card .subfolder-thumb .folder-icon { font-size: 4.5rem; }
        }

        /* Banner Ad Styles - Desktop */
        .page-wrapper {
            display: flex;
            justify-content: center;
            gap: 20px;
            align-items: flex-start;
        }
        
        .banner-ad {
            width: 300px;
            min-width: 300px;
            position: sticky;
            top: 20px;
        }
        
        .banner-ad img {
            width: 100%;
            height: auto;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
            display: block;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .banner-ad a {
            display: block;
            text-decoration: none;
            position: relative;
            overflow: hidden;
            border-radius: 12px;
        }
        
        .banner-ad a:hover img {
            transform: scale(1.03);
            box-shadow: 0 12px 40px rgba(0,0,0,0.18);
        }
        
        .banner-ad-label {
            font-size: 11px;
            color: #aaa;
            text-align: center;
            margin-top: 8px;
            text-transform: uppercase;
            letter-spacing: 2px;
            font-weight: 500;
        }
        
        .main-content {
            flex: 1;
            max-width: 1400px;
        }
        
        /* Desktop sidebar banners - hide on smaller screens */
        @media (max-width: 1200px) {
            .banner-ad-desktop {
                display: none;
            }
            .page-wrapper {
                display: block;
            }
        }
        
        /* Mobile Banner Styles - Show at bottom on mobile/tablet */
        .mobile-banner-section {
            display: none;
            margin-top: 30px;
            padding: 20px 0;
        }
        
        .mobile-banner-container {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 1px solid rgba(0,0,0,0.04);
        }
        
        .mobile-banner-header {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
            gap: 8px;
        }
        
        .mobile-banner-header span {
            font-size: 12px;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 2px;
            font-weight: 500;
        }
        
        .mobile-banner-header::before,
        .mobile-banner-header::after {
            content: '';
            flex: 1;
            height: 1px;
            background: linear-gradient(90deg, transparent, #ddd, transparent);
        }
        
        .mobile-banners-grid {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        
        .mobile-banner-item {
            position: relative;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            background: #fff;
        }
        
        .mobile-banner-item img {
            width: 100%;
            height: auto;
            display: block;
            transition: transform 0.3s ease;
        }
        
        .mobile-banner-item a {
            display: block;
        }
        
        .mobile-banner-item a:active img {
            transform: scale(0.98);
        }
        
        .mobile-banner-item .banner-badge {
            position: absolute;
            top: 8px;
            right: 8px;
            background: rgba(0,0,0,0.6);
            color: #fff;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 500;
            backdrop-filter: blur(5px);
        }
        
        /* Show mobile banners on smaller screens */
        @media (max-width: 1200px) {
            .mobile-banner-section {
                display: block;
            }
        }
        
        /* Adjust mobile banner layout for different screen sizes */
        @media (min-width: 576px) and (max-width: 1200px) {
            /* Tablet: show banners side by side if both exist */
            .mobile-banners-grid.has-two-banners {
                flex-direction: row;
                gap: 20px;
            }
            .mobile-banners-grid.has-two-banners .mobile-banner-item {
                flex: 1;
            }
        }
        
        @media (max-width: 575px) {
            /* Mobile: full width stacked banners */
            .mobile-banner-container {
                padding: 15px;
                border-radius: 12px;
            }
            .mobile-banners-grid {
                gap: 12px;
            }
            .mobile-banner-item {
                border-radius: 10px;
            }
            /* On mobile: Banner A moves to top, hide it from the bottom section */
            .mobile-banner-a-in-bottom {
                display: none;
            }
        }

        /* Mobile top banner - Banner A at top, only on mobile */
        .mobile-banner-top {
            display: none;
            margin-bottom: 20px;
        }
        @media (max-width: 575px) {
            .mobile-banner-top {
                display: block;
            }
        }

    </style>
</head>
<body>
    <?php 
    // Check if any banner is enabled and has an image
    $show_banner_a = $banner_a_enabled && !empty($banner_a_image) && file_exists(UPLOAD_PATH . $banner_a_image);
    $show_banner_b = $banner_b_enabled && !empty($banner_b_image) && file_exists(UPLOAD_PATH . $banner_b_image);
    $has_any_banner = $show_banner_a || $show_banner_b;
    ?>
    
    <?php if ($has_any_banner): ?>
    <div class="page-wrapper">
        <!-- Banner A (Left Side) - Desktop Only -->
        <?php if ($show_banner_a): ?>
        <div class="banner-ad banner-ad-left banner-ad-desktop">
            <?php if (!empty($banner_a_link)): ?>
            <a href="<?php echo htmlspecialchars($banner_a_link); ?>" target="_blank" rel="noopener noreferrer">
                <img src="<?php echo UPLOAD_URL . htmlspecialchars($banner_a_image); ?>" alt="Sponsored Banner">
            </a>
            <?php else: ?>
            <img src="<?php echo UPLOAD_URL . htmlspecialchars($banner_a_image); ?>" alt="Sponsored Banner">
            <?php endif; ?>
            <div class="banner-ad-label">Sponsored</div>
        </div>
        <?php endif; ?>
        
        <div class="main-content">
    <?php endif; ?>
    
    <div class="folder-container">
        <?php if ($error_message): ?>
            <div class="error-container">
                <div class="error-icon">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
                <h3 class="fw-bold mb-2" style="color:var(--text-primary);">Access Denied</h3>
                <p class="mb-4" style="color:var(--text-secondary);"><?php echo htmlspecialchars($error_message); ?></p>
                <p style="color:var(--text-secondary);">
                    <small>If you believe this is an error, please contact the sender.</small>
                </p>
            </div>
        <?php else: ?>
            <?php if ($show_banner_a): ?>
            <!-- Mobile Top Banner - Banner A at top on mobile only -->
            <div class="mobile-banner-top">
                <div class="mobile-banner-item">
                    <?php if (!empty($banner_a_link)): ?>
                    <a href="<?php echo htmlspecialchars($banner_a_link); ?>" target="_blank" rel="noopener noreferrer">
                        <img src="<?php echo UPLOAD_URL . htmlspecialchars($banner_a_image); ?>" alt="Sponsored Banner" loading="lazy">
                        <span class="banner-badge">Ad</span>
                    </a>
                    <?php else: ?>
                    <img src="<?php echo UPLOAD_URL . htmlspecialchars($banner_a_image); ?>" alt="Sponsored Banner" loading="lazy">
                    <span class="banner-badge">Ad</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            <!-- Folder Header -->
            <div class="folder-header">
                <div class="folder-header-accent"></div>
                <div class="folder-header-body">
                <?php if ($site_logo && file_exists(UPLOAD_PATH . $site_logo)): ?>
                <div class="folder-brand-bar">
                    <a href="<?php echo BASE_URL; ?>/" class="brand-link" title="Go to Home">
                        <img src="<?php echo UPLOAD_URL . htmlspecialchars($site_logo); ?>"
                             alt="<?php echo htmlspecialchars($site_name); ?>"
                             class="brand-logo">
                    </a>
                    <div class="brand-text">
                        <p class="brand-name"><?php echo htmlspecialchars($site_name); ?></p>
                        <p class="brand-tagline"><i class="fas fa-shield-alt"></i> Professional &amp; Secure File Sharing</p>
                    </div>
                    <a href="<?php echo BASE_URL; ?>/" class="btn btn-outline-secondary btn-sm ms-auto go-home-btn">
                        <i class="fas fa-home me-1"></i> Go Back to Home
                    </a>
                </div>
                <?php else: ?>
                <div class="folder-brand-bar">
                    <div class="brand-text">
                        <p class="brand-name"><?php echo htmlspecialchars($site_name); ?></p>
                        <p class="brand-tagline"><i class="fas fa-shield-alt"></i> Professional &amp; Secure File Sharing</p>
                    </div>
                    <a href="<?php echo BASE_URL; ?>/" class="btn btn-outline-secondary btn-sm ms-auto go-home-btn">
                        <i class="fas fa-home me-1"></i> Go Back to Home
                    </a>
                </div>
                <?php endif; ?>
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h1 class="folder-title">
                            <span class="folder-title-icon">
                                <i class="fas fa-folder-open"></i>
                            </span>
                            <?php if ($has_subfolders && $current_album !== null): ?>
                                <?php echo htmlspecialchars($current_album === '' ? 'General' : $current_album); ?>
                            <?php else: ?>
                                <?php echo htmlspecialchars($folder['folder_name']); ?>
                            <?php endif; ?>
                        </h1>

                        <?php if (!$has_subfolders || $current_album === null): ?>
                            <?php if ($folder['description']): ?>
                                <p class="folder-description"><?php echo nl2br(htmlspecialchars($folder['description'])); ?></p>
                            <?php endif; ?>
                        <?php endif; ?>

                        <div class="stats-badges">
                            <?php if ($has_subfolders && $current_album === null): ?>
                                <span class="stats-badge">
                                    <i class="fas fa-folder" style="color:#d97706;"></i>
                                    <?php echo count($subfolders); ?> Album<?php echo count($subfolders) !== 1 ? 's' : ''; ?>
                                </span>
                                <span class="stats-badge">
                                    <i class="fas fa-photo-video" style="color:#3b82f6;"></i>
                                    <?php echo count($photos); ?> File<?php echo count($photos) !== 1 ? 's' : ''; ?>
                                </span>
                            <?php else: ?>
                                <span class="stats-badge">
                                    <i class="fas fa-photo-video" style="color:#3b82f6;"></i>
                                    <?php echo count($visible_photos); ?> File<?php echo count($visible_photos) !== 1 ? 's' : ''; ?>
                                </span>
                            <?php endif; ?>
                            <?php if ($folder['expires_at']): ?>
                                <span class="stats-badge">
                                    <i class="fas fa-clock" style="color:#f59e0b;"></i>
                                    Expires: <?php echo date('M d, Y', strtotime($folder['expires_at'])); ?> (<?php echo convertToNepaliDate($folder['expires_at']); ?>)
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-4 text-md-end mt-3 mt-md-0">
                        <?php if ($folder['allow_zip_download']): ?>
                            <?php if ($has_subfolders && $current_album === null && count($photos) > 0): ?>
                                <!-- Top-level: offer download of all photos -->
                                <a href="?token=<?php echo urlencode($token); ?>&download_all=1"
                                   class="btn btn-success download-all-btn"
                                   onclick="return startDownload(this.href, <?php echo json_encode(htmlspecialchars($folder['folder_name']) . '.zip'); ?>)">
                                    <i class="fas fa-download me-2"></i>
                                    Download All (<?php echo count($photos); ?>)
                                </a>
                                <p class="text-muted mt-2 mb-0">
                                    <small><i class="fas fa-file-archive"></i> Downloads as ZIP file in one folder</small>
                                </p>
                            <?php elseif (!$has_subfolders && count($photos) > 0): ?>
                                <!-- Flat view -->
                                <a href="?token=<?php echo urlencode($token); ?>&download_all=1"
                                   class="btn btn-success download-all-btn"
                                   onclick="return startDownload(this.href, <?php echo json_encode(htmlspecialchars($folder['folder_name']) . '.zip'); ?>)">
                                    <i class="fas fa-download me-2"></i> 
                                    Download All (<?php echo count($photos); ?>)
                                </a>
                                <p class="text-muted mt-2 mb-0">
                                    <small><i class="fas fa-file-archive"></i> Downloads as ZIP file in one folder</small>
                                </p>
                            <?php elseif ($has_subfolders && $current_album !== null && count($visible_photos) > 0): ?>
                                <!-- Album view: download only this album -->
                                <a href="?token=<?php echo urlencode($token); ?>&album=<?php echo urlencode($current_album); ?>&download_all=1"
                                   class="btn btn-success album-download-btn"
                                   onclick="return startDownload(this.href, <?php echo json_encode(($current_album === '' ? 'General' : $current_album) . '.zip', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>)">
                                    <i class="fas fa-download me-2"></i>
                                    Download Album (<?php echo count($visible_photos); ?>)
                                </a>
                                <p class="text-muted mt-2 mb-0">
                                    <small><i class="fas fa-file-archive"></i> Downloads album as ZIP</small>
                                </p>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php if ($whatsapp_delete_url): ?>
                        <!-- WhatsApp Photo Deletion Request -->
                        <div class="whatsapp-delete-request">
                            <a href="<?php echo htmlspecialchars($whatsapp_delete_url); ?>" 
                               target="_blank" 
                               rel="noopener noreferrer"
                               class="whatsapp-delete-btn">
                                <i class="fab fa-whatsapp"></i>
                                मैले फोटो डाउनलोड गरेँ, कृपया डिलिट गरिदिनुस्
                            </a>
                            <p class="whatsapp-delete-note">
                                <i class="fas fa-info-circle"></i> फोटो डाउनलोड गरिसकेपछि माथिको बटन थिच्नुहोस्
                            </p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div><!-- end folder-header-body -->
            </div><!-- end folder-header -->

            <!-- Security Assurance Panel -->
            <div class="security-panel">
                <div class="security-item">
                    <i class="fas fa-lock"></i> Private &amp; Secure Link
                </div>
                <div class="security-item">
                    <i class="fas fa-shield-alt"></i> Encrypted Transfer
                </div>
                <div class="security-item">
                    <i class="fas fa-user-shield"></i> Access-Controlled
                </div>
                <div class="security-item">
                    <i class="fas fa-camera"></i> Original Quality Files
                </div>
                <div class="security-note">
                    <i class="fas fa-info-circle"></i>
                    Only people with this link can view &amp; download these files
                </div>
            </div>

            <?php 
            // Check if preview is disabled - show download-only view
            $show_preview = !isset($folder['show_preview']) || $folder['show_preview'];
            if (!$show_preview): 
            ?>
                <!-- ── Download Only View (Preview Disabled) ── -->
                <div class="download-only-view text-center py-5">
                    <div class="mb-4" style="width:90px;height:90px;border-radius:50%;background:#dcfce7;display:flex;align-items:center;justify-content:center;margin:0 auto;">
                        <i class="fas fa-file-archive" style="font-size: 2.8rem; color: var(--primary-green);"></i>
                    </div>
                    <h4 class="mb-2 fw-bold" style="color:var(--text-primary);">
                        फाइलहरू डाउनलोड गर्नुहोस्
                    </h4>
                    <?php 
                    $file_count = count($photos);
                    $file_text = $file_count !== 1 ? 'फाइलहरू छन्' : 'फाइल छ';
                    ?>
                    <p style="color:var(--text-secondary);" class="mb-4">
                        यस फोल्डरमा <?php echo $file_count; ?> <?php echo $file_text; ?>।<br>
                        तलको बटन थिचेर ZIP फाइलमा एकैपटक डाउनलोड गर्नुहोस्।
                    </p>
                    <?php if ($folder['allow_zip_download'] && $file_count > 0): ?>
                        <a href="?token=<?php echo urlencode($token); ?>&download_all=1"
                           class="btn btn-success btn-lg download-all-btn px-5 py-3"
                           onclick="return startDownload(this.href, <?php echo json_encode(htmlspecialchars($folder['folder_name']) . '.zip'); ?>)">
                            <i class="fas fa-download me-2"></i>
                            सबै डाउनलोड गर्नुहोस् (<?php echo $file_count; ?> फाइलहरू)
                        </a>
                        <p style="color:var(--text-secondary);" class="mt-3 mb-0">
                            <small><i class="fas fa-file-archive me-1"></i> ZIP फाइलमा डाउनलोड हुन्छ</small>
                        </p>

                        <?php if ($whatsapp_delete_url): ?>
                        <!-- WhatsApp Photo Deletion Request -->
                        <div class="whatsapp-delete-request mt-4">
                            <a href="<?php echo htmlspecialchars($whatsapp_delete_url); ?>"
                               target="_blank"
                               rel="noopener noreferrer"
                               class="whatsapp-delete-btn">
                                <i class="fab fa-whatsapp"></i>
                                मैले फोटो डाउनलोड गरेँ, कृपया डिलिट गरिदिनुस्
                            </a>
                            <p class="whatsapp-delete-note">
                                <i class="fas fa-info-circle"></i> फोटो डाउनलोड गरिसकेपछि माथिको बटन थिच्नुहोस्
                            </p>
                        </div>
                        <?php endif; ?>
                    <?php elseif (!$folder['allow_zip_download']): ?>
                        <div class="alert alert-warning d-inline-block">
                            <i class="fas fa-info-circle me-2"></i>
                            डाउनलोड यस फोल्डरको लागि उपलब्ध छैन।
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info d-inline-block">
                            <i class="fas fa-folder-open me-2"></i>
                            यस फोल्डरमा अहिले कुनै फाइल छैन।
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <!-- ── Preview Enabled: Show Photos/Albums ── -->

            <?php if ($has_subfolders && $current_album !== null): ?>
            <!-- Breadcrumb navigation when inside an album -->
            <div class="breadcrumb-nav">
                <a href="?token=<?php echo urlencode($token); ?>">
                    <i class="fas fa-folder"></i> <?php echo htmlspecialchars($folder['folder_name']); ?>
                </a>
                <span class="separator"><i class="fas fa-chevron-right" style="font-size:0.75rem;"></i></span>
                <span>
                    <i class="fas fa-folder-open text-warning"></i>
                    <?php echo htmlspecialchars($current_album === '' ? 'General' : $current_album); ?>
                </span>
            </div>
            <?php endif; ?>

            <?php if ($has_subfolders && $current_album === null): ?>
                <!-- ── Sub-folder / Album Cards View ── -->
                <?php if (empty($subfolders)): ?>
                    <div class="text-center py-5" style="color:var(--text-secondary);">
                        <i class="fas fa-folder-open fa-4x mb-3" style="opacity:0.35;"></i>
                        <p>No albums in this folder yet.</p>
                    </div>
                <?php else: ?>
                    <div class="subfolder-grid">
                        <?php foreach ($subfolders as $sf_name => $sf_photos):
                            $display_name = ($sf_name === '') ? 'General' : $sf_name;
                            $album_url = '?token=' . urlencode($token) . '&album=' . urlencode($sf_name);
                        ?>
                            <a href="<?php echo htmlspecialchars($album_url, ENT_QUOTES, 'UTF-8'); ?>" class="subfolder-card">
                                <div class="subfolder-thumb">
                                    <i class="fas fa-folder folder-icon"></i>
                                </div>
                                <div class="subfolder-info">
                                    <div class="subfolder-name" title="<?php echo htmlspecialchars($display_name); ?>">
                                        <?php echo htmlspecialchars($display_name); ?>
                                    </div>
                                    <div class="subfolder-count">
                                        <i class="fas fa-photo-video"></i>
                                        <?php echo count($sf_photos); ?> file<?php echo count($sf_photos) !== 1 ? 's' : ''; ?>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            <?php elseif ($has_subfolders && $current_album !== null): ?>
                <!-- ── Album Drill-Down: Photos in Selected Album ── -->
                <?php if (empty($visible_photos)): ?>
                    <div class="text-center py-5" style="color:var(--text-secondary);">
                        <i class="fas fa-photo-video fa-4x mb-3" style="opacity:0.35;"></i>
                        <p>No files in this album.</p>
                    </div>
                <?php else: ?>
                    <div class="photo-grid">
                        <?php 
                        // Define extension arrays once for performance
                        $image_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
                        $video_extensions = ['mp4', 'mov', 'avi', 'webm', 'mkv', 'mpg', 'mpeg', '3gp', 'm4v', 'ogg'];
                        foreach ($visible_photos as $photo): 
                            $file_url = UPLOAD_URL . $photo['image_path'];
                            $pf_ext = strtolower(pathinfo($photo['image_path'], PATHINFO_EXTENSION));
                            // Determine file type, treating image extensions as photos even if stored as 'file'
                            $is_video = isset($photo['file_type']) && $photo['file_type'] === 'video';
                            $is_generic = isset($photo['file_type']) && $photo['file_type'] === 'file';
                            // If file is marked as 'file' but has an image extension, treat it as a photo
                            if ($is_generic && in_array($pf_ext, $image_extensions)) {
                                $is_generic = false;
                            }
                            // If file is marked as 'file' but has a video extension, treat it as a video
                            if ($is_generic && in_array($pf_ext, $video_extensions)) {
                                $is_generic = false;
                                $is_video = true;
                            }
                            $can_download = !$folder['max_downloads'] || $photo['download_count'] < $folder['max_downloads'];
                            $pf_icon = getFileTypeIcon($pf_ext);
                            $download_url_qs = '?token=' . urlencode($token) . '&download_photo=' . $photo['id'];
                            $photo_title_js = json_encode($photo['title'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                        ?>
                            <div class="photo-card">
                                <div class="photo-media">
                                <?php if ($is_video): ?>
                                    <div class="video-container" onclick="openVideoLightbox('<?php echo htmlspecialchars($file_url, ENT_QUOTES, 'UTF-8'); ?>', <?php echo $can_download ? json_encode($download_url_qs, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) : 'null'; ?>, <?php echo $photo_title_js; ?>)">
                                        <div class="video-play-overlay">
                                            <i class="fas fa-play-circle"></i>
                                        </div>
                                        <span class="badge bg-danger file-type-badge">VIDEO</span>
                                    </div>
                                <?php elseif ($is_generic): ?>
                                    <div class="video-container d-flex flex-column align-items-center justify-content-center" style="background:#f8f9fa;">
                                        <i class="fas <?php echo $pf_icon; ?>" style="font-size:4rem;color:#888;"></i>
                                        <small class="mt-2 text-muted text-uppercase" style="font-size:0.8rem;"><?php echo htmlspecialchars($pf_ext ?: 'FILE'); ?></small>
                                        <span class="badge bg-secondary file-type-badge">FILE</span>
                                    </div>
                                <?php else: ?>
                                    <img src="<?php echo htmlspecialchars($file_url, ENT_QUOTES, 'UTF-8'); ?>"
                                         alt="<?php echo htmlspecialchars($photo['title'], ENT_QUOTES, 'UTF-8'); ?>"
                                         onclick="openLightbox('<?php echo htmlspecialchars($file_url, ENT_QUOTES, 'UTF-8'); ?>', <?php echo $can_download ? json_encode($download_url_qs, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) : 'null'; ?>, <?php echo $photo_title_js; ?>)"
                                         loading="lazy"
                                         class="lazy-img"
                                         onload="this.classList.add('loaded')"
                                         onerror="handleImageError(this)"
                                         style="cursor: pointer;">
                                <?php endif; ?>
                                
                                <?php if ($can_download): ?>
                                    <a href="<?php echo htmlspecialchars($download_url_qs, ENT_QUOTES, 'UTF-8'); ?>"
                                       class="photo-media-download"
                                       title="Download"
                                       onclick="event.stopPropagation(); return startDownload(this.href, <?php echo $photo_title_js; ?>)">
                                        <i class="fas fa-arrow-down"></i>
                                    </a>
                                <?php else: ?>
                                    <span class="photo-media-download dl-limit-reached" title="Download limit reached">
                                        <i class="fas fa-ban"></i>
                                    </span>
                                <?php endif; ?>
                                </div>
                                
                                <div class="photo-info">
                                    <div class="photo-title" title="<?php echo htmlspecialchars($photo['title']); ?>">
                                        <?php echo htmlspecialchars($photo['title']); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <!-- ── Flat View (no sub-folders) ── -->
                <?php if (empty($photos)): ?>
                    <div class="text-center py-5" style="color:var(--text-secondary);">
                        <i class="fas fa-photo-video fa-4x mb-3" style="opacity:0.35;"></i>
                        <p>No files in this folder yet.</p>
                    </div>
                <?php else: ?>
                    <div class="photo-grid">
                        <?php 
                        // Define extension arrays once for performance
                        $image_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
                        $video_extensions = ['mp4', 'mov', 'avi', 'webm', 'mkv', 'mpg', 'mpeg', '3gp', 'm4v', 'ogg'];
                        foreach ($photos as $photo): 
                            $file_url = UPLOAD_URL . $photo['image_path'];
                            $pf_ext = strtolower(pathinfo($photo['image_path'], PATHINFO_EXTENSION));
                            // Determine file type, treating image extensions as photos even if stored as 'file'
                            $is_video = isset($photo['file_type']) && $photo['file_type'] === 'video';
                            $is_generic = isset($photo['file_type']) && $photo['file_type'] === 'file';
                            // If file is marked as 'file' but has an image extension, treat it as a photo
                            if ($is_generic && in_array($pf_ext, $image_extensions)) {
                                $is_generic = false;
                            }
                            // If file is marked as 'file' but has a video extension, treat it as a video
                            if ($is_generic && in_array($pf_ext, $video_extensions)) {
                                $is_generic = false;
                                $is_video = true;
                            }
                            $can_download = !$folder['max_downloads'] || $photo['download_count'] < $folder['max_downloads'];
                            $pf_icon = getFileTypeIcon($pf_ext);
                            $download_url_qs = '?token=' . urlencode($token) . '&download_photo=' . $photo['id'];
                            $photo_title_js = json_encode($photo['title'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                        ?>
                            <div class="photo-card">
                                <div class="photo-media">
                                <?php if ($is_video): ?>
                                    <div class="video-container" onclick="openVideoLightbox('<?php echo htmlspecialchars($file_url, ENT_QUOTES, 'UTF-8'); ?>', <?php echo $can_download ? json_encode($download_url_qs, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) : 'null'; ?>, <?php echo $photo_title_js; ?>)">
                                        <div class="video-play-overlay">
                                            <i class="fas fa-play-circle"></i>
                                        </div>
                                        <span class="badge bg-danger file-type-badge">VIDEO</span>
                                    </div>
                                <?php elseif ($is_generic): ?>
                                    <div class="video-container d-flex flex-column align-items-center justify-content-center" style="background:#f8f9fa;">
                                        <i class="fas <?php echo $pf_icon; ?>" style="font-size:4rem;color:#888;"></i>
                                        <small class="mt-2 text-muted text-uppercase" style="font-size:0.8rem;"><?php echo htmlspecialchars($pf_ext ?: 'FILE'); ?></small>
                                        <span class="badge bg-secondary file-type-badge">FILE</span>
                                    </div>
                                <?php else: ?>
                                    <img src="<?php echo htmlspecialchars($file_url, ENT_QUOTES, 'UTF-8'); ?>" 
                                         alt="<?php echo htmlspecialchars($photo['title'], ENT_QUOTES, 'UTF-8'); ?>"
                                         onclick="openLightbox('<?php echo htmlspecialchars($file_url, ENT_QUOTES, 'UTF-8'); ?>', <?php echo $can_download ? json_encode($download_url_qs, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) : 'null'; ?>, <?php echo $photo_title_js; ?>)"
                                         loading="lazy"
                                         class="lazy-img"
                                         onload="this.classList.add('loaded')"
                                         onerror="handleImageError(this)"
                                         style="cursor: pointer;">
                                <?php endif; ?>
                                
                                <?php if ($can_download): ?>
                                    <a href="<?php echo htmlspecialchars($download_url_qs, ENT_QUOTES, 'UTF-8'); ?>"
                                       class="photo-media-download"
                                       title="Download"
                                       onclick="event.stopPropagation(); return startDownload(this.href, <?php echo $photo_title_js; ?>)">
                                        <i class="fas fa-arrow-down"></i>
                                    </a>
                                <?php else: ?>
                                    <span class="photo-media-download dl-limit-reached" title="Download limit reached">
                                        <i class="fas fa-ban"></i>
                                    </span>
                                <?php endif; ?>
                                </div>
                                
                                <div class="photo-info">
                                    <div class="photo-title" title="<?php echo htmlspecialchars($photo['title']); ?>">
                                        <?php echo htmlspecialchars($photo['title']); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>
        <?php endif; ?> <!-- End of show_preview else block -->
        
        <!-- Mobile Banner Ads Section - Shows at bottom on mobile/tablet -->
        <?php if ($has_any_banner): ?>
        <div class="mobile-banner-section">
            <div class="mobile-banner-container">
                <div class="mobile-banner-header">
                    <span>Sponsored</span>
                </div>
                <div class="mobile-banners-grid <?php echo ($show_banner_a && $show_banner_b) ? 'has-two-banners' : ''; ?>">
                    <?php if ($show_banner_a): ?>
                    <div class="mobile-banner-item mobile-banner-a-in-bottom">
                        <?php if (!empty($banner_a_link)): ?>
                        <a href="<?php echo htmlspecialchars($banner_a_link); ?>" target="_blank" rel="noopener noreferrer">
                            <img src="<?php echo UPLOAD_URL . htmlspecialchars($banner_a_image); ?>" alt="Sponsored Banner" loading="lazy">
                            <span class="banner-badge">Ad</span>
                        </a>
                        <?php else: ?>
                        <img src="<?php echo UPLOAD_URL . htmlspecialchars($banner_a_image); ?>" alt="Sponsored Banner" loading="lazy">
                        <span class="banner-badge">Ad</span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($show_banner_b): ?>
                    <div class="mobile-banner-item">
                        <?php if (!empty($banner_b_link)): ?>
                        <a href="<?php echo htmlspecialchars($banner_b_link); ?>" target="_blank" rel="noopener noreferrer">
                            <img src="<?php echo UPLOAD_URL . htmlspecialchars($banner_b_image); ?>" alt="Sponsored Banner" loading="lazy">
                            <span class="banner-badge">Ad</span>
                        </a>
                        <?php else: ?>
                        <img src="<?php echo UPLOAD_URL . htmlspecialchars($banner_b_image); ?>" alt="Sponsored Banner" loading="lazy">
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
                <?php if ($contact_phone): ?>
                <a href="tel:<?php echo htmlspecialchars($contact_phone); ?>">
                    <i class="fas fa-phone"></i> <?php echo htmlspecialchars($contact_phone); ?>
                </a>
                <?php endif; ?>
                <?php if ($contact_email): ?>
                <a href="mailto:<?php echo htmlspecialchars($contact_email); ?>">
                    <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($contact_email); ?>
                </a>
                <?php endif; ?>
                <?php if ($whatsapp_number): ?>
                <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $whatsapp_number); ?>" target="_blank" rel="noopener noreferrer">
                    <i class="fab fa-whatsapp"></i> WhatsApp
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <div class="footer-security">
                <i class="fas fa-shield-alt"></i>
                <span>Secure file sharing by <strong><?php echo htmlspecialchars($site_name); ?></strong></span>
                <span>&nbsp;·&nbsp;</span>
                <i class="fas fa-lock"></i>
                <span>Your files are private &amp; protected</span>
            </div>
        </div>
    </div>
    
    <?php if ($has_any_banner): ?>
        </div><!-- End main-content -->
        
        <!-- Banner B (Right Side) - Desktop Only -->
        <?php if ($show_banner_b): ?>
        <div class="banner-ad banner-ad-right banner-ad-desktop">
            <?php if (!empty($banner_b_link)): ?>
            <a href="<?php echo htmlspecialchars($banner_b_link); ?>" target="_blank" rel="noopener noreferrer">
                <img src="<?php echo UPLOAD_URL . htmlspecialchars($banner_b_image); ?>" alt="Sponsored Banner">
            </a>
            <?php else: ?>
            <img src="<?php echo UPLOAD_URL . htmlspecialchars($banner_b_image); ?>" alt="Sponsored Banner">
            <?php endif; ?>
            <div class="banner-ad-label">Sponsored</div>
        </div>
        <?php endif; ?>
    </div><!-- End page-wrapper -->
    <?php endif; ?>
    
    <!-- Lightbox for image preview -->
    <div class="lightbox" id="lightbox" onclick="closeLightbox()">
        <span class="lightbox-close">&times;</span>
        <a id="lightbox-download-btn" class="lightbox-download-btn" title="Download" onclick="event.stopPropagation()">
            <i class="fas fa-arrow-down"></i>
        </a>
        <img src="" alt="Preview" id="lightbox-image">
    </div>
    
    <!-- Lightbox for video preview -->
    <div class="lightbox" id="video-lightbox" onclick="closeVideoLightbox()">
        <span class="lightbox-close">&times;</span>
        <a id="video-lightbox-download-btn" class="lightbox-download-btn" title="Download" onclick="event.stopPropagation()">
            <i class="fas fa-arrow-down"></i>
        </a>
        <video id="lightbox-video" controls onclick="event.stopPropagation()">
            <source src="" id="lightbox-video-src" type="video/mp4">
        </video>
    </div>
    
    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Download Progress Overlay -->
    <div id="downloadProgressOverlay">
        <div class="dl-card">
            <div class="dl-icon"><i class="fas fa-download" id="dlIcon"></i></div>
            <div class="dl-title" id="dlTitle">Preparing Download…</div>
            <div class="dl-filename" id="dlFilename"></div>
            <div class="dl-bar-wrap">
                <div class="dl-bar-fill" id="dlBar"></div>
            </div>
            <div class="dl-stats">
                <span id="dlPercent">0%</span>
                <span id="dlEta">Calculating…</span>
                <span id="dlSpeed"></span>
            </div>
            <div class="dl-size-info" id="dlSizeInfo"></div>
        </div>
    </div>

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
        /**
         * Handle image load errors gracefully
         * Instead of hiding the entire photo card (which causes blinking),
         * show a placeholder indicating the image is unavailable
         */
        function handleImageError(img) {
            // Create a placeholder div to replace the broken image
            var placeholder = document.createElement('div');
            placeholder.className = 'img-error-placeholder';
            placeholder.innerHTML = '<i class="fas fa-image"></i><span>Image unavailable</span>';
            
            // Replace the img element with the placeholder
            img.parentNode.replaceChild(placeholder, img);
        }
        
        function openLightbox(src, downloadUrl, title) {
            document.getElementById('lightbox-image').src = src;
            var dlBtn = document.getElementById('lightbox-download-btn');
            if (dlBtn) {
                if (downloadUrl) {
                    dlBtn.href = downloadUrl;
                    dlBtn.classList.add('visible');
                    dlBtn.onclick = function(e) {
                        e.stopPropagation();
                        return startDownload(downloadUrl, title || '');
                    };
                } else {
                    dlBtn.classList.remove('visible');
                }
            }
            document.getElementById('lightbox').classList.add('active');
        }
        
        function closeLightbox() {
            document.getElementById('lightbox').classList.remove('active');
        }
        
        function openVideoLightbox(src, downloadUrl, title) {
            var video = document.getElementById('lightbox-video');
            var sourceEl = document.getElementById('lightbox-video-src');
            // Determine MIME type from file extension
            var ext = src.split('?')[0].split('.').pop().toLowerCase();
            var mimeMap = {
                'mp4': 'video/mp4', 'mov': 'video/quicktime', 'm4v': 'video/mp4',
                'webm': 'video/webm', 'ogg': 'video/ogg', 'ogv': 'video/ogg',
                'avi': 'video/x-msvideo', 'mkv': 'video/x-matroska',
                'mpg': 'video/mpeg', 'mpeg': 'video/mpeg', '3gp': 'video/3gpp'
            };
            video.pause();
            sourceEl.src = src;
            sourceEl.type = mimeMap[ext] || 'video/mp4';
            video.load();
            var dlBtn = document.getElementById('video-lightbox-download-btn');
            if (dlBtn) {
                if (downloadUrl) {
                    dlBtn.href = downloadUrl;
                    dlBtn.classList.add('visible');
                    dlBtn.onclick = function(e) {
                        e.stopPropagation();
                        return startDownload(downloadUrl, title || '');
                    };
                } else {
                    dlBtn.classList.remove('visible');
                }
            }
            document.getElementById('video-lightbox').classList.add('active');
        }
        
        function closeVideoLightbox() {
            var video = document.getElementById('lightbox-video');
            video.pause();
            video.currentTime = 0;
            document.getElementById('video-lightbox').classList.remove('active');
        }
        
        // Close lightbox with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeLightbox();
                closeVideoLightbox();
            }
        });

        /**
         * Instant Download Handler
         * Uses native browser download (like IDM) for immediate download start.
         * The browser's download manager handles the file transfer directly,
         * avoiding the slow fetch-to-memory approach.
         */
    function startDownload(url, defaultName) {
            var overlay = document.getElementById('downloadProgressOverlay');
            var dlBar   = document.getElementById('dlBar');
            var dlPct   = document.getElementById('dlPercent');
            var dlEta   = document.getElementById('dlEta');
            var dlSpd   = document.getElementById('dlSpeed');
            var dlTitle = document.getElementById('dlTitle');
            var dlFile  = document.getElementById('dlFilename');
            var dlSize  = document.getElementById('dlSizeInfo');
            var dlIcon  = document.getElementById('dlIcon');

            // Show "Starting Download" notification with spinner
            dlBar.style.width      = '100%';
            dlBar.style.background = 'linear-gradient(90deg,#4CAF50,#8BC34A)';
            dlBar.style.backgroundSize = '';
            dlBar.style.animation  = '';
            dlPct.textContent      = '';
            dlEta.textContent      = '';
            dlSpd.textContent      = '';
            dlTitle.textContent    = 'Starting Download...';
            dlFile.textContent     = defaultName || '';
            dlSize.textContent     = '';
            dlIcon.className       = 'fas fa-spinner fa-spin';

            overlay.classList.add('dl-active');

            // Helper function to show success state
            function showSuccess() {
                dlTitle.textContent = 'Download Started!';
                dlSize.textContent  = 'Check your browser downloads';
                dlIcon.className    = 'fas fa-check-circle';
                setTimeout(function() { 
                    overlay.classList.remove('dl-active'); 
                }, 1500);
            }

            // Use hidden iframe for instant download (native browser download)
            // This triggers the browser's download manager immediately.
            // The iframe is intentionally reused across downloads for efficiency.
            var iframe = document.getElementById('downloadFrame');
            if (!iframe) {
                iframe = document.createElement('iframe');
                iframe.id = 'downloadFrame';
                iframe.name = 'downloadFrame';
                iframe.style.display = 'none';
                document.body.appendChild(iframe);
            }
            
            // Timeout fallback: if server responds with attachment header, onload won't fire
            var loadTimeout = setTimeout(showSuccess, 800);
            
            // Attach event handlers BEFORE setting src to avoid race condition
            iframe.onload = function() {
                clearTimeout(loadTimeout);
                showSuccess();
            };
            iframe.onerror = function() {
                clearTimeout(loadTimeout);
                dlTitle.textContent = 'Download Failed';
                dlSize.textContent  = 'Please try again';
                dlIcon.className    = 'fas fa-exclamation-circle';
                dlBar.style.background = '#dc3545';
                setTimeout(function() { 
                    overlay.classList.remove('dl-active'); 
                }, 2500);
            };
            
            // Trigger download
            iframe.src = url;

        return false;
    }
    </script>
    <script src="<?php echo BASE_URL; ?>/js/share.js"></script>
</body>
</html>
