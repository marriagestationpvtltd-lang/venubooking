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

$db = getDB();
$error_message = '';
$folder = null;
$photos = [];

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
            // Fetch all photos/videos in this folder
            $photos_stmt = $db->prepare("SELECT * FROM shared_photos WHERE folder_id = ? AND status = 'active' ORDER BY created_at DESC");
            $photos_stmt->execute([$folder['id']]);
            $photos = $photos_stmt->fetchAll();
        }
    } catch (Exception $e) {
        error_log('Folder page error: ' . $e->getMessage());
        $error_message = 'Unable to load folder. Please try again later.';
    }
}

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
                
                // Send file for download
                header('Content-Type: ' . $mime_type);
                header('Content-Disposition: attachment; filename="' . $download_filename . '"');
                header('Content-Length: ' . filesize($file_path));
                header('Cache-Control: no-cache, must-revalidate');
                header('Pragma: no-cache');
                
                readfile($file_path);
                exit;
            } else {
                $error_message = 'File not found.';
            }
        }
    }
}

// Handle Download All as ZIP
if (!$error_message && isset($_GET['download_all']) && $_GET['download_all'] === '1' && $folder['allow_zip_download']) {
    if (empty($photos)) {
        $error_message = 'No photos to download.';
    } else {
        // Create ZIP file
        $zip = new ZipArchive();
        
        // Create safe folder name for ZIP
        $safe_folder_name = preg_replace('/[^a-zA-Z0-9_\-\s]/u', '_', $folder['folder_name']);
        $safe_folder_name = preg_replace('/_+/', '_', $safe_folder_name);
        $safe_folder_name = trim($safe_folder_name, '_');
        if (empty($safe_folder_name)) {
            $safe_folder_name = 'photos';
        }
        
        // Generate unique ID for this download to avoid conflicts
        $unique_id = substr(uniqid(), -6);
        $zip_filename = $safe_folder_name . '_' . date('Y-m-d') . '_' . $unique_id . '.zip';
        $zip_path = sys_get_temp_dir() . '/' . 'folder_' . $unique_id . '.zip';
        
        // Register cleanup function to ensure temp file is deleted even on error
        register_shutdown_function(function() use ($zip_path) {
            if (file_exists($zip_path)) {
                @unlink($zip_path);
            }
        });
        
        if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
            $added_count = 0;
            $file_counter = [];
            
            foreach ($photos as $photo) {
                $file_path = UPLOAD_PATH . $photo['image_path'];
                
                // Security check
                $real_upload_path = realpath(UPLOAD_PATH);
                $real_file_path = realpath($file_path);
                
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
                    
                    // Add file to ZIP inside the folder
                    $zip->addFile($file_path, $zip_entry_name);
                    $added_count++;
                    
                    // Increment download count for each photo
                    $update_stmt = $db->prepare("UPDATE shared_photos SET download_count = download_count + 1 WHERE id = ?");
                    $update_stmt->execute([$photo['id']]);
                }
            }
            
            $zip->close();
            
            if ($added_count > 0 && file_exists($zip_path)) {
                // Increment folder total downloads
                $db->prepare("UPDATE shared_folders SET total_downloads = total_downloads + ? WHERE id = ?")->execute([$added_count, $folder['id']]);
                
                // Send ZIP file for download
                header('Content-Type: application/zip');
                header('Content-Disposition: attachment; filename="' . $zip_filename . '"');
                header('Content-Length: ' . filesize($zip_path));
                header('Cache-Control: no-cache, must-revalidate');
                header('Pragma: no-cache');
                
                readfile($zip_path);
                
                // Clean up temp file
                unlink($zip_path);
                exit;
            } else {
                $error_message = 'Failed to create ZIP file.';
            }
        } else {
            $error_message = 'Failed to create ZIP file.';
        }
    }
}

