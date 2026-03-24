<?php
/**
 * Shared Folders Management - List all shared folders
 * Similar to Google Drive - folders containing photos with shareable links
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();
$current_user = getCurrentUser();
$db = getDB();

// Handle delete request
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = intval($_GET['delete']);
    
    // Get folder info before deletion
    $stmt = $db->prepare("SELECT folder_name FROM shared_folders WHERE id = ?");
    $stmt->execute([$id]);
    $folder = $stmt->fetch();
    
    if ($folder) {
        // Get all photos in this folder to delete their files
        $photos_stmt = $db->prepare("SELECT image_path, thumbnail_path FROM shared_photos WHERE folder_id = ?");
        $photos_stmt->execute([$id]);
        $photos = $photos_stmt->fetchAll();
        
        // Delete folder (cascade will delete photos from DB)
        $delete_stmt = $db->prepare("DELETE FROM shared_folders WHERE id = ?");
        if ($delete_stmt->execute([$id])) {
            // Delete physical files and thumbnails
            $real_upload_path = realpath(UPLOAD_PATH);
            foreach ($photos as $photo) {
                $file_path = UPLOAD_PATH . $photo['image_path'];
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
            }
            
            logActivity($current_user['id'], 'Deleted shared folder', 'shared_folders', $id, "Deleted folder: " . $folder['folder_name'] . " with " . count($photos) . " photos");
            $_SESSION['success_message'] = 'Folder and all photos deleted successfully!';
        } else {
            $_SESSION['error_message'] = 'Failed to delete folder.';
        }
    }
    
    header('Location: index.php');
    exit;
}

$page_title = 'फोटो सेयर व्यवस्थापन (Photo Share)';
require_once __DIR__ . '/../includes/header.php';

$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : '';

// Check for URL-based success message
if (isset($_GET['success'])) {
    $success_message = $_GET['success'] === 'created' ? 'Folder created successfully!' : 
                      ($_GET['success'] === 'updated' ? 'Folder updated successfully!' : $success_message);
}

// Clear session messages
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);

// Fetch all shared folders with photo counts
$stmt = $db->query("SELECT sf.*, u.full_name as created_by_name,
                    (SELECT COUNT(*) FROM shared_photos sp WHERE sp.folder_id = sf.id) as actual_photo_count
                    FROM shared_folders sf 
                    LEFT JOIN users u ON sf.created_by = u.id 
                    ORDER BY sf.created_at DESC");
$folders = $stmt->fetchAll();

// Generate base folder URL
$folder_base_url = BASE_URL . '/folder.php?token=';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4><i class="fas fa-share-alt"></i> फोटो सेयर व्यवस्थापन</h4>
    <a href="add.php" class="btn btn-success">
        <i class="fas fa-plus"></i> Create New Folder
    </a>
</div>

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

<div class="alert alert-info">
    <i class="fas fa-info-circle"></i> <strong>Google Drive जस्तै फोटो सेयरिङ:</strong>
    <ul class="mb-0 mt-2">
        <li><strong>फोल्डर बनाउनुहोस्:</strong> "Create New Folder" क्लिक गरेर नयाँ फोल्डर बनाउनुहोस्</li>
        <li><strong>फोटो अपलोड:</strong> फोल्डर खोलेर धेरै फोटो एकैपटक अपलोड गर्नुहोस् (५०० हजार+ सम्म)</li>
        <li><strong>लिङ्क सेयर:</strong> फोल्डरको लिङ्क कपी गरेर कसैलाई पनि पठाउनुहोस्</li>
        <li><strong>डाउनलोड:</strong> युजरले लिङ्कबाट सबै फोटो हेर्न र डाउनलोड गर्न सक्छन्</li>
    </ul>
</div>

<div class="card">
    <div class="card-body">
        <?php if (empty($folders)): ?>
            <div class="text-center py-5">
                <i class="fas fa-folder-open fa-4x text-muted mb-3"></i>
                <p class="text-muted">No folders created yet.</p>
                <a href="add.php" class="btn btn-success">
                    <i class="fas fa-plus"></i> Create Your First Folder
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover" id="foldersTable">
                    <thead>
                        <tr>
                            <th>Folder Name</th>
                            <th>Photos</th>
                            <th>Shareable Link</th>
                            <th>Downloads</th>
                            <th>Status</th>
                            <th>Created By</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($folders as $folder): 
                            $folder_url = $folder_base_url . urlencode($folder['download_token']);
                            $is_expired = ($folder['expires_at'] && strtotime($folder['expires_at']) < time()) || $folder['status'] === 'expired';
                        ?>
                            <tr>
                                <td>
                                    <a href="view.php?id=<?php echo $folder['id']; ?>" class="text-decoration-none">
                                        <i class="fas fa-folder text-warning me-1"></i>
                                        <strong><?php echo htmlspecialchars($folder['folder_name']); ?></strong>
                                    </a>
                                    <?php if ($folder['description']): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars(substr($folder['description'], 0, 50)); ?><?php echo strlen($folder['description']) > 50 ? '...' : ''; ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-primary"><?php echo $folder['actual_photo_count']; ?></span>
                                </td>
                                <td style="max-width: 200px;">
                                    <div class="input-group input-group-sm">
                                        <input type="text" class="form-control form-control-sm folder-link" 
                                               value="<?php echo htmlspecialchars($folder_url); ?>" readonly
                                               style="font-size: 11px;">
                                        <button class="btn btn-outline-primary copy-link-btn" type="button" 
                                                data-url="<?php echo htmlspecialchars($folder_url); ?>"
                                                title="Copy Link">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?php echo $folder['total_downloads']; ?></span>
                                </td>
                                <td>
                                    <?php if ($is_expired): ?>
                                        <span class="badge bg-secondary">Expired</span>
                                    <?php elseif ($folder['status'] === 'active'): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary"><?php echo ucfirst($folder['status']); ?></span>
                                    <?php endif; ?>
                                    
                                    <?php if ($folder['expires_at']): ?>
                                        <br><small class="text-muted">
                                            Expires: <?php echo date('M d, Y', strtotime($folder['expires_at'])); ?> (<?php echo convertToNepaliDate($folder['expires_at']); ?>)
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($folder['created_by_name'] ?? ($folder['transfer_source'] === 'public' ? 'Public Transfer' : 'Unknown')); ?>
                                    <?php if (($folder['transfer_source'] ?? 'admin') === 'public'): ?>
                                        <br><span class="badge bg-purple" style="background:#7c3aed!important;font-size:.7rem;">Public Transfer</span>
                                        <?php if (!empty($folder['sender_email'])): ?>
                                            <br><small class="text-muted"><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($folder['sender_email']); ?></small>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('M d, Y H:i', strtotime($folder['created_at'])); ?><br><small class="text-muted"><?php echo convertToNepaliDate($folder['created_at']); ?></small></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="view.php?id=<?php echo $folder['id']; ?>" class="btn btn-outline-primary" title="View & Upload Photos">
                                            <i class="fas fa-images"></i>
                                        </a>
                                        <a href="<?php echo $folder_url; ?>" class="btn btn-outline-info" title="Preview" target="_blank">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="edit.php?id=<?php echo $folder['id']; ?>" class="btn btn-outline-warning" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="?delete=<?php echo $folder['id']; ?>" class="btn btn-outline-danger" 
                                           onclick="return confirm('के तपाईं यो फोल्डर र यसका सबै फोटोहरू स्थायी रूपमा हटाउन चाहनुहुन्छ?\n\nयो कार्य उल्टाउन सकिँदैन।');" 
                                           title="Delete Folder">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php 
$extra_js = <<<'JS'
<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#foldersTable').DataTable({
        "order": [[6, "desc"]],
        "pageLength": 25,
        "columnDefs": [
            { "orderable": false, "searchable": false, "targets": [2, 7] }
        ]
    });
    
    // Copy link functionality
    $(document).on('click', '.copy-link-btn', function() {
        var url = $(this).data('url');
        var $btn = $(this);
        
        navigator.clipboard.writeText(url).then(function() {
            var originalHtml = $btn.html();
            $btn.html('<i class="fas fa-check"></i>');
            $btn.removeClass('btn-outline-primary').addClass('btn-success');
            
            setTimeout(function() {
                $btn.html(originalHtml);
                $btn.removeClass('btn-success').addClass('btn-outline-primary');
            }, 2000);
        }).catch(function() {
            var $input = $btn.closest('.input-group').find('input');
            $input.select();
            document.execCommand('copy');
            
            var originalHtml = $btn.html();
            $btn.html('<i class="fas fa-check"></i>');
            $btn.removeClass('btn-outline-primary').addClass('btn-success');
            
            setTimeout(function() {
                $btn.html(originalHtml);
                $btn.removeClass('btn-success').addClass('btn-outline-primary');
            }, 2000);
        });
    });
});
</script>
JS;

require_once __DIR__ . '/../includes/footer.php'; 
?>
