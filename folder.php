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
            $real_upload_base = realpath(UPLOAD_PATH);
            if ($real_upload_base !== false) {
                $photos = array_values(array_filter($photos, function ($photo) use ($real_upload_base) {
                    if (empty($photo['image_path'])) {
                        return false;
                    }
                    $abs_path = UPLOAD_PATH . $photo['image_path'];
                    $real_path = realpath($abs_path);
                    return $real_path !== false
                        && strpos($real_path, $real_upload_base . DIRECTORY_SEPARATOR) === 0
                        && file_exists($abs_path);
                }));
            }
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
    
    <style>
        :root {
            --primary-green: #4CAF50;
            --dark-green: #2E7D32;
        }
        
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .folder-container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .folder-header {
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            padding: 30px;
            margin-bottom: 30px;
        }
        
        .folder-title {
            font-size: 1.8rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
        }
        
        .folder-title i {
            color: #ffc107;
        }
        
        .folder-description {
            color: #666;
            margin-bottom: 20px;
        }
        
        .download-all-btn {
            background: var(--primary-green);
            border: none;
            padding: 15px 40px;
            font-size: 1.2rem;
            border-radius: 50px;
            transition: all 0.3s;
            box-shadow: 0 5px 20px rgba(76, 175, 80, 0.3);
        }
        
        .download-all-btn:hover {
            background: var(--dark-green);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(76, 175, 80, 0.4);
        }
        
        .photo-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
        }
        
        .photo-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: all 0.3s;
        }
        
        .photo-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .photo-card img {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }
        
        /* Prevent blinking: hide images until loaded, then show them */
        .photo-card img.lazy-img {
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .photo-card img.lazy-img.loaded {
            opacity: 1;
        }
        
        /* Error state for failed images - show placeholder instead of hiding */
        .photo-card .img-error-placeholder {
            width: 100%;
            height: 200px;
            background: linear-gradient(135deg, #f5f5f5 0%, #e0e0e0 100%);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #999;
        }
        .photo-card .img-error-placeholder i {
            font-size: 3rem;
            margin-bottom: 8px;
        }
        .photo-card .img-error-placeholder span {
            font-size: 0.85rem;
        }
        
        .photo-card .photo-info {
            padding: 15px;
        }
        
        .photo-card .photo-title {
            font-weight: 500;
            color: #333;
            margin-bottom: 10px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .photo-card .download-btn {
            width: 100%;
            background: var(--primary-green);
            border: none;
            padding: 10px;
            border-radius: 8px;
            color: white;
            transition: background 0.3s;
        }
        
        .photo-card .download-btn:hover {
            background: var(--dark-green);
        }
        
        /* Video card styles */
        .photo-card .video-container {
            position: relative;
            width: 100%;
            height: 200px;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
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
            font-size: 3.5rem;
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
        
        .stats-badge {
            background: #f8f9fa;
            padding: 8px 15px;
            border-radius: 20px;
            display: inline-block;
            margin-right: 10px;
            font-size: 0.9rem;
        }
        
        .error-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            padding: 50px;
            text-align: center;
            max-width: 600px;
            margin: 50px auto;
        }
        
        .error-icon {
            font-size: 4rem;
            color: #dc3545;
            margin-bottom: 20px;
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
            height: 12px;
            background: #e8f5e9;
            border-radius: 6px;
            overflow: hidden;
            margin-bottom: 10px;
        }
        .dl-bar-fill {
            height: 100%;
            width: 0%;
            background: linear-gradient(90deg, #4CAF50, #8BC34A);
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
            background: rgba(0,0,0,0.9);
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
            .folder-header {
                padding: 20px;
            }
            
            .folder-title {
                font-size: 1.4rem;
            }
            
            .download-all-btn {
                width: 100%;
                padding: 12px 30px;
                font-size: 1rem;
            }
            
            .photo-grid {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
                gap: 15px;
            }
            
            .photo-card img {
                height: 150px;
            }
        }

        /* ── Company Brand Bar ── */
        .folder-brand-bar {
            display: flex;
            align-items: center;
            gap: 12px;
            padding-bottom: 20px;
            margin-bottom: 20px;
            border-bottom: 1px solid #f0f0f0;
        }
        .folder-brand-bar .brand-logo {
            height: 48px;
            max-width: 150px;
            object-fit: contain;
        }
        .brand-text .brand-name {
            font-size: 1rem;
            font-weight: 700;
            color: #333;
            line-height: 1.2;
            margin: 0;
        }
        .brand-text .brand-tagline {
            font-size: 0.76rem;
            color: #888;
            margin: 2px 0 0;
        }

        /* ── Security Panel ── */
        .security-panel {
            background: linear-gradient(135deg, #e8f5e9 0%, #f1f8e9 100%);
            border: 1px solid #c8e6c9;
            border-radius: 12px;
            padding: 14px 20px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }
        .security-item {
            display: flex;
            align-items: center;
            gap: 7px;
            font-size: 0.82rem;
            color: #2E7D32;
            font-weight: 500;
        }
        .security-item i {
            font-size: 1rem;
            color: #4CAF50;
        }
        .security-note {
            margin-left: auto;
            font-size: 0.76rem;
            color: #888;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .security-note i {
            color: #aaa;
        }

        /* ── Enhanced Footer ── */
        .footer-text {
            text-align: center;
            padding: 30px 20px;
            color: #999;
            font-size: 0.85rem;
        }
        .footer-contact {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 12px;
        }
        .footer-contact a {
            color: #666;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.85rem;
            transition: color 0.2s;
        }
        .footer-contact a:hover {
            color: #4CAF50;
        }
        .footer-security {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 6px;
            color: #aaa;
            font-size: 0.8rem;
            flex-wrap: wrap;
        }

        @media (max-width: 576px) {
            .security-note { display: none; }
            .folder-brand-bar { padding-bottom: 15px; margin-bottom: 15px; }
        }

        /* ── Sub-folder / Album Grid ── */
        .subfolder-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 20px;
        }

        .subfolder-card {
            background: white;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: all 0.3s;
            text-decoration: none;
            color: inherit;
            display: block;
        }

        .subfolder-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            color: inherit;
        }

        .subfolder-card .subfolder-thumb {
            position: relative;
            height: 160px;
            background: linear-gradient(135deg, #fff9c4 0%, #fff3e0 100%);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .subfolder-card .subfolder-thumb .folder-icon {
            font-size: 5rem;
            color: #ffc107;
            filter: drop-shadow(0 4px 8px rgba(255,193,7,0.3));
        }

        .subfolder-card .subfolder-thumb .thumb-preview {
            position: absolute;
            inset: 0;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2px;
            opacity: 0.55;
        }

        .subfolder-card .subfolder-thumb .thumb-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        /* Prevent blinking: hide thumb images until loaded */
        .subfolder-card .subfolder-thumb .thumb-preview img.lazy-img {
            opacity: 0;
            transition: opacity 0.2s ease;
        }
        .subfolder-card .subfolder-thumb .thumb-preview img.lazy-img.loaded {
            opacity: 1;
        }

        .subfolder-card .subfolder-thumb .folder-icon-overlay {
            position: absolute;
            font-size: 3.5rem;
            color: #ffc107;
            filter: drop-shadow(0 2px 6px rgba(0,0,0,0.4));
        }

        .subfolder-card .subfolder-info {
            padding: 14px 16px;
        }

        .subfolder-card .subfolder-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .subfolder-card .subfolder-count {
            font-size: 0.82rem;
            color: #888;
        }

        .breadcrumb-nav {
            background: white;
            border-radius: 12px;
            padding: 12px 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.06);
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .breadcrumb-nav a {
            color: var(--primary-green);
            text-decoration: none;
            font-weight: 500;
        }

        .breadcrumb-nav a:hover { text-decoration: underline; }

        .breadcrumb-nav .separator { color: #bbb; }

        .album-download-btn {
            background: var(--primary-green);
            border: none;
            padding: 10px 24px;
            font-size: 1rem;
            border-radius: 50px;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(76, 175, 80, 0.3);
        }

        .album-download-btn:hover {
            background: var(--dark-green);
            transform: translateY(-2px);
        }

        @media (max-width: 576px) {
            .subfolder-grid {
                grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
                gap: 14px;
            }
            .subfolder-card .subfolder-thumb { height: 120px; }
        }
    </style>
</head>
<body>
    <div class="folder-container">
        <?php if ($error_message): ?>
            <div class="error-container">
                <div class="error-icon">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
                <h3 class="text-danger mb-3">Access Denied</h3>
                <p class="text-muted mb-4"><?php echo htmlspecialchars($error_message); ?></p>
                <p class="text-muted">
                    <small>If you believe this is an error, please contact the sender.</small>
                </p>
            </div>
        <?php else: ?>
            <!-- Folder Header -->
            <div class="folder-header">
                <?php if ($site_logo && file_exists(UPLOAD_PATH . $site_logo)): ?>
                <div class="folder-brand-bar">
                    <img src="<?php echo UPLOAD_URL . htmlspecialchars($site_logo); ?>"
                         alt="<?php echo htmlspecialchars($site_name); ?>"
                         class="brand-logo">
                    <div class="brand-text">
                        <p class="brand-name"><?php echo htmlspecialchars($site_name); ?></p>
                        <p class="brand-tagline"><i class="fas fa-shield-alt"></i> Professional &amp; Secure File Sharing</p>
                    </div>
                </div>
                <?php endif; ?>
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h1 class="folder-title">
                            <?php if ($has_subfolders && $current_album !== null): ?>
                                <i class="fas fa-folder-open"></i>
                                <?php echo htmlspecialchars($current_album === '' ? 'General' : $current_album); ?>
                            <?php else: ?>
                                <i class="fas fa-folder-open"></i>
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
                                    <i class="fas fa-folder text-warning"></i>
                                    <?php echo count($subfolders); ?> Album<?php echo count($subfolders) !== 1 ? 's' : ''; ?>
                                </span>
                                <span class="stats-badge">
                                    <i class="fas fa-photo-video text-primary"></i>
                                    <?php echo count($photos); ?> File<?php echo count($photos) !== 1 ? 's' : ''; ?>
                                </span>
                            <?php else: ?>
                                <span class="stats-badge">
                                    <i class="fas fa-photo-video text-primary"></i> 
                                    <?php echo count($visible_photos); ?> File<?php echo count($visible_photos) !== 1 ? 's' : ''; ?>
                                </span>
                            <?php endif; ?>
                            <?php if ($folder['expires_at']): ?>
                                <span class="stats-badge">
                                    <i class="fas fa-clock text-warning"></i> 
                                    Expires: <?php echo date('M d, Y', strtotime($folder['expires_at'])); ?>
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
                                   onclick="return startDownload(this.href, <?php echo json_encode(($current_album === '' ? 'General' : $current_album) . '.zip'); ?>)">
                                    <i class="fas fa-download me-2"></i>
                                    Download Album (<?php echo count($visible_photos); ?>)
                                </a>
                                <p class="text-muted mt-2 mb-0">
                                    <small><i class="fas fa-file-archive"></i> Downloads album as ZIP</small>
                                </p>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

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
                    <div class="download-only-icon mb-4">
                        <i class="fas fa-file-archive" style="font-size: 5rem; color: #28a745;"></i>
                    </div>
                    <h4 class="mb-3">
                        <i class="fas fa-download me-2"></i>
                        फाइलहरू डाउनलोड गर्नुहोस्
                    </h4>
                    <p class="text-muted mb-4">
                        यस फोल्डरमा <?php echo count($photos); ?> फाइल<?php echo count($photos) !== 1 ? 'हरू' : ''; ?> छ<?php echo count($photos) !== 1 ? 'न्' : ''; ?>।<br>
                        तलको बटन थिचेर ZIP फाइलमा एकैपटक डाउनलोड गर्नुहोस्।
                    </p>
                    <?php if ($folder['allow_zip_download'] && count($photos) > 0): ?>
                        <a href="?token=<?php echo urlencode($token); ?>&download_all=1"
                           class="btn btn-success btn-lg download-all-btn px-5 py-3"
                           onclick="return startDownload(this.href, <?php echo json_encode(htmlspecialchars($folder['folder_name']) . '.zip'); ?>)">
                            <i class="fas fa-download me-2"></i> 
                            सबै डाउनलोड गर्नुहोस् (<?php echo count($photos); ?> files)
                        </a>
                        <p class="text-muted mt-3 mb-0">
                            <small><i class="fas fa-file-archive me-1"></i> ZIP फाइलमा डाउनलोड हुन्छ</small>
                        </p>
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
                    <div class="text-center py-5">
                        <i class="fas fa-folder-open fa-4x text-muted mb-3"></i>
                        <p class="text-muted">No albums in this folder yet.</p>
                    </div>
                <?php else: ?>
                    <div class="subfolder-grid">
                        <?php foreach ($subfolders as $sf_name => $sf_photos):
                            $display_name = ($sf_name === '') ? 'General' : $sf_name;
                            $album_url = '?token=' . urlencode($token) . '&album=' . urlencode($sf_name);
                            // Use first few images as preview thumbnails
                            $thumb_photos = array_filter($sf_photos, function($p) {
                                return (!isset($p['file_type']) || $p['file_type'] === 'photo');
                            });
                            $thumb_photos = array_values($thumb_photos);
                        ?>
                            <a href="<?php echo htmlspecialchars($album_url, ENT_QUOTES, 'UTF-8'); ?>" class="subfolder-card">
                                <div class="subfolder-thumb">
                                    <?php if (!empty($thumb_photos)): ?>
                                        <div class="thumb-preview">
                                            <?php foreach (array_slice($thumb_photos, 0, 4) as $tp): ?>
                                                <img src="<?php echo htmlspecialchars(UPLOAD_URL . $tp['image_path'], ENT_QUOTES, 'UTF-8'); ?>"
                                                     alt=""
                                                     loading="lazy"
                                                     class="lazy-img"
                                                     onload="this.classList.add('loaded')">
                                            <?php endforeach; ?>
                                        </div>
                                        <i class="fas fa-folder folder-icon-overlay"></i>
                                    <?php else: ?>
                                        <i class="fas fa-folder folder-icon"></i>
                                    <?php endif; ?>
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
                    <div class="text-center py-5">
                        <i class="fas fa-photo-video fa-4x text-muted mb-3"></i>
                        <p class="text-muted">No files in this album.</p>
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
                        ?>
                            <div class="photo-card">
                                <?php if ($is_video): ?>
                                    <div class="video-container" onclick="openVideoLightbox('<?php echo htmlspecialchars($file_url, ENT_QUOTES, 'UTF-8'); ?>')">
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
                                         onclick="openLightbox('<?php echo htmlspecialchars($file_url, ENT_QUOTES, 'UTF-8'); ?>')"
                                         loading="lazy"
                                         class="lazy-img"
                                         onload="this.classList.add('loaded')"
                                         onerror="handleImageError(this)"
                                         style="cursor: pointer;">
                                <?php endif; ?>
                                
                                <div class="photo-info">
                                    <div class="photo-title" title="<?php echo htmlspecialchars($photo['title']); ?>">
                                        <?php echo htmlspecialchars($photo['title']); ?>
                                    </div>
                                    
                                    <?php if ($can_download): ?>
                                        <a href="?token=<?php echo urlencode($token); ?>&download_photo=<?php echo $photo['id']; ?>"
                                           class="btn download-btn"
                                           onclick="return startDownload(this.href, <?php echo json_encode(htmlspecialchars($photo['title'])); ?>)">
                                            <i class="fas fa-download me-1"></i> Download
                                        </a>
                                    <?php else: ?>
                                        <button class="btn btn-secondary w-100" disabled>
                                            <i class="fas fa-ban me-1"></i> Limit Reached
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <!-- ── Flat View (no sub-folders): show all photos ── -->
                <?php if (empty($photos)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-photo-video fa-4x text-muted mb-3"></i>
                        <p class="text-muted">No files in this folder yet.</p>
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
                        ?>
                            <div class="photo-card">
                                <?php if ($is_video): ?>
                                    <div class="video-container" onclick="openVideoLightbox('<?php echo htmlspecialchars($file_url, ENT_QUOTES, 'UTF-8'); ?>')">
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
                                         onclick="openLightbox('<?php echo htmlspecialchars($file_url, ENT_QUOTES, 'UTF-8'); ?>')"
                                         loading="lazy"
                                         class="lazy-img"
                                         onload="this.classList.add('loaded')"
                                         onerror="handleImageError(this)"
                                         style="cursor: pointer;">
                                <?php endif; ?>
                                
                                <div class="photo-info">
                                    <div class="photo-title" title="<?php echo htmlspecialchars($photo['title']); ?>">
                                        <?php echo htmlspecialchars($photo['title']); ?>
                                    </div>
                                    
                                    <?php if ($can_download): ?>
                                        <a href="?token=<?php echo urlencode($token); ?>&download_photo=<?php echo $photo['id']; ?>"
                                           class="btn download-btn"
                                           onclick="return startDownload(this.href, <?php echo json_encode(htmlspecialchars($photo['title'])); ?>)">
                                            <i class="fas fa-download me-1"></i> Download
                                        </a>
                                    <?php else: ?>
                                        <button class="btn btn-secondary w-100" disabled>
                                            <i class="fas fa-ban me-1"></i> Limit Reached
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>
        <?php endif; ?> <!-- End of show_preview else block -->
        
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
    
    <!-- Lightbox for image preview -->
    <div class="lightbox" id="lightbox" onclick="closeLightbox()">
        <span class="lightbox-close">&times;</span>
        <img src="" alt="Preview" id="lightbox-image">
    </div>
    
    <!-- Lightbox for video preview -->
    <div class="lightbox" id="video-lightbox" onclick="closeVideoLightbox()">
        <span class="lightbox-close">&times;</span>
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
        
        function openLightbox(src) {
            document.getElementById('lightbox-image').src = src;
            document.getElementById('lightbox').classList.add('active');
        }
        
        function closeLightbox() {
            document.getElementById('lightbox').classList.remove('active');
        }
        
        function openVideoLightbox(src) {
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
</body>
</html>
