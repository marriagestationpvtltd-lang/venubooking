<?php
/**
 * View Shared Folder & Upload Photos
 * Admin can view folder contents and upload multiple photos
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();
$current_user = getCurrentUser();
$db = getDB();

// Get folder ID
$folder_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$folder_id) {
    header('Location: index.php');
    exit;
}

// Fetch folder
$stmt = $db->prepare("SELECT sf.*, u.full_name as created_by_name 
                      FROM shared_folders sf 
                      LEFT JOIN users u ON sf.created_by = u.id 
                      WHERE sf.id = ?");
$stmt->execute([$folder_id]);
$folder = $stmt->fetch();

if (!$folder) {
    $_SESSION['error_message'] = 'Folder not found.';
    header('Location: index.php');
    exit;
}

// Handle individual photo delete
if (isset($_GET['delete_photo']) && is_numeric($_GET['delete_photo'])) {
    $photo_id = intval($_GET['delete_photo']);
    
    $photo_stmt = $db->prepare("SELECT image_path, thumbnail_path, title FROM shared_photos WHERE id = ? AND folder_id = ?");
    $photo_stmt->execute([$photo_id, $folder_id]);
    $photo = $photo_stmt->fetch();
    
    if ($photo) {
        $delete_stmt = $db->prepare("DELETE FROM shared_photos WHERE id = ?");
        if ($delete_stmt->execute([$photo_id])) {
            // Delete physical file
            $file_path = UPLOAD_PATH . $photo['image_path'];
            $real_upload_path = realpath(UPLOAD_PATH);
            $real_file_path = realpath($file_path);
            
            if ($real_file_path && $real_upload_path && strpos($real_file_path, $real_upload_path) === 0) {
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
            }

            // Delete thumbnail if it exists
            if (!empty($photo['thumbnail_path'])) {
                $thumb_file_path = UPLOAD_PATH . $photo['thumbnail_path'];
                $real_thumb_path = realpath($thumb_file_path);
                if ($real_thumb_path && $real_upload_path && strpos($real_thumb_path, $real_upload_path) === 0) {
                    @unlink($thumb_file_path);
                }
            }
            
            logActivity($current_user['id'], 'Deleted photo from folder', 'shared_photos', $photo_id, "Deleted: " . $photo['title']);
            $_SESSION['success_message'] = 'Photo deleted successfully!';
        }
    }
    
    header('Location: view.php?id=' . $folder_id);
    exit;
}

$page_title = 'Folder: ' . $folder['folder_name'];
require_once __DIR__ . '/../includes/header.php';

// Check for messages
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : '';

if (isset($_GET['success'])) {
    if ($_GET['success'] === 'created') {
        $success_message = 'Folder created successfully! Now upload photos below.';
    } elseif (is_numeric($_GET['success'])) {
        $count = intval($_GET['success']);
        $success_message = $count . ' file' . ($count > 1 ? 's' : '') . ' uploaded successfully!';
    }
}

unset($_SESSION['success_message']);
unset($_SESSION['error_message']);

// Fetch photos in this folder, ordered by subfolder_name then created_at
$photos_stmt = $db->prepare("SELECT * FROM shared_photos WHERE folder_id = ? ORDER BY COALESCE(subfolder_name,'') ASC, created_at DESC");
$photos_stmt->execute([$folder_id]);
$photos = $photos_stmt->fetchAll();

// Group photos by subfolder_name for admin display
$photos_by_subfolder = [];
foreach ($photos as $photo) {
    $sf = ($photo['subfolder_name'] !== null && $photo['subfolder_name'] !== '') ? $photo['subfolder_name'] : '';
    $photos_by_subfolder[$sf][] = $photo;
}
ksort($photos_by_subfolder);

// Generate folder URL
$folder_url = BASE_URL . '/folder.php?token=' . urlencode($folder['download_token']);
$is_expired = ($folder['expires_at'] && strtotime($folder['expires_at']) < time()) || $folder['status'] === 'expired';
?>

<!-- Include Image Upload Handler CSS -->
<link rel="stylesheet" href="<?php echo htmlspecialchars(BASE_URL); ?>/admin/css/image-upload-handler.css">

<style>
.photo-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 15px;
    margin-top: 20px;
}

.photo-item {
    position: relative;
    aspect-ratio: 1;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: transform 0.2s;
}

.photo-item:hover {
    transform: scale(1.02);
}

.photo-item img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

/* Video thumbnail styles */
.video-thumbnail {
    position: relative;
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
}

