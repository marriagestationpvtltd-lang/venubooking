<?php
/**
 * Enhanced Image Upload Page
 * Features:
 * - Multiple file upload with drag & drop
 * - Client-side image compression (reduces file size with minimal visible quality loss)
 * - Real-time preview before upload
 * - Progress indicator during upload
 * - AJAX-based upload for better UX
 */

$page_title = 'Upload New Image';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();
$success_message = '';
$error_message = '';

// Define available sections
$sections = [
    'banner' => 'Banner / Hero Section',
    'venue' => 'Venue Gallery',
    'hall' => 'Hall Gallery',
    'package' => 'Package/Menu Images',
    'gallery' => 'General Gallery',
    'work_photos' => 'Our Work (Folder Gallery)',
    'testimonial' => 'Testimonials',
    'feature' => 'Features Section',
    'about' => 'About Us Section',
    'other' => 'Other'
];

// Predefined event categories for the work_photos folder gallery
$event_categories = [
    'विवाह फोटो (Wedding Photos)',
    'व्रतबन्ध फोटो (Bratabandha Photos)',
    'Engagement Photos',
    'Reception Photos',
    'Birthday Party Photos',
    'Corporate Event Photos',
    'Other Events',
];

// Get current card info for selected section (for display purposes)
$section_card_info = [];
foreach ($sections as $key => $label) {
    $stmt = $db->prepare("SELECT COALESCE(MAX(card_id), 0) as max_card, COUNT(*) as total_photos FROM site_images WHERE section = ?");
    $stmt->execute([$key]);
    $section_card_info[$key] = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<!-- Include Image Upload Handler CSS -->
<link rel="stylesheet" href="<?php echo htmlspecialchars(BASE_URL); ?>/admin/css/image-upload-handler.css">

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-upload"></i> Upload New Images</h5>
                <a href="index.php" class="btn btn-secondary btn-sm">
                    <i class="fas fa-arrow-left"></i> Back to List
                </a>
            </div>
            <div class="card-body">
                <!-- Features Info -->
                <div class="alert alert-success mb-4">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-magic fa-2x me-3"></i>
                        <div>
                            <strong>Enhanced Upload Features:</strong>
                            <ul class="mb-0 mt-1">
                                <li><i class="fas fa-compress-alt"></i> <strong>Auto Compression:</strong> Images are automatically compressed with minimal visible quality loss</li>
                                <li><i class="fas fa-images"></i> <strong>Multiple Upload:</strong> Select or drag multiple photos at once</li>
                                <li><i class="fas fa-eye"></i> <strong>Preview:</strong> See thumbnails before uploading</li>
                                <li><i class="fas fa-spinner"></i> <strong>Progress:</strong> Real-time upload progress for each file</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <form id="uploadForm" method="POST" action="ajax-upload.php" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="title" class="form-label">Image Title</label>
                                <input type="text" class="form-control" id="title" name="title" 
                                       value="" 
                                       placeholder="e.g., Grand Ballroom Banner">
                                <small class="text-muted">Optional — filename is used as title if left blank. Same title will be applied to all uploaded images.</small>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="section" class="form-label">Section <span class="text-danger">*</span></label>
                                <select class="form-select" id="section" name="section" required>
                                    <option value="">Select Section</option>
                                    <?php foreach ($sections as $key => $label): ?>
                                        <option value="<?php echo $key; ?>" data-photos="<?php echo $section_card_info[$key]['total_photos']; ?>" data-cards="<?php echo $section_card_info[$key]['max_card']; ?>">
                                            <?php echo htmlspecialchars($label); ?> (<?php echo $section_card_info[$key]['total_photos']; ?> photos)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">Where should these images appear?</small>
                            </div>
                        </div>
                    </div>

                    <!-- Section Info Display -->
                    <div id="sectionInfo" class="photos-per-card-info" style="display: none;">
                        <i class="fas fa-folder-open"></i>
                        <span>Photos are grouped into cards (max 100 per card). Current section has <span id="currentPhotos" class="count">0</span> photos in <span id="currentCards" class="count">0</span> card(s).</span>
                    </div>

                    <!-- Event Category field – only shown when section = work_photos -->
                    <div class="mb-3" id="eventCategoryField" style="display:none;">
                        <label for="event_category" class="form-label">
                            Event Category (Folder) <span class="text-danger">*</span>
                        </label>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <select class="form-select" id="event_category_select" name="_event_category_select">
                                    <option value="">— Choose a category —</option>
                                    <?php foreach ($event_categories as $cat): ?>
                                        <option value="<?php echo htmlspecialchars($cat); ?>">
                                            <?php echo htmlspecialchars($cat); ?>
                                        </option>
                                    <?php endforeach; ?>
                                    <option value="__custom__">— Custom / other category —</option>
                                </select>
                            </div>
                            <div class="col-md-6" id="customCategoryWrap" style="display:none;">
                                <input type="text" class="form-control" id="event_category_custom"
                                       placeholder="Enter custom category name">
                            </div>
                        </div>
                        <input type="hidden" id="event_category" name="event_category" value="">
                        <small class="text-muted">Photos in the same category are displayed together in one folder card (like menu → sub-menu structure).</small>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="2" 
                                  placeholder="Optional description or alt text for the images"></textarea>
                        <small class="text-muted">Optional: This description will be applied to all uploaded images</small>
                    </div>

                    <div class="row">
                        <div class="col-md-2">
                            <div class="mb-3">
                                <label for="display_order" class="form-label">Display Order</label>
                                <input type="number" class="form-control" id="display_order" name="display_order" 
                                       value="0" min="0" placeholder="0">
                                <small class="text-muted">Lower = first</small>
                            </div>
                        </div>

                        <div class="col-md-2">
                            <div class="mb-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="active" selected>Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Drag & Drop Zone -->
                    <div class="mb-3">
                        <label class="form-label">Image Files <span class="text-danger">*</span></label>
                        
                        <div id="dropZone" class="drop-zone">
                            <div class="drop-zone-icon">
                                <i class="fas fa-cloud-upload-alt"></i>
                            </div>
                            <div class="drop-zone-text">
                                <strong>Drag & Drop images here</strong><br>
                                or click to browse
                            </div>
                            <div class="drop-zone-hint">
                                Supported: JPG, PNG, GIF, WebP • Images auto-compressed for optimal loading
                            </div>
                        </div>
                        
                        <input type="file" class="form-control d-none" id="images" name="images[]" accept="image/jpeg,image/png,image/gif,image/webp" multiple>
                    </div>

                    <!-- Image Preview Container -->
                    <div id="imagePreviewContainer" class="image-preview-container"></div>

                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> <strong>How it works:</strong>
                        <ul class="mb-0 mt-2">
                            <li><strong>Select Section First:</strong> Choose where the images should appear</li>
                            <li><strong>Add Images:</strong> Drag & drop or click to select multiple images</li>
                            <li><strong>Auto-Compression:</strong> Large images (>1920px) are automatically resized and compressed without visible quality loss</li>
                            <li><strong>Card Grouping:</strong> Images are automatically grouped into cards (max 100 per card) within each section, similar to menu/sub-menu structure</li>
                            <li><strong>Preview & Remove:</strong> You can preview and remove images before uploading</li>
                        </ul>
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                        <button type="submit" id="uploadButton" class="btn btn-success btn-lg" disabled>
                            <i class="fas fa-upload"></i> Upload Image(s)
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
    var sectionSel  = document.getElementById('section');
    var catField    = document.getElementById('eventCategoryField');
    var catSelect   = document.getElementById('event_category_select');
    var customWrap  = document.getElementById('customCategoryWrap');
    var customInput = document.getElementById('event_category_custom');
    var hiddenInput = document.getElementById('event_category');
    var sectionInfo = document.getElementById('sectionInfo');
    var currentPhotos = document.getElementById('currentPhotos');
    var currentCards = document.getElementById('currentCards');

    function toggleCategoryField() {
        if (sectionSel.value === 'work_photos') {
            catField.style.display = '';
        } else {
            catField.style.display = 'none';
            hiddenInput.value = '';
        }
        
        // Show section info
        var selectedOption = sectionSel.options[sectionSel.selectedIndex];
        if (sectionSel.value && selectedOption) {
            var photos = selectedOption.getAttribute('data-photos') || '0';
            var cards = selectedOption.getAttribute('data-cards') || '0';
            currentPhotos.textContent = photos;
            currentCards.textContent = cards === '0' ? '0' : cards;
            sectionInfo.style.display = '';
        } else {
            sectionInfo.style.display = 'none';
        }
    }

    function syncHidden() {
        if (catSelect.value === '__custom__') {
            customWrap.style.display = '';
            hiddenInput.value = customInput.value.trim();
        } else {
            customWrap.style.display = 'none';
            hiddenInput.value = catSelect.value;
        }
    }

    sectionSel.addEventListener('change', toggleCategoryField);
    catSelect.addEventListener('change', syncHidden);
    customInput.addEventListener('input', syncHidden);

    // Run once on page load
    toggleCategoryField();
    if (catSelect.value) syncHidden();

    // Initialize Enhanced Image Upload Handler
    var uploadHandler = new ImageUploadHandler({
        fileInput: '#images',
        dropZone: '#dropZone',
        previewContainer: '#imagePreviewContainer',
        uploadButton: '#uploadButton',
        form: '#uploadForm',
        maxWidth: 1920,
        maxHeight: 1920,
        quality: 0.85,
        maxFileSize: 50 * 1024 * 1024, // 50MB - large raw photos are compressed client-side before upload
        autoUpload: true, // Start upload immediately after file selection
        uploadUrl: 'ajax-upload.php',
        onUploadStart: function() {
        },
        onUploadProgress: function(percent) {
        },
        onUploadComplete: function(result) {
            if (result.uploadedCount > 0 && result.errorCount === 0) {
                // Redirect to image list after successful upload
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
