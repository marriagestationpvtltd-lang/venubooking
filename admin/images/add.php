<?php
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
    'testimonial' => 'Testimonials',
    'feature' => 'Features Section',
    'about' => 'About Us Section',
    'other' => 'Other'
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $section = $_POST['section'];
    $display_order = intval($_POST['display_order']);
    $status = $_POST['status'];

    // Validation
    if (empty($title) || empty($section)) {
        $error_message = 'Please fill in all required fields.';
    } elseif (!isset($_FILES['image']) || $_FILES['image']['error'] == UPLOAD_ERR_NO_FILE) {
        $error_message = 'Please select an image to upload.';
    } else {
        // Handle file upload
        $file = $_FILES['image'];
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        $max_size = 5 * 1024 * 1024; // 5MB

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $error_message = 'Error uploading file. Please try again.';
        } elseif (!in_array($file['type'], $allowed_types)) {
            $error_message = 'Invalid file type. Only JPG, PNG, GIF, and WebP images are allowed.';
        } elseif ($file['size'] > $max_size) {
            $error_message = 'File is too large. Maximum size is 5MB.';
        } else {
            // Generate unique filename
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = $section . '_' . time() . '_' . uniqid() . '.' . $extension;
            $upload_path = UPLOAD_PATH . $filename;

            // Create uploads directory if it doesn't exist
            if (!is_dir(UPLOAD_PATH)) {
                mkdir(UPLOAD_PATH, 0755, true);
            }

            // Move uploaded file
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                try {
                    $sql = "INSERT INTO site_images (title, description, image_path, section, display_order, status) 
                            VALUES (?, ?, ?, ?, ?, ?)";
                    
                    $stmt = $db->prepare($sql);
                    $result = $stmt->execute([
                        $title,
                        $description,
                        $filename,
                        $section,
                        $display_order,
                        $status
                    ]);

                    if ($result) {
                        $image_id = $db->lastInsertId();
                        
                        // Log activity
                        logActivity($current_user['id'], 'Uploaded new image', 'site_images', $image_id, "Uploaded image: $title");
                        
                        $success_message = 'Image uploaded successfully!';
                        
                        // Clear form
                        $_POST = [];
                    } else {
                        // Delete uploaded file if database insert fails
                        unlink($upload_path);
                        $error_message = 'Failed to save image to database. Please try again.';
                    }
                } catch (Exception $e) {
                    // Delete uploaded file on exception
                    if (file_exists($upload_path)) {
                        unlink($upload_path);
                    }
                    $error_message = 'Error: ' . $e->getMessage();
                }
            } else {
                $error_message = 'Failed to upload file. Please check directory permissions.';
            }
        }
    }
}
?>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-upload"></i> Upload New Image</h5>
                <a href="index.php" class="btn btn-secondary btn-sm">
                    <i class="fas fa-arrow-left"></i> Back to List
                </a>
            </div>
            <div class="card-body">
                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                        <a href="index.php" class="alert-link">View all images</a> or 
                        <a href="add.php" class="alert-link">upload another</a>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="title" class="form-label">Image Title <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="title" name="title" 
                                       value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>" 
                                       placeholder="e.g., Grand Ballroom Banner" required>
                                <small class="text-muted">A descriptive title for this image</small>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="section" class="form-label">Section <span class="text-danger">*</span></label>
                                <select class="form-select" id="section" name="section" required>
                                    <option value="">Select Section</option>
                                    <?php foreach ($sections as $key => $label): ?>
                                        <option value="<?php echo $key; ?>" <?php echo (isset($_POST['section']) && $_POST['section'] == $key) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">Where should this image appear?</small>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3" 
                                  placeholder="Optional description or alt text for the image"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                        <small class="text-muted">Optional: Provide additional details or alt text</small>
                    </div>

                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="image" class="form-label">Image File <span class="text-danger">*</span></label>
                                <input type="file" class="form-control" id="image" name="image" accept="image/*" required>
                                <small class="text-muted">Supported formats: JPG, PNG, GIF, WebP. Maximum size: 5MB</small>
                            </div>
                        </div>

                        <div class="col-md-2">
                            <div class="mb-3">
                                <label for="display_order" class="form-label">Display Order</label>
                                <input type="number" class="form-control" id="display_order" name="display_order" 
                                       value="<?php echo isset($_POST['display_order']) ? $_POST['display_order'] : '0'; ?>" 
                                       min="0" placeholder="0">
                                <small class="text-muted">Lower numbers appear first</small>
                            </div>
                        </div>

                        <div class="col-md-2">
                            <div class="mb-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="active" <?php echo (!isset($_POST['status']) || $_POST['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo (isset($_POST['status']) && $_POST['status'] == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> <strong>Section Guide:</strong>
                        <ul class="mb-0 mt-2">
                            <li><strong>Banner:</strong> Main hero/banner images on homepage</li>
                            <li><strong>Venue Gallery:</strong> Images displayed in venue galleries</li>
                            <li><strong>Hall Gallery:</strong> Images shown in hall detail pages</li>
                            <li><strong>Package/Menu:</strong> Images for menu packages</li>
                            <li><strong>Gallery:</strong> General photo gallery section</li>
                            <li><strong>Other sections:</strong> Images for various other parts of the website</li>
                        </ul>
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-upload"></i> Upload Image
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