.video-play-icon {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    color: rgba(255,255,255,0.85);
    font-size: 2.5rem;
    text-shadow: 0 2px 10px rgba(0,0,0,0.5);
    pointer-events: none;
    transition: transform 0.2s, color 0.2s;
}

.video-thumbnail:hover .video-play-icon {
    transform: translate(-50%, -50%) scale(1.15);
    color: #fff;
}

.video-badge {
    position: absolute;
    top: 5px;
    left: 5px;
    font-size: 0.65rem;
    padding: 2px 6px;
}

/* Photo item clickable */
.photo-item img {
    cursor: pointer;
}
.photo-item img:hover {
    opacity: 0.9;
}

/* Admin lightbox */
.admin-lightbox {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.92);
    z-index: 9999;
    align-items: center;
    justify-content: center;
}
.admin-lightbox.active {
    display: flex;
}
.admin-lightbox img {
    max-width: 90vw;
    max-height: 90vh;
    border-radius: 8px;
    object-fit: contain;
}
.admin-lightbox video {
    max-width: 90vw;
    max-height: 90vh;
    border-radius: 8px;
    outline: none;
}
.admin-lightbox-close {
    position: absolute;
    top: 18px;
    right: 28px;
    color: white;
    font-size: 2.2rem;
    cursor: pointer;
    line-height: 1;
    z-index: 10000;
    opacity: 0.8;
    transition: opacity 0.2s;
}
.admin-lightbox-close:hover { opacity: 1; }

.photo-item .photo-overlay {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background: linear-gradient(transparent, rgba(0,0,0,0.7));
    padding: 30px 10px 10px;
    color: white;
    opacity: 0;
    transition: opacity 0.2s;
}

.photo-item:hover .photo-overlay {
    opacity: 1;
}

.photo-item .photo-checkbox {
    position: absolute;
    top: 8px;
    left: 8px;
    z-index: 10;
}

.photo-item .delete-btn {
    position: absolute;
    top: 5px;
    right: 5px;
    background: rgba(220, 53, 69, 0.9);
    color: white;
    border: none;
    border-radius: 50%;
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.2s;
    cursor: pointer;
}

.photo-item:hover .delete-btn {
    opacity: 1;
}

.folder-stats {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 15px;
}

.stat-item {
    text-align: center;
    padding: 10px;
}

.stat-item i {
    font-size: 1.5rem;
    color: var(--primary-green);
}

.stat-item .stat-value {
    font-size: 1.5rem;
    font-weight: bold;
    color: #333;
}

.stat-item .stat-label {
    color: #666;
    font-size: 0.85rem;
}
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4><i class="fas fa-folder-open text-warning"></i> <?php echo htmlspecialchars($folder['folder_name']); ?></h4>
        <?php if ($folder['description']): ?>
            <p class="text-muted mb-0"><?php echo htmlspecialchars($folder['description']); ?></p>
        <?php endif; ?>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-danger" id="bulkDeleteBtn" style="display: none;">
            <i class="fas fa-trash"></i> Delete Selected (<span id="selectedCount">0</span>)
        </button>
        <a href="edit.php?id=<?php echo $folder_id; ?>" class="btn btn-warning">
            <i class="fas fa-edit"></i> Edit Folder
        </a>
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Folders
        </a>
    </div>
</div>

<!-- Hidden CSRF token for AJAX requests -->
<input type="hidden" id="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8'); ?>">
<input type="hidden" id="folder_id" value="<?php echo $folder_id; ?>">

