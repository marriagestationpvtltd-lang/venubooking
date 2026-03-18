<?php
/**
 * Public Photo Download Page
 * Users can download shared photos via unique download links
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$db = getDB();
$error_message = '';
$photo = null;

// Get download token from URL
$token = isset($_GET['token']) ? trim($_GET['token']) : '';

if (empty($token)) {
    $error_message = 'Invalid download link.';
} else {
    // Fetch photo by token
    $stmt = $db->prepare("SELECT * FROM shared_photos WHERE download_token = ?");
    $stmt->execute([$token]);
    $photo = $stmt->fetch();
    
    if (!$photo) {
        $error_message = 'Photo not found. The link may be invalid or expired.';
    } elseif ($photo['status'] === 'inactive' || $photo['status'] === 'expired') {
        $error_message = 'This download link is no longer active.';
    } elseif ($photo['expires_at'] && strtotime($photo['expires_at']) < time()) {
        $error_message = 'This download link has expired.';
        // Update status to expired
        $update_stmt = $db->prepare("UPDATE shared_photos SET status = 'expired' WHERE id = ?");
        $update_stmt->execute([$photo['id']]);
    } elseif ($photo['max_downloads'] && $photo['download_count'] >= $photo['max_downloads']) {
        $error_message = 'Maximum download limit reached for this photo.';
    } else {
        // Valid photo - check if file exists
        $file_path = UPLOAD_PATH . $photo['image_path'];
        if (!file_exists($file_path)) {
            $error_message = 'Photo file not found on server.';
        }
    }
}

// Handle download action
if (!$error_message && isset($_GET['download']) && $_GET['download'] === '1') {
    $file_path = UPLOAD_PATH . $photo['image_path'];
    
    // Security: Verify file is within uploads directory
    $real_upload_path = realpath(UPLOAD_PATH);
    $real_file_path = realpath($file_path);
    
    if ($real_file_path && $real_upload_path && strpos($real_file_path, $real_upload_path) === 0 && file_exists($file_path)) {
        // Increment download count
        $update_stmt = $db->prepare("UPDATE shared_photos SET download_count = download_count + 1 WHERE id = ?");
        $update_stmt->execute([$photo['id']]);
        
        // Get file info
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file_path);
        finfo_close($finfo);
        
        // Generate download filename (ASCII-safe for cross-platform compatibility)
        $ext = pathinfo($photo['image_path'], PATHINFO_EXTENSION);
        // Transliterate Nepali/Unicode to ASCII and sanitize
        $safe_title = preg_replace('/[^a-zA-Z0-9_\-\.\s]/u', '_', $photo['title']);
        $safe_title = preg_replace('/_+/', '_', $safe_title); // Remove consecutive underscores
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
        $error_message = 'File not found or access denied.';
    }
}

// Get site settings
$site_name = getSetting('site_name') ?: 'Photo Download';
$site_logo = getSetting('site_logo');
?>
<!DOCTYPE html>
<html lang="ne">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Download Photo - <?php echo htmlspecialchars($site_name); ?></title>
    
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
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .download-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            max-width: 600px;
            width: 100%;
            overflow: hidden;
        }
        
        .card-header {
            background: var(--primary-green);
            color: white;
            padding: 20px;
            text-align: center;
        }
        
        .card-header h1 {
            font-size: 1.5rem;
            margin: 0;
        }
        
        .card-body {
            padding: 30px;
        }
        
        .photo-preview {
            text-align: center;
            margin-bottom: 25px;
        }
        
        .photo-preview img {
            max-width: 100%;
            max-height: 400px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        
        .photo-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
        }
        
        .photo-description {
            color: #666;
            margin-bottom: 20px;
        }
        
        .download-btn {
            background: var(--primary-green);
            border: none;
            padding: 15px 40px;
            font-size: 1.1rem;
            border-radius: 50px;
            transition: all 0.3s;
        }
        
        .download-btn:hover {
            background: var(--dark-green);
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(76, 175, 80, 0.3);
        }
        
        .error-icon {
            font-size: 4rem;
            color: #dc3545;
            margin-bottom: 20px;
        }
        
        .success-icon {
            font-size: 4rem;
            color: var(--primary-green);
            margin-bottom: 20px;
        }
        
        .meta-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
            font-size: 0.9rem;
            color: #666;
        }
        
        .meta-info i {
            color: var(--primary-green);
            margin-right: 5px;
        }
        
        .footer-text {
            text-align: center;
            padding: 15px;
            color: #999;
            font-size: 0.85rem;
            border-top: 1px solid #eee;
        }
    </style>
</head>
<body>
    <div class="download-card">
        <div class="card-header">
            <?php if ($site_logo && file_exists(UPLOAD_PATH . $site_logo)): ?>
                <img src="<?php echo UPLOAD_URL . htmlspecialchars($site_logo); ?>" alt="Logo" style="height: 40px; margin-bottom: 10px;">
            <?php endif; ?>
            <h1><i class="fas fa-download me-2"></i> Photo Download</h1>
        </div>
        
        <div class="card-body text-center">
            <?php if ($error_message): ?>
                <div class="error-icon">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
                <h3 class="text-danger mb-3">Download Not Available</h3>
                <p class="text-muted mb-4"><?php echo htmlspecialchars($error_message); ?></p>
                <p class="text-muted">
                    <small>If you believe this is an error, please contact the administrator.</small>
                </p>
            <?php else: ?>
                <div class="photo-preview">
                    <img src="<?php echo UPLOAD_URL . htmlspecialchars($photo['image_path']); ?>" 
                         alt="<?php echo htmlspecialchars($photo['title']); ?>">
                </div>
                
                <div class="photo-title">
                    <?php echo htmlspecialchars($photo['title']); ?>
                </div>
                
                <?php if ($photo['description']): ?>
                    <div class="photo-description">
                        <?php echo nl2br(htmlspecialchars($photo['description'])); ?>
                    </div>
                <?php endif; ?>
                
                <a href="?token=<?php echo urlencode($token); ?>&download=1" class="btn btn-success download-btn">
                    <i class="fas fa-download me-2"></i> Download Photo
                </a>
                
                <div class="meta-info">
                    <div class="row">
                        <div class="col-6 text-start">
                            <i class="fas fa-download"></i>
                            Downloads: <?php echo $photo['download_count']; ?>
                            <?php if ($photo['max_downloads']): ?>
                                / <?php echo $photo['max_downloads']; ?>
                            <?php endif; ?>
                        </div>
                        <div class="col-6 text-end">
                            <?php if ($photo['expires_at']): ?>
                                <i class="fas fa-clock"></i>
                                Expires: <?php echo date('M d, Y', strtotime($photo['expires_at'])); ?>
                            <?php else: ?>
                                <i class="fas fa-infinity"></i>
                                No expiry
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="footer-text">
            <i class="fas fa-shield-alt me-1"></i>
            Secure photo sharing by <?php echo htmlspecialchars($site_name); ?>
        </div>
    </div>
    
    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
