<?php
/**
 * Shared Photo Upload Page
 * Admin can upload photos for sharing with users
 */

$page_title = 'Upload Photo for Sharing';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();
$success_message = '';
$error_message = '';
?>

<!-- Include Image Upload Handler CSS -->
<link rel="stylesheet" href="<?php echo htmlspecialchars(BASE_URL); ?>/admin/css/image-upload-handler.css">

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-upload"></i> Upload Photo for Sharing</h5>
                <a href="index.php" class="btn btn-secondary btn-sm">
                    <i class="fas fa-arrow-left"></i> Back to List
                </a>
            </div>
            <div class="card-body">
                <!-- Features Info -->
                <div class="alert alert-success mb-4">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-share-alt fa-2x me-3"></i>
                        <div>
                            <strong>फाइल सेयर गर्ने तरिका:</strong>
                            <ul class="mb-0 mt-1">
                                <li><i class="fas fa-upload"></i> <strong>अपलोड:</strong> फोटो, भिडियो, ZIP, PDF वा कुनै पनि फाइल अपलोड गर्नुहोस्</li>
                                <li><i class="fas fa-link"></i> <strong>लिङ्क:</strong> स्वचालित रूपमा डाउनलोड लिङ्क जेनेरेट हुनेछ</li>
                                <li><i class="fas fa-share"></i> <strong>सेयर:</strong> उक्त लिङ्क युजरलाई दिनुहोस्</li>
                                <li><i class="fas fa-download"></i> <strong>डाउनलोड:</strong> युजरले लिङ्कबाट फाइल डाउनलोड गर्न सक्छन्</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <form id="uploadForm" method="POST" action="ajax-upload.php" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="title" class="form-label">File Title <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="title" name="title" 
                                       value="" required
                                       placeholder="e.g., विवाह फोटो - राम र सीता, Contract.pdf">
                                <small class="text-muted">युजरलाई फाइल पहिचान गर्न सजिलो हुने नाम दिनुहोस्</small>
                            </div>
                        </div>

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
                                </select>
                                <small class="text-muted">लिङ्क कति दिनपछि expire हुने?</small>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Description (Optional)</label>
                        <textarea class="form-control" id="description" name="description" rows="2" 
                                  placeholder="Optional description about the photo"></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="max_downloads" class="form-label">Maximum Downloads (Optional)</label>
                                <input type="number" class="form-control" id="max_downloads" name="max_downloads" 
                                       value="" min="1" placeholder="Unlimited">
                                <small class="text-muted">कति पटक डाउनलोड गर्न मिल्ने? खाली छाड्दा unlimited</small>
                            </div>
                        </div>
                    </div>

                    <!-- Drag & Drop Zone -->
                    <div class="mb-3">
                        <label class="form-label">File <span class="text-danger">*</span></label>
                        
                        <div id="dropZone" class="drop-zone">
                            <div class="drop-zone-icon">
                                <i class="fas fa-cloud-upload-alt"></i>
                            </div>
                            <div class="drop-zone-text">
                                <strong>Drag & Drop file here</strong><br>
                                or click to browse
                            </div>
                            <div class="drop-zone-hint">
                                कुनै पनि फाइल: फोटो, भिडियो, ZIP, PDF, Word, Excel र अन्य सबै • Max size: 50GB
                            </div>
                        </div>
                        
                        <input type="file" class="form-control d-none" id="images" name="images[]" accept="*/*" multiple>
                    </div>

                    <!-- Image Preview Container -->
                    <div id="imagePreviewContainer" class="image-preview-container"></div>

                    <div class="d-flex justify-content-between mt-4">
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                        <button type="submit" id="uploadButton" class="btn btn-success btn-lg" disabled>
                            <i class="fas fa-upload"></i> Upload & Generate Link
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Include Image Upload Handler JS -->
<script src="<?php echo BASE_URL; ?>/admin/js/image-upload-handler.js"></script>

<script>
(function () {
    // Initialize Enhanced Image Upload Handler
    var uploadHandler = new ImageUploadHandler({
        fileInput: '#images',
        dropZone: '#dropZone',
        previewContainer: '#imagePreviewContainer',
        uploadButton: '#uploadButton',
        form: '#uploadForm',
        maxWidth: 1920,
        maxHeight: 1920,
        skipCompression: true, // Deliver original quality for shared files
        maxFileSize: 50 * 1024 * 1024 * 1024, // 50GB
        allowAllFiles: true, // Allow any file type
        uploadUrl: 'ajax-upload.php',
        onUploadStart: function() {
            console.log('Upload started');
        },
        onUploadProgress: function(percent) {
            console.log('Upload progress: ' + percent + '%');
        },
        onUploadComplete: function(result) {
            console.log('Upload complete:', result);
            if (result.uploadedCount > 0 && result.errorCount === 0) {
                // Redirect to photo list after successful upload
                setTimeout(function() {
                    window.location.href = 'index.php?success=' + result.uploadedCount;
                }, 1500);
            }
        },
        onUploadError: function(error) {
            console.error('Upload error:', error);
        }
    });
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