<?php if ($success_message): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($error_message): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Folder Info & Shareable Link -->
<div class="row mb-4">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-success text-white">
                <i class="fas fa-link"></i> Shareable Folder Link
            </div>
            <div class="card-body">
                <div class="input-group">
                    <input type="text" class="form-control" id="folderLink" value="<?php echo htmlspecialchars($folder_url); ?>" readonly>
                    <button class="btn btn-primary copy-link-btn" type="button" data-url="<?php echo htmlspecialchars($folder_url); ?>">
                        <i class="fas fa-copy"></i> Copy Link
                    </button>
                    <a href="<?php echo $folder_url; ?>" class="btn btn-info" target="_blank">
                        <i class="fas fa-external-link-alt"></i> Preview
                    </a>
                </div>
                <small class="text-muted mt-2 d-block">
                    <i class="fas fa-info-circle"></i> यो लिङ्क कसैलाई पनि पठाउनुहोस् - उनीहरूले फोटो हेर्न र डाउनलोड गर्न सक्छन्
                </small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="folder-stats">
            <div class="row">
                <div class="col-6 stat-item">
                    <i class="fas fa-photo-video"></i>
                    <div class="stat-value"><?php echo count($photos); ?></div>
                    <div class="stat-label">Files</div>
                </div>
                <div class="col-6 stat-item">
                    <i class="fas fa-download"></i>
                    <div class="stat-value"><?php echo $folder['total_downloads']; ?></div>
                    <div class="stat-label">Downloads</div>
                </div>
            </div>
            <div class="text-center mt-2">
                <?php if ($is_expired): ?>
                    <span class="badge bg-secondary">Expired</span>
                <?php elseif ($folder['status'] === 'active'): ?>
                    <span class="badge bg-success">Active</span>
                <?php else: ?>
                    <span class="badge bg-secondary"><?php echo ucfirst($folder['status']); ?></span>
                <?php endif; ?>
                
                <?php if ($folder['expires_at']): ?>
                    <br><small class="text-muted">Expires: <?php echo date('M d, Y', strtotime($folder['expires_at'])); ?></small>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Upload Section -->
