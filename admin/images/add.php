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
    'work_photos' => 'Our Work (Portfolio Slideshow)',
    'testimonial' => 'Testimonials',
    'feature' => 'Features Section',
    'about' => 'About Us Section',
    'other' => 'Other'
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title_base = trim($_POST['title']);
    $description = trim($_POST['description']);
    $section = $_POST['section'];
    $display_order = intval($_POST['display_order']);
    $status = $_POST['status'];

    // Validation
    if (empty($section)) {
        $error_message = 'Please select a section.';
    } elseif (!isset($_FILES['images']) || (count(array_filter($_FILES['images']['error'], function($e) { return $e !== UPLOAD_ERR_NO_FILE; })) === 0)) {
        $error_message = 'Please select at least one image to upload.';
    } else {
        $files = $_FILES['images'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $max_size = 5 * 1024 * 1024; // 5MB
        $file_count = count($files['name']);
        $success_count = 0;
        $file_errors = [];

        // Create uploads directory if it doesn't exist
        if (!is_dir(UPLOAD_PATH)) {
            mkdir(UPLOAD_PATH, 0755, true);
        }

        $sql = "INSERT INTO site_images (title, description, image_path, section, display_order, status) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $db->prepare($sql);

        for ($i = 0; $i < $file_count; $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_NO_FILE) continue;

            // Determine title for this file
            $file_title = $title_base ?: pathinfo($files['name'][$i], PATHINFO_FILENAME);
            if ($file_count > 1 && $title_base) {
                $file_title = $title_base . ' (' . ($i + 1) . ')';
            }

            if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                $file_errors[] = 'Error uploading "' . htmlspecialchars($files['name'][$i]) . '".';
                continue;
            }
            if (!in_array($files['type'][$i], $allowed_types)) {
                $file_errors[] = '"' . htmlspecialchars($files['name'][$i]) . '": Invalid file type. Only JPG, PNG, GIF, and WebP are allowed.';
                continue;
            }
            if ($files['size'][$i] > $max_size) {
                $file_errors[] = '"' . htmlspecialchars($files['name'][$i]) . '": File exceeds 5MB limit.';
                continue;
            }

            // Generate unique filename (include index to avoid collisions in same-second batch)
            $extension = pathinfo($files['name'][$i], PATHINFO_EXTENSION);
            $filename = $section . '_' . time() . '_' . $i . '_' . uniqid() . '.' . $extension;
            $upload_path = UPLOAD_PATH . $filename;

            if (move_uploaded_file($files['tmp_name'][$i], $upload_path)) {
                try {
                    $result = $stmt->execute([$file_title, $description, $filename, $section, $display_order, $status]);
                    if ($result) {
                        $image_id = $db->lastInsertId();
                        logActivity($current_user['id'], 'Uploaded new image', 'site_images', $image_id, "Uploaded image: $file_title");
                        $success_count++;
                    } else {
                        unlink($upload_path);
                        $file_errors[] = '"' . htmlspecialchars($files['name'][$i]) . '": Failed to save to database.';
                    }
                } catch (Exception $e) {
                    if (file_exists($upload_path)) unlink($upload_path);
                    $file_errors[] = '"' . htmlspecialchars($files['name'][$i]) . '": ' . $e->getMessage();
                }
            } else {
                $file_errors[] = '"' . htmlspecialchars($files['name'][$i]) . '": Failed to save file. Check directory permissions.';
            }
        }

        if ($success_count > 0) {
            $success_message = $success_count . ' image' . ($success_count > 1 ? 's' : '') . ' uploaded successfully!';
            $_POST = [];
        }
        if (!empty($file_errors)) {
            $error_message = implode('<br>', $file_errors);
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
                                <label for="title" class="form-label">Image Title</label>
                                <input type="text" class="form-control" id="title" name="title" 
                                       value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>" 
                                       placeholder="e.g., Grand Ballroom Banner">
                                <small class="text-muted">Optional when uploading multiple images — filename is used as title if left blank</small>
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
                                <label for="images" class="form-label">Image Files <span class="text-danger">*</span></label>
                                <input type="file" class="form-control" id="images" name="images[]" accept="image/*" multiple required>
                                <small class="text-muted">Supported formats: JPG, PNG, GIF, WebP. Maximum size: 5MB each. Select multiple files at once to bulk upload.</small>
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
                            <li><strong>Our Work (Portfolio Slideshow):</strong> Showcase photos of your work — displayed as an auto-scrolling infinite slideshow on the homepage. Pauses when visitors hover or touch.</li>
                            <li><strong>Other sections:</strong> Images for various other parts of the website</li>
                        </ul>
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-upload"></i> Upload Image(s)
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