// Get site settings
$site_name = getSetting('site_name') ?: 'Photo Folder';
$site_logo = getSetting('site_logo');
?>
<!DOCTYPE html>
<html lang="ne">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $folder ? htmlspecialchars($folder['folder_name']) : 'Folder'; ?> - <?php echo htmlspecialchars($site_name); ?></title>
    
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
            background: #1a1a2e;
        }
        
        .photo-card video {
            width: 100%;
            height: 200px;
            object-fit: cover;
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
            background: rgba(0,0,0,0.3);
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .photo-card .video-play-overlay:hover {
            background: rgba(0,0,0,0.5);
        }
        
        .photo-card .video-play-overlay i {
            font-size: 3rem;
            color: white;
            text-shadow: 0 2px 10px rgba(0,0,0,0.5);
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
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h1 class="folder-title">
                            <i class="fas fa-folder-open"></i> 
                            <?php echo htmlspecialchars($folder['folder_name']); ?>
                        </h1>
                        
                        <?php if ($folder['description']): ?>
                            <p class="folder-description"><?php echo nl2br(htmlspecialchars($folder['description'])); ?></p>
                        <?php endif; ?>
                        
                        <div class="stats-badges">
                            <span class="stats-badge">
                                <i class="fas fa-photo-video text-primary"></i> 
                                <?php echo count($photos); ?> File<?php echo count($photos) !== 1 ? 's' : ''; ?>
                            </span>
                            <?php if ($folder['expires_at']): ?>
                                <span class="stats-badge">
                                    <i class="fas fa-clock text-warning"></i> 
                                    Expires: <?php echo date('M d, Y', strtotime($folder['expires_at'])); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-4 text-md-end mt-3 mt-md-0">
                        <?php if ($folder['allow_zip_download'] && count($photos) > 0): ?>
                            <a href="?token=<?php echo urlencode($token); ?>&download_all=1" class="btn btn-success download-all-btn">
                                <i class="fas fa-download me-2"></i> 
                                Download All (<?php echo count($photos); ?>)
                            </a>
                            <p class="text-muted mt-2 mb-0">
                                <small><i class="fas fa-file-archive"></i> Downloads as ZIP file in one folder</small>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Files Grid -->
            <?php if (empty($photos)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-photo-video fa-4x text-muted mb-3"></i>
                    <p class="text-muted">No files in this folder yet.</p>
                </div>
            <?php else: ?>
                <div class="photo-grid">
                    <?php foreach ($photos as $photo): 
                        $file_url = UPLOAD_URL . $photo['image_path'];
                        $is_video = isset($photo['file_type']) && $photo['file_type'] === 'video';
                        $can_download = !$folder['max_downloads'] || $photo['download_count'] < $folder['max_downloads'];
                    ?>
                        <div class="photo-card">
                            <?php if (file_exists(UPLOAD_PATH . $photo['image_path'])): ?>
                                <?php if ($is_video): ?>
                                    <div class="video-container" onclick="openVideoLightbox('<?php echo htmlspecialchars($file_url); ?>')" style="cursor: pointer;">
                                        <video muted preload="metadata">
                                            <source src="<?php echo htmlspecialchars($file_url); ?>#t=0.5" type="video/mp4">
                                        </video>
                                        <div class="video-play-overlay">
                                            <i class="fas fa-play-circle"></i>
                                        </div>
                                        <span class="badge bg-danger file-type-badge">VIDEO</span>
                                    </div>
                                <?php else: ?>
                                    <img src="<?php echo htmlspecialchars($file_url); ?>" 
                                         alt="<?php echo htmlspecialchars($photo['title']); ?>"
                                         onclick="openLightbox('<?php echo htmlspecialchars($file_url); ?>')"
                                         style="cursor: pointer;">
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="bg-secondary text-white d-flex align-items-center justify-content-center" style="height: 200px;">
                                    <i class="fas fa-<?php echo $is_video ? 'video' : 'image'; ?> fa-3x"></i>
                                </div>
                            <?php endif; ?>
                            
                            <div class="photo-info">
                                <div class="photo-title" title="<?php echo htmlspecialchars($photo['title']); ?>">
                                    <?php echo htmlspecialchars($photo['title']); ?>
                                </div>
                                
                                <?php if ($can_download): ?>
                                    <a href="?token=<?php echo urlencode($token); ?>&download_photo=<?php echo $photo['id']; ?>" 
                                       class="btn download-btn">
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
        
        <div class="footer-text">
            <i class="fas fa-shield-alt me-1"></i>
            Secure photo &amp; video sharing by <?php echo htmlspecialchars($site_name); ?>
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
    
    <script>
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
            sourceEl.src = src;
            // Determine MIME type from file extension
            var ext = src.split('?')[0].split('.').pop().toLowerCase();
            var mimeMap = {
                'mp4': 'video/mp4', 'mov': 'video/quicktime',
                'avi': 'video/x-msvideo', 'webm': 'video/webm',
                'mkv': 'video/x-matroska', 'mpg': 'video/mpeg',
                'mpeg': 'video/mpeg', '3gp': 'video/3gpp'
            };
            sourceEl.type = mimeMap[ext] || 'video/mp4';
            video.load();
            document.getElementById('video-lightbox').classList.add('active');
        }
        
        function closeVideoLightbox() {
            var video = document.getElementById('lightbox-video');
            video.pause();
            document.getElementById('video-lightbox').classList.remove('active');
        }
        
        // Close lightbox with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeLightbox();
                closeVideoLightbox();
            }
        });
    </script>
</body>
</html>