<div class="card mb-4">
    <div class="card-header bg-white">
        <h5 class="mb-0"><i class="fas fa-cloud-upload-alt"></i> Upload Files to this Folder</h5>
    </div>
    <div class="card-body">
        <div class="alert alert-info mb-3">
            <i class="fas fa-info-circle"></i> <strong>Bulk Upload:</strong> 
            तपाईं एकैपटकमा धेरै फाइलहरु अपलोड गर्न सक्नुहुन्छ।<br>
            <i class="fas fa-file"></i> कुनै पनि फाइल: फोटो, भिडियो, ZIP, PDF, Word, Excel र अन्य सबै (५० GB सम्म)<br>
            <i class="fas fa-video"></i> ठूलो भिडियो/फाइल chunk गरेर background मा अपलोड हुन्छ
        </div>
        
        <form id="uploadForm" method="POST" action="ajax-upload.php" enctype="multipart/form-data">
            <input type="hidden" name="folder_id" value="<?php echo $folder_id; ?>">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8'); ?>">
            
            <!-- Sub-folder/Album Name -->
            <div class="mb-3">
                <label for="subfolderNameInput" class="form-label fw-semibold">
                    <i class="fas fa-folder-plus text-warning"></i> Album / Sub-folder Name
                    <small class="text-muted fw-normal ms-1">(optional – groups photos into albums)</small>
                </label>
                <?php
                // Collect existing subfolder names for datalist
                $sf_stmt = $db->prepare("SELECT DISTINCT subfolder_name FROM shared_photos WHERE folder_id = ? AND subfolder_name IS NOT NULL AND subfolder_name <> '' ORDER BY subfolder_name ASC");
                $sf_stmt->execute([$folder_id]);
                $existing_subfolders = $sf_stmt->fetchAll(PDO::FETCH_COLUMN);
                ?>
                <input type="text" class="form-control" id="subfolderNameInput" name="subfolder_name"
                       placeholder="e.g. Ceremony, Reception, Getting Ready…"
                       list="subfolderSuggestions" autocomplete="off"
                       value="">
                <?php if (!empty($existing_subfolders)): ?>
                <datalist id="subfolderSuggestions">
                    <?php foreach ($existing_subfolders as $sf): ?>
                    <option value="<?php echo htmlspecialchars($sf, ENT_QUOTES, 'UTF-8'); ?>">
                    <?php endforeach; ?>
                </datalist>
                <?php endif; ?>
                <div class="form-text">
                    <i class="fas fa-info-circle"></i> 
                    फोटोहरू एउटै एल्बममा राख्न एउटै नाम टाइप गर्नुहोस्। उदाहरण: "Ceremony" टाइप गरेर Ceremony एल्बममा फोटो थप्नुहोस्।
                </div>
            </div>
            
            <!-- Drag & Drop Zone -->
            <div id="dropZone" class="drop-zone">
                <div class="drop-zone-icon">
                    <i class="fas fa-cloud-upload-alt"></i>
                </div>
                <div class="drop-zone-text">
                    <strong>Drag & Drop files here</strong><br>
                    or click to browse
                </div>
                <div class="drop-zone-hint">
                    कुनै पनि फाइल: फोटो, भिडियो, ZIP, PDF, Word, Excel र अन्य सबै • Max size: 50GB
                </div>
            </div>
            
            <input type="file" class="form-control d-none" id="images" name="images[]" accept="*/*" multiple>
            
            <!-- Image Preview Container -->
            <div id="imagePreviewContainer" class="image-preview-container"></div>
            
            <div class="d-flex justify-content-end mt-3">
                <button type="submit" id="uploadButton" class="btn btn-success btn-lg" disabled>
                    <i class="fas fa-upload"></i> Upload Files
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Files Grid -->
<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-photo-video"></i> Files in this Folder (<?php echo count($photos); ?>)</h5>
        <?php if (count($photos) > 0): ?>
            <div class="form-check">
                <input type="checkbox" class="form-check-input" id="selectAllCheckbox">
                <label class="form-check-label" for="selectAllCheckbox">Select All</label>
            </div>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if (empty($photos)): ?>
            <div class="text-center py-5">
                <i class="fas fa-photo-video fa-4x text-muted mb-3"></i>
                <p class="text-muted">No files in this folder yet.</p>
                <p class="text-muted">Use the upload area above to add files.</p>
            </div>
        <?php else: ?>
            <?php foreach ($photos_by_subfolder as $sf_name => $sf_photos): ?>
                <?php if ($sf_name !== ''): ?>
                    <div class="d-flex align-items-center gap-2 mt-3 mb-2">
                        <i class="fas fa-folder-open text-warning"></i>
                        <strong><?php echo htmlspecialchars($sf_name); ?></strong>
                        <span class="badge bg-secondary"><?php echo count($sf_photos); ?></span>
                    </div>
                <?php elseif (count($photos_by_subfolder) > 1): ?>
                    <div class="d-flex align-items-center gap-2 mt-3 mb-2">
                        <i class="fas fa-folder text-muted"></i>
                        <strong class="text-muted">Uncategorized</strong>
                        <span class="badge bg-secondary"><?php echo count($sf_photos); ?></span>
                    </div>
                <?php endif; ?>
                <div class="photo-grid">
                    <?php foreach ($sf_photos as $photo): 
                        $file_url = UPLOAD_URL . $photo['image_path'];
                        $is_video = isset($photo['file_type']) && $photo['file_type'] === 'video';
                        $is_generic = isset($photo['file_type']) && $photo['file_type'] === 'file';
                        $ext = strtolower(pathinfo($photo['image_path'], PATHINFO_EXTENSION));
                        $icon_class = getFileTypeIcon($ext);
                    ?>
                        <div class="photo-item" data-photo-id="<?php echo $photo['id']; ?>">
                            <input type="checkbox" class="form-check-input photo-checkbox" value="<?php echo $photo['id']; ?>">
                            
                            <?php if ($is_video): ?>
                                <div class="video-thumbnail" onclick="adminOpenVideo('<?php echo htmlspecialchars($file_url, ENT_QUOTES, 'UTF-8'); ?>')" title="Click to play video">
                                    <div class="video-play-icon">
                                        <i class="fas fa-play-circle"></i>
                                    </div>
                                    <span class="badge bg-danger video-badge">VIDEO</span>
                                </div>
                            <?php elseif ($is_generic): ?>
                                <div class="video-thumbnail d-flex flex-column align-items-center justify-content-center" style="background:#f8f9fa;">
                                    <i class="fas <?php echo $icon_class; ?>" style="font-size:3rem;color:#888;"></i>
                                    <small class="mt-2 text-muted text-uppercase" style="font-size:0.7rem;"><?php echo htmlspecialchars($ext ?: 'FILE'); ?></small>
                                    <span class="badge bg-secondary video-badge">FILE</span>
                                </div>
                            <?php else: ?>
                                <img src="<?php echo htmlspecialchars($file_url); ?>" alt="<?php echo htmlspecialchars($photo['title']); ?>" loading="lazy"
                                     onclick="adminOpenImage('<?php echo htmlspecialchars($file_url, ENT_QUOTES, 'UTF-8'); ?>')"
                                     title="Click to preview"
                                     onerror="this.style.opacity='0.3';this.style.cursor='default';this.onclick=null;this.title='File not found on server';">
                            <?php endif; ?>
                            
                            <a href="?id=<?php echo $folder_id; ?>&delete_photo=<?php echo $photo['id']; ?>" 
                               class="delete-btn"
                               onclick="return confirm('Delete this file?');"
                               title="Delete <?php echo $is_video ? 'Video' : 'Photo'; ?>">
                                <i class="fas fa-times"></i>
                            </a>
                            
                            <div class="photo-overlay">
                                <small><?php echo htmlspecialchars(substr($photo['title'], 0, 20)); ?><?php echo strlen($photo['title']) > 20 ? '...' : ''; ?></small>
                                <br>
                                <small>
                                    <?php if ($is_video && isset($photo['file_size'])): ?>
                                        <i class="fas fa-file-video"></i> <?php echo formatFileSize($photo['file_size']); ?>
                                    <?php endif; ?>
                                    <i class="fas fa-download"></i> <?php echo $photo['download_count']; ?>
                                </small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Include Image Upload Handler JS -->
