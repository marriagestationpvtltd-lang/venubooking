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
    try {
        // Fetch photo by token
        $stmt = $db->prepare("SELECT * FROM shared_photos WHERE download_token = ?");
        $stmt->execute([$token]);
        $photo = $stmt->fetch();
        
        if (!$photo) {
            $error_message = 'File not found. The link may be invalid or expired.';
        } elseif ($photo['status'] === 'inactive' || $photo['status'] === 'expired') {
            $error_message = 'This download link is no longer active.';
        } elseif ($photo['expires_at'] && strtotime($photo['expires_at']) < time()) {
            $error_message = 'This download link has expired.';
            // Update status to expired
            $update_stmt = $db->prepare("UPDATE shared_photos SET status = 'expired' WHERE id = ?");
            $update_stmt->execute([$photo['id']]);
        } elseif ($photo['max_downloads'] && $photo['download_count'] >= $photo['max_downloads']) {
            $error_message = 'Maximum download limit reached for this file.';
        } else {
            // Valid file - check if file exists
            $file_path = UPLOAD_PATH . $photo['image_path'];
            if (!file_exists($file_path)) {
                $error_message = 'File not found on server.';
            }
        }
    } catch (Throwable $e) {
        error_log('Download page error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
        $error_message = 'Unable to load file. Please try again later.';
    }
}

