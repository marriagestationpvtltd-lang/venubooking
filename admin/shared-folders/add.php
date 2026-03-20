<?php
/**
 * Create New Shared Folder
 * Admin can create folders to organize photos for sharing
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();
$current_user = getCurrentUser();
$db = getDB();

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $folder_name = trim($_POST['folder_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $expires_in = intval($_POST['expires_in'] ?? 0);
    $max_downloads = !empty($_POST['max_downloads']) ? intval($_POST['max_downloads']) : null;
    $allow_zip_download = isset($_POST['allow_zip_download']) ? 1 : 0;
    $show_preview = isset($_POST['show_preview']) ? 1 : 0;
    
    if (empty($folder_name)) {
        $error_message = 'Please enter a folder name.';
    } else {
        // Generate unique download token (64 characters, URL-safe)
        $download_token = bin2hex(random_bytes(32));
        
        // Calculate expiration date if set
        $expires_at = null;
        if ($expires_in > 0) {
            $expires_at = date('Y-m-d H:i:s', strtotime("+{$expires_in} days"));
        }
        
        try {
            $sql = "INSERT INTO shared_folders (folder_name, description, download_token, max_downloads, expires_at, allow_zip_download, show_preview, status, created_by) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'active', ?)";
            $stmt = $db->prepare($sql);
            $result = $stmt->execute([
                $folder_name, 
                $description, 
                $download_token, 
                $max_downloads, 
                $expires_at, 
                $allow_zip_download,
                $show_preview,
                $current_user['id']
            ]);
            
            if ($result) {
                $folder_id = $db->lastInsertId();
                logActivity($current_user['id'], 'Created shared folder', 'shared_folders', $folder_id, "Created folder: $folder_name");
                
                // Redirect to folder view for uploading photos
                header('Location: view.php?id=' . $folder_id . '&success=created');
                exit;
            } else {
                $error_message = 'Failed to create folder. Please try again.';
            }
        } catch (Exception $e) {
            error_log('Shared folder creation error: ' . $e->getMessage());
            $error_message = 'Database error occurred. Please try again.';
        }
    }
}

$page_title = 'Create New Folder';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-folder-plus"></i> Create New Photo Folder</h5>
                <a href="index.php" class="btn btn-secondary btn-sm">
                    <i class="fas fa-arrow-left"></i> Back to Folders
                </a>
            </div>
            <div class="card-body">
                <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Features Info -->
                <div class="alert alert-success mb-4">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-folder-open fa-2x me-3"></i>
                        <div>
                            <strong>Google Drive जस्तै फोटो सेयर:</strong>
                            <ul class="mb-0 mt-1">
                                <li><i class="fas fa-folder-plus"></i> <strong>फोल्डर बनाउनुहोस्:</strong> फोटोहरू राख्न फोल्डर बनाउनुहोस्</li>
                                <li><i class="fas fa-upload"></i> <strong>अपलोड:</strong> धेरै फोटो एकैपटक अपलोड गर्नुहोस् (५०० हजार+)</li>
                                <li><i class="fas fa-link"></i> <strong>लिङ्क:</strong> स्वचालित सेयर लिङ्क बन्छ</li>
                                <li><i class="fas fa-download"></i> <strong>डाउनलोड:</strong> युजरले एक-एक वा सबै ZIP मा डाउनलोड गर्न सक्छन्</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="folder_name" class="form-label">Folder Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="folder_name" name="folder_name" 
                               value="<?php echo htmlspecialchars($_POST['folder_name'] ?? ''); ?>" required
                               placeholder="e.g., विवाह फोटो - राम र सीता - २०८२">
                        <small class="text-muted">युजरले फोल्डर पहिचान गर्न सजिलो हुने नाम दिनुहोस्</small>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Description (Optional)</label>
                        <textarea class="form-control" id="description" name="description" rows="2" 
                                  placeholder="Optional description about this folder's contents"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="expires_in" class="form-label">Link Expiry (Optional)</label>
                                <select class="form-select" id="expires_in" name="expires_in">
                                    <option value="">Never Expires</option>
                                    <option value="1">1 Day</option>
                                    <option value="3">3 Days</option>
                                    <option value="7">7 Days</option>
                                    <option value="14">14 Days</option>
                                    <option value="30">30 Days</option>
                                    <option value="90">90 Days</option>
                                    <option value="365">1 Year</option>
                                </select>
                                <small class="text-muted">फोल्डर लिङ्क कति दिनपछि expire हुने?</small>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="max_downloads" class="form-label">Max Downloads per Photo (Optional)</label>
                                <input type="number" class="form-control" id="max_downloads" name="max_downloads" 
                                       value="<?php echo htmlspecialchars($_POST['max_downloads'] ?? ''); ?>" min="1" placeholder="Unlimited">
                                <small class="text-muted">प्रति फोटो कति पटक डाउनलोड?</small>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">ZIP Download</label>
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" id="allow_zip_download" name="allow_zip_download" checked>
                                    <label class="form-check-label" for="allow_zip_download">
                                        Allow download all as ZIP
                                    </label>
                                </div>
                                <small class="text-muted">सबै फोटो एकैपटक ZIP मा डाउनलोड</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Photo Preview</label>
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" id="show_preview" name="show_preview" checked>
                                    <label class="form-check-label" for="show_preview">
                                        <i class="fas fa-eye"></i> फोटो प्रिभियु देखाउनुहोस्
                                    </label>
                                </div>
                                <small class="text-muted">बन्द गरेमा युजरलाई सिधै ZIP डाउनलोड मात्र देखिन्छ, फोटो प्रिभियु देखिँदैन</small>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between mt-4">
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-success btn-lg">
                            <i class="fas fa-folder-plus"></i> Create Folder & Upload Photos
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