<script src="<?php echo BASE_URL; ?>/admin/js/image-upload-handler.js"></script>

<script>
(function () {
    // Initialize Enhanced Image Upload Handler for folder uploads
    var uploadHandler = new ImageUploadHandler({
        fileInput: '#images',
        dropZone: '#dropZone',
        previewContainer: '#imagePreviewContainer',
        uploadButton: '#uploadButton',
        form: '#uploadForm',
        maxWidth: 1920,
        maxHeight: 1920,
        quality: 0.90,
        skipCompression: true,          // Deliver original quality for shared folder files
        maxFileSize: 50 * 1024 * 1024 * 1024,   // 50 GB per file
        maxVideoSize: 50 * 1024 * 1024 * 1024,  // 50 GB per video
        allowAllFiles: true,                     // Allow any file type
        uploadUrl: 'ajax-upload.php',
        chunkUploadUrl: 'ajax-chunk-upload.php',
        onUploadStart: function() {
            console.log('Upload started');
        },
        onUploadProgress: function(percent) {
            console.log('Upload progress: ' + percent + '%');
        },
        onUploadComplete: function(result) {
            console.log('Upload complete:', result);
            if (result.uploadedCount > 0 && result.errorCount === 0) {
                setTimeout(function() {
                    window.location.href = 'view.php?id=' + <?php echo $folder_id; ?> + '&success=' + result.uploadedCount;
                }, 1500);
            }
        },
        onUploadError: function(error) {
            console.error('Upload error:', error);
        }
    });
})();