// Handle download action
if (!$error_message && isset($_GET['download']) && $_GET['download'] === '1') {
    try {
        $file_path = UPLOAD_PATH . $photo['image_path'];
        
        // Security: Verify file is within uploads directory
        $real_upload_path = realpath(UPLOAD_PATH);
        $real_file_path = realpath($file_path);
        
        if ($real_file_path && $real_upload_path && strpos($real_file_path, $real_upload_path) === 0 && file_exists($file_path)) {
            // Detect MIME type using robust helper (handles missing/broken finfo extension)
            $mime_type = detectMimeType($file_path);
            
            // Generate download filename (ASCII-safe for cross-platform compatibility)
            $ext = pathinfo($photo['image_path'], PATHINFO_EXTENSION);
            // Transliterate Nepali/Unicode to ASCII and sanitize
            $safe_title = preg_replace('/[^a-zA-Z0-9_\-\.\s]/u', '_', $photo['title']);
            $safe_title = preg_replace('/_+/', '_', $safe_title); // Remove consecutive underscores
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
            // Mirrors the chunked-upload approach: the client (browser/download
            // manager) can request any byte range so that interrupted downloads can
            // be resumed without restarting from the beginning.  This is especially
            // important on shared-hosting connections that may drop mid-transfer.
            $range_start  = 0;
            $range_end    = (int)$file_size - 1;
            $is_range_req = false;

            $http_range = isset($_SERVER['HTTP_RANGE']) ? trim($_SERVER['HTTP_RANGE']) : '';
            if ($http_range !== '') {
                if (preg_match('/^bytes=(\d*)-(\d*)$/i', $http_range, $rm)) {
                    $req_start = ($rm[1] !== '') ? (int)$rm[1] : 0;
                    $req_end   = ($rm[2] !== '') ? (int)$rm[2] : (int)$file_size - 1;

                    // Clamp end to last byte
                    if ($req_end >= (int)$file_size) {
                        $req_end = (int)$file_size - 1;
                    }

                    if ($req_start > $req_end || $req_start >= (int)$file_size) {
                        // Invalid range — 416 Range Not Satisfiable
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
            // Subsequent range requests for the same file are continuations.
            if (!$is_range_req || $range_start === 0) {
                $update_stmt = $db->prepare("UPDATE shared_photos SET download_count = download_count + 1 WHERE id = ?");
                $update_stmt->execute([$photo['id']]);
            }

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
            $error_message = 'File not found or access denied.';
        }
    } catch (Throwable $e) {
        error_log('File download error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
        // headers_sent() is true only when an exception occurs AFTER flush() has
        // already pushed the Content-Type/binary headers to the browser (e.g. a DB
        // error mid-stream).  In that case we cannot safely send an HTML page, so
        // we stop immediately.  When the exception occurs before flush() (e.g. a
        // PDOException from the download-count update), headers_sent() is false and
        // we fall through to show a user-friendly error page instead.
        if (headers_sent()) {
            // Cannot output HTML after binary headers were sent – just stop.
            exit;
        }
        $error_message = 'Download failed. Please try again.';
    }
}

// Get site settings
$site_name = getSetting('site_name') ?: 'Photo Download';
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
    <title>Download File - <?php echo htmlspecialchars($site_name); ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/share.css">
    
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

        /* ── Enhanced Card Header ── */
        .card-header .company-tagline {
            font-size: 0.8rem;
            opacity: 0.85;
            margin: 4px 0 0;
        }

        /* ── Security Trust Badges ── */
        .security-trust-row {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #f0f0f0;
        }
        .trust-badge {
            display: flex;
            align-items: center;
            gap: 6px;
            background: #f0fdf0;
            border: 1px solid #c8e6c9;
            border-radius: 20px;
            padding: 6px 14px;
            font-size: 0.78rem;
            color: #2E7D32;
            font-weight: 500;
        }
        .trust-badge i {
            color: #4CAF50;
            font-size: 0.85rem;
        }

        /* ── Enhanced Footer ── */
        .footer-contact-row {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 10px;
        }
        .footer-contact-row a {
            color: #666;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.82rem;
            transition: color 0.2s;
        }
        .footer-contact-row a:hover {
            color: #4CAF50;
        }

    </style>
</head>
<body>
    <div class="download-card">
        <div class="card-header">
            <?php if ($site_logo && file_exists(UPLOAD_PATH . $site_logo)): ?>
                <img src="<?php echo UPLOAD_URL . htmlspecialchars($site_logo); ?>" alt="Logo" style="height: 40px; margin-bottom: 10px;">
            <?php endif; ?>
            <h1><i class="fas fa-download me-2"></i> <?php echo htmlspecialchars($site_name); ?></h1>
            <p class="company-tagline"><i class="fas fa-shield-alt me-1"></i> Secure File Sharing</p>
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
                <?php
                    $file_ext = strtolower(pathinfo($photo['image_path'], PATHINFO_EXTENSION));
                    $photo_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                    $video_exts = ['mp4', 'mov', 'avi', 'webm', 'mkv', 'mpg', 'mpeg', '3gp', 'm4v', 'ogv'];
                    $is_downloadable_image = in_array($file_ext, $photo_exts);
                    $is_video_file = in_array($file_ext, $video_exts);
                    $file_icon_class = getFileTypeIcon($file_ext);
                    $file_url = UPLOAD_URL . $photo['image_path'];
                    $video_mime_map = [
                        'mp4' => 'video/mp4', 'mov' => 'video/quicktime', 'm4v' => 'video/mp4',
                        'webm' => 'video/webm', 'ogg' => 'video/ogg', 'ogv' => 'video/ogg',
                        'avi' => 'video/x-msvideo', 'mkv' => 'video/x-matroska',
                        'mpg' => 'video/mpeg', 'mpeg' => 'video/mpeg', '3gp' => 'video/3gpp'
                    ];
                    $video_mime = isset($video_mime_map[$file_ext]) ? $video_mime_map[$file_ext] : 'video/mp4';
                ?>

                <?php if ($is_downloadable_image): ?>
                <div class="photo-preview">
                    <img src="<?php echo htmlspecialchars($file_url); ?>" 
                         alt="<?php echo htmlspecialchars($photo['title']); ?>">
                </div>
                <?php elseif ($is_video_file): ?>
                <div class="photo-preview">
                    <video controls style="max-width:100%;max-height:400px;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,0.1);">
                        <source src="<?php echo htmlspecialchars($file_url); ?>" type="<?php echo htmlspecialchars($video_mime); ?>">
                        Your browser does not support the video tag.
                    </video>
                </div>
                <?php else: ?>
                <div class="photo-preview">
                    <i class="fas <?php echo htmlspecialchars($file_icon_class); ?>" style="font-size:5rem;margin:20px 0;display:block;"></i>
                    <div class="text-muted" style="font-size:0.85rem;">
                        <?php echo strtoupper(htmlspecialchars($file_ext)) ?: 'FILE'; ?> File
                        <?php if (!empty($photo['file_size'])): ?>
                            &bull; <?php echo htmlspecialchars(formatFileSize($photo['file_size'])); ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="photo-title">
                    <?php echo htmlspecialchars($photo['title']); ?>
                </div>
                
                <?php if ($photo['description']): ?>
                    <div class="photo-description">
                        <?php echo nl2br(htmlspecialchars($photo['description'])); ?>
                    </div>
                <?php endif; ?>
                
                <a href="?token=<?php echo urlencode($token); ?>&download=1"
                   class="btn btn-success download-btn"
                   onclick="return startDownload(this.href, <?php echo json_encode(htmlspecialchars($photo['title'])); ?>)">
                    <i class="fas fa-download me-2"></i> Download File
                </a>

                <!-- Security Trust Badges -->
                <div class="security-trust-row">
                    <span class="trust-badge"><i class="fas fa-lock"></i> Private Link</span>
                    <span class="trust-badge"><i class="fas fa-shield-alt"></i> Secure Transfer</span>
                    <span class="trust-badge"><i class="fas fa-user-shield"></i> Protected</span>
                    <span class="trust-badge"><i class="fas fa-file-check"></i> Original Quality</span>
                </div>
                
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
                                Expires: <?php echo date('M d, Y', strtotime($photo['expires_at'])); ?> (<?php echo convertToNepaliDate($photo['expires_at']); ?>)
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
            <?php if ($contact_phone || $contact_email || $whatsapp_number): ?>
            <div class="footer-contact-row">
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
            <div>
                <i class="fas fa-shield-alt me-1"></i>
                Secure file sharing by <strong><?php echo htmlspecialchars($site_name); ?></strong>
                &nbsp;·&nbsp;
                <i class="fas fa-lock me-1"></i>Your files are private &amp; protected
            </div>
        </div>
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
     * Instant Download Handler
     * Uses native browser download (like IDM) for immediate download start.
     * The browser's download manager handles the file transfer directly,
     * avoiding the slow fetch-to-memory approach.
     */
    function startDownload(url, defaultName) {
        var overlay  = document.getElementById('downloadProgressOverlay');
        var dlBar    = document.getElementById('dlBar');
        var dlPct    = document.getElementById('dlPercent');
        var dlEta    = document.getElementById('dlEta');
        var dlSpd    = document.getElementById('dlSpeed');
        var dlTitle  = document.getElementById('dlTitle');
        var dlFile   = document.getElementById('dlFilename');
        var dlSize   = document.getElementById('dlSizeInfo');
        var dlIcon   = document.getElementById('dlIcon');

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

        return false; // Prevent default link navigation
    }
    </script>
    <script src="<?php echo BASE_URL; ?>/js/share.js"></script>
</body>
</html>
