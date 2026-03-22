<?php
/**
 * Edit Shared Folder
 * Admin can edit folder details and settings
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
$stmt = $db->prepare("SELECT * FROM shared_folders WHERE id = ?");
$stmt->execute([$folder_id]);
$folder = $stmt->fetch();

if (!$folder) {
    $_SESSION['error_message'] = 'Folder not found.';
    header('Location: index.php');
    exit;
}

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $folder_name = trim($_POST['folder_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $expires_in = $_POST['expires_in'] ?? '';
    $max_downloads = !empty($_POST['max_downloads']) ? intval($_POST['max_downloads']) : null;
    $allow_zip_download = isset($_POST['allow_zip_download']) ? 1 : 0;
    $show_preview = isset($_POST['show_preview']) ? 1 : 0;
    $status = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';
    
    if (empty($folder_name)) {
        $error_message = 'Please enter a folder name.';
    } else {
        // Calculate expiration date
        $expires_at = $folder['expires_at']; // Keep existing by default
        if ($expires_in === 'clear') {
            $expires_at = null;
        } elseif (is_numeric($expires_in) && intval($expires_in) > 0) {
            $expires_at = date('Y-m-d H:i:s', strtotime("+" . intval($expires_in) . " days"));
        }
        
        try {
            $sql = "UPDATE shared_folders SET 
                    folder_name = ?, 
                    description = ?, 
                    max_downloads = ?, 
                    expires_at = ?, 
                    allow_zip_download = ?,
                    show_preview = ?,
                    status = ?
                    WHERE id = ?";
            $stmt = $db->prepare($sql);
            $result = $stmt->execute([
                $folder_name, 
                $description, 
                $max_downloads, 
                $expires_at, 
                $allow_zip_download,
                $show_preview,
                $status,
                $folder_id
            ]);
            
            if ($result) {
                logActivity($current_user['id'], 'Updated shared folder', 'shared_folders', $folder_id, "Updated folder: $folder_name");
                header('Location: index.php?success=updated');
                exit;
            } else {
                $error_message = 'Failed to update folder. Please try again.';
            }
        } catch (Exception $e) {
            error_log('Shared folder update error: ' . $e->getMessage());
            $error_message = 'Database error occurred. Please try again.';
        }
    }
}

$page_title = 'Edit Folder: ' . $folder['folder_name'];
require_once __DIR__ . '/../includes/header.php';

// Generate folder URL
$folder_url = BASE_URL . '/folder.php?token=' . urlencode($folder['download_token']);
?>

<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-edit"></i> Edit Folder</h5>
                <div>
                    <a href="view.php?id=<?php echo $folder_id; ?>" class="btn btn-primary btn-sm">
                        <i class="fas fa-images"></i> View Photos
                    </a>
                    <a href="index.php" class="btn btn-secondary btn-sm">
                        <i class="fas fa-arrow-left"></i> Back to Folders
                    </a>
                </div>
            </div>
            <div class="card-body">
                <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Current Shareable Link -->
                <div class="alert alert-success mb-4">
                    <strong><i class="fas fa-link"></i> Shareable Link:</strong>
                    <div class="input-group mt-2">
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($folder_url); ?>" readonly>
                        <button class="btn btn-primary copy-link-btn" type="button" data-url="<?php echo htmlspecialchars($folder_url); ?>">
                            <i class="fas fa-copy"></i> Copy
                        </button>
                    </div>
                </div>

                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="folder_name" class="form-label">Folder Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="folder_name" name="folder_name" 
                               value="<?php echo htmlspecialchars($folder['folder_name']); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Description (Optional)</label>
                        <textarea class="form-control" id="description" name="description" rows="2"><?php echo htmlspecialchars($folder['description']); ?></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="active" <?php echo $folder['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $folder['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="expires_in" class="form-label">Link Expiry</label>
                                <select class="form-select" id="expires_in" name="expires_in">
                                    <option value="">Keep Current</option>
                                    <option value="clear">Never Expires</option>
                                    <option value="1">Extend 1 Day</option>
                                    <option value="7">Extend 7 Days</option>
                                    <option value="30">Extend 30 Days</option>
                                    <option value="90">Extend 90 Days</option>
                                    <option value="365">Extend 1 Year</option>
                                </select>
                                <?php if ($folder['expires_at']): ?>
                                    <small class="text-muted">Current: <?php echo date('M d, Y H:i', strtotime($folder['expires_at'])); ?> (<?php echo convertToNepaliDate($folder['expires_at']); ?>)</small>
                                <?php else: ?>
                                    <small class="text-muted">Current: Never expires</small>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="max_downloads" class="form-label">Max Downloads per Photo</label>
                                <input type="number" class="form-control" id="max_downloads" name="max_downloads" 
                                       value="<?php echo htmlspecialchars($folder['max_downloads'] ?? ''); ?>" min="1" placeholder="Unlimited">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="allow_zip_download" name="allow_zip_download" 
                                   <?php echo $folder['allow_zip_download'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="allow_zip_download">
                                <i class="fas fa-file-archive"></i> Allow "Download All as ZIP" (सबै फोटो एकैपटक डाउनलोड)
                            </label>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="show_preview" name="show_preview" 
                                   <?php echo (!isset($folder['show_preview']) || $folder['show_preview']) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="show_preview">
                                <i class="fas fa-eye"></i> फोटो प्रिभियु देखाउनुहोस्
                            </label>
                        </div>
                        <small class="text-muted ms-4">बन्द गरेमा युजरलाई सिधै ZIP डाउनलोड मात्र देखिन्छ, फोटो प्रिभियु देखिँदैन</small>
                    </div>

                    <div class="d-flex justify-content-between mt-4">
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
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
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