$(document).ready(function() {
    // Copy link functionality
    $('.copy-link-btn').on('click', function() {
        var url = $(this).data('url');
        var $btn = $(this);
        
        navigator.clipboard.writeText(url).then(function() {
            var originalHtml = $btn.html();
            $btn.html('<i class="fas fa-check"></i> Copied!');
            $btn.removeClass('btn-primary').addClass('btn-success');
            
            setTimeout(function() {
                $btn.html(originalHtml);
                $btn.removeClass('btn-success').addClass('btn-primary');
            }, 2000);
        });
    });
    
    // Update selected count
    function updateSelectedCount() {
        var count = $('.photo-checkbox:checked').length;
        $('#selectedCount').text(count);
        if (count > 0) {
            $('#bulkDeleteBtn').show();
        } else {
            $('#bulkDeleteBtn').hide();
        }
    }
    
    // Handle checkbox changes
    $(document).on('change', '.photo-checkbox', function() {
        updateSelectedCount();
        var allChecked = $('.photo-checkbox').length === $('.photo-checkbox:checked').length;
        $('#selectAllCheckbox').prop('checked', allChecked);
    });
    
    // Handle Select All
    $('#selectAllCheckbox').on('change', function() {
        var isChecked = $(this).prop('checked');
        $('.photo-checkbox').prop('checked', isChecked);
        updateSelectedCount();
    });
    
    // Handle bulk delete
    $('#bulkDeleteBtn').on('click', function() {
        var selectedIds = [];
        $('.photo-checkbox:checked').each(function() {
            selectedIds.push($(this).val());
        });
        
        if (selectedIds.length === 0) return;
        
        if (!confirm('Delete ' + selectedIds.length + ' selected file(s)?')) return;
        
        var $btn = $(this);
        $btn.prop('disabled', true).find('i').removeClass('fa-trash').addClass('fa-spinner fa-spin');
        
        $.ajax({
            url: 'ajax-bulk-delete.php',
            method: 'POST',
            data: {
                csrf_token: $('#csrf_token').val(),
                folder_id: $('#folder_id').val(),
                photo_ids: selectedIds.join(',')
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    response.deleted_ids.forEach(function(id) {
                        $('.photo-item[data-photo-id="' + id + '"]').fadeOut(300, function() {
                            $(this).remove();
                        });
                    });
                    $('#selectAllCheckbox').prop('checked', false);
                    updateSelectedCount();
                    showAlert('success', response.message);
                } else {
                    showAlert('danger', response.message || 'Failed to delete photos.');
                }
            },
            error: function() {
                showAlert('danger', 'An error occurred. Please try again.');
            },
            complete: function() {
                $btn.prop('disabled', false).find('i').removeClass('fa-spinner fa-spin').addClass('fa-trash');
            }
        });
    });
    
    function showAlert(type, message) {
        var $alert = $('<div class="alert alert-' + type + ' alert-dismissible fade show">' +
            '<i class="fas fa-' + (type === 'success' ? 'check' : 'exclamation') + '-circle"></i> ' + message +
            '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>' +
            '</div>');
        $('.card').first().before($alert);
        setTimeout(function() { $alert.alert('close'); }, 5000);
    }
});
</script>

<!-- Admin Image Lightbox -->
<div class="admin-lightbox" id="adminImageLightbox" onclick="adminCloseLightbox()">
    <span class="admin-lightbox-close" onclick="adminCloseLightbox()">&times;</span>
    <img src="" alt="Preview" id="adminLightboxImage" onclick="event.stopPropagation()">
</div>

<!-- Admin Video Lightbox -->
<div class="admin-lightbox" id="adminVideoLightbox" onclick="adminCloseVideo()">
    <span class="admin-lightbox-close" onclick="adminCloseVideo()">&times;</span>
    <video id="adminLightboxVideo" controls onclick="event.stopPropagation()">
        <source src="" id="adminLightboxVideoSrc" type="video/mp4">
    </video>
</div>

<script>
function adminOpenImage(src) {
    document.getElementById('adminLightboxImage').src = src;
    document.getElementById('adminImageLightbox').classList.add('active');
}
function adminCloseLightbox() {
    document.getElementById('adminLightboxImage').src = '';
    document.getElementById('adminImageLightbox').classList.remove('active');
}
function adminOpenVideo(src) {
    var video = document.getElementById('adminLightboxVideo');
    var sourceEl = document.getElementById('adminLightboxVideoSrc');
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
    document.getElementById('adminVideoLightbox').classList.add('active');
}
function adminCloseVideo() {
    var video = document.getElementById('adminLightboxVideo');
    video.pause();
    video.currentTime = 0;
    document.getElementById('adminVideoLightbox').classList.remove('active');
}
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        adminCloseLightbox();
        adminCloseVideo();
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
