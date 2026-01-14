<?php
$page_title = 'Edit Image';
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

// Get image ID
$image_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($image_id <= 0) {
    header('Location: index.php');
    exit;
}

// Fetch image details
$stmt = $db->prepare("SELECT * FROM site_images WHERE id = ?");
$stmt->execute([$image_id]);
$image = $stmt->fetch();

if (!$image) {
    $_SESSION['error_message'] = 'Image not found.';
    header('Location: index.php');
    exit;
}

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
    } else {
        $update_image = false;
        $new_filename = $image['image_path'];

        // Handle new file upload if provided
        if (isset($_FILES['image']) && $_FILES['image']['error'] == UPLOAD_ERR_OK) {
            $file = $_FILES['image'];
            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
            $max_size = 5 * 1024 * 1024; // 5MB

            if (!in_array($file['type'], $allowed_types)) {
                $error_message = 'Invalid file type. Only JPG, PNG, GIF, and WebP images are allowed.';
            } elseif ($file['size'] > $max_size) {
                $error_message = 'File is too large. Maximum size is 5MB.';
            } else {
                // Generate unique filename
                $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $new_filename = $section . '_' . time() . '_' . uniqid() . '.' . $extension;
                $upload_path = UPLOAD_PATH . $new_filename;

                // Create uploads directory if it doesn't exist
                if (!is_dir(UPLOAD_PATH)) {
                    mkdir(UPLOAD_PATH, 0755, true);
                }

                // Move uploaded file
                if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                    $update_image = true;
                    // Delete old image file
                    $old_file = UPLOAD_PATH . $image['image_path'];
                    if (file_exists($old_file)) {
                        unlink($old_file);
                    }
                } else {
                    $error_message = 'Failed to upload new file. Please check directory permissions.';
                }
            }
        }

        // Update database if no errors
        if (empty($error_message)) {
            try {
                $sql = "UPDATE site_images SET title = ?, description = ?, section = ?, display_order = ?, status = ?, image_path = ? WHERE id = ?";
                
                $stmt = $db->prepare($sql);
                $result = $stmt->execute([
                    $title,
                    $description,
                    $section,
                    $display_order,
                    $status,
                    $new_filename,
                    $image_id
                ]);

                if ($result) {
                    // Update local image array
                    $image['title'] = $title;
                    $image['description'] = $description;
                    $image['section'] = $section;
                    $image['display_order'] = $display_order;
                    $image['status'] = $status;
                    $image['image_path'] = $new_filename;
                    
                    // Log activity
                    logActivity($current_user['id'], 'Updated image', 'site_images', $image_id, "Updated image: $title");
                    
                    $success_message = 'Image updated successfully!';
                } else {
                    // If update failed and we uploaded a new file, delete it
                    if ($update_image && file_exists(UPLOAD_PATH . $new_filename)) {
                        unlink(UPLOAD_PATH . $new_filename);
                    }
                    $error_message = 'Failed to update image. Please try again.';
                }
            } catch (Exception $e) {
                // If exception and we uploaded a new file, delete it
                if ($update_image && file_exists(UPLOAD_PATH . $new_filename)) {
                    unlink(UPLOAD_PATH . $new_filename);
                }
                $error_message = 'Error: ' . $e->getMessage();
            }
        }
    }
}

$image_url = UPLOAD_URL . $image['image_path'];
$image_exists = file_exists(UPLOAD_PATH . $image['image_path']);
?>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-edit"></i> Edit Image</h5>
                <div>
                    <a href="view.php?id=<?php echo $image['id']; ?>" class="btn btn-info btn-sm">
                        <i class="fas fa-eye"></i> View
                    </a>
                    <a href="index.php" class="btn btn-secondary btn-sm">
                        <i class="fas fa-arrow-left"></i> Back to List
                    </a>
                </div>
            </div>
            <div class="card-body">
                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-4">
                        <h6 class="text-muted mb-3">Current Image</h6>
                        <div class="border rounded p-3 text-center bg-light">
                            <?php if ($image_exists): ?>
                                <img src="<?php echo $image_url; ?>" alt="<?php echo htmlspecialchars($image['title']); ?>" 
                                     class="img-fluid" style="max-height: 300px; object-fit: contain;">
                            <?php else: ?>
                                <div class="text-danger py-3">
                                    <i class="fas fa-exclamation-triangle fa-2x mb-2"></i>
                                    <p class="small">Image file not found</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        <small class="text-muted d-block mt-2">Leave the file field empty to keep current image</small>
                    </div>

                    <div class="col-md-8">
                        <form method="POST" action="" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="title" class="form-label">Image Title <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="title" name="title" 
                                       value="<?php echo htmlspecialchars($image['title']); ?>" 
                                       placeholder="e.g., Grand Ballroom Banner" required>
                                <small class="text-muted">A descriptive title for this image</small>
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3" 
                                          placeholder="Optional description or alt text for the image"><?php echo htmlspecialchars($image['description']); ?></textarea>
                                <small class="text-muted">Optional: Provide additional details or alt text</small>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="section" class="form-label">Section <span class="text-danger">*</span></label>
                                        <select class="form-select" id="section" name="section" required>
                                            <?php foreach ($sections as $key => $label): ?>
                                                <option value="<?php echo $key; ?>" <?php echo $image['section'] == $key ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($label); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <small class="text-muted">Where should this image appear?</small>
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="display_order" class="form-label">Display Order</label>
                                        <input type="number" class="form-control" id="display_order" name="display_order" 
                                               value="<?php echo $image['display_order']; ?>" 
                                               min="0" placeholder="0">
                                        <small class="text-muted">Lower = first</small>
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="status" class="form-label">Status</label>
                                        <select class="form-select" id="status" name="status">
                                            <option value="active" <?php echo $image['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                            <option value="inactive" <?php echo $image['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="image" class="form-label">Replace Image (Optional)</label>
                                <input type="file" class="form-control" id="image" name="image" accept="image/*">
                                <small class="text-muted">Only upload if you want to replace the current image. Supported: JPG, PNG, GIF, WebP. Max 5MB</small>
                            </div>

                            <div class="d-flex justify-content-between">
                                <a href="index.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-save"></i> Update Image
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
