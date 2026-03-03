<?php
$page_title = 'Edit Venue';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();
$success_message = '';
$error_message = '';

// Get venue ID from URL
$venue_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($venue_id <= 0) {
    header('Location: index.php');
    exit;
}

// Fetch venue details
$stmt = $db->prepare("SELECT * FROM venues WHERE id = ?");
$stmt->execute([$venue_id]);
$venue = $stmt->fetch();

if (!$venue) {
    header('Location: index.php');
    exit;
}

// Fetch active cities for dropdown
$cities_stmt = $db->query("SELECT id, name FROM cities WHERE status = 'active' ORDER BY name");
$cities = $cities_stmt->fetchAll();

// Handle venue image upload (separate from main form)
if (isset($_POST['upload_image']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['venue_image_upload'])) {
        $upload_result = handleImageUpload($_FILES['venue_image_upload'], 'venue');

        if ($upload_result['success']) {
            try {
                $is_primary = isset($_POST['is_primary']) ? 1 : 0;
                $display_order = intval($_POST['display_order']);

                // If this is primary, unset other primary images for this venue
                if ($is_primary) {
                    $db->prepare("UPDATE venue_images SET is_primary = 0 WHERE venue_id = ?")->execute([$venue_id]);
                }

                $db->prepare("INSERT INTO venue_images (venue_id, image_path, is_primary, display_order) VALUES (?, ?, ?, ?)")
                   ->execute([$venue_id, $upload_result['filename'], $is_primary, $display_order]);

                logActivity($current_user['id'], 'Uploaded venue image', 'venue_images', $db->lastInsertId(), "Uploaded image for venue: {$venue['name']}");
                $success_message = 'Image uploaded successfully!';
            } catch (Exception $e) {
                deleteUploadedFile($upload_result['filename']);
                $error_message = 'Error saving image: ' . $e->getMessage();
            }
        } else {
            $error_message = $upload_result['message'];
        }
    } else {
        $error_message = 'Please select an image to upload.';
    }
}

// Handle venue image deletion
if (isset($_POST['delete_image']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error_message = 'Invalid security token. Please try again.';
    } else {
        $image_id = intval($_POST['delete_image']);
        try {
            $stmt = $db->prepare("SELECT * FROM venue_images WHERE id = ? AND venue_id = ?");
            $stmt->execute([$image_id, $venue_id]);
            $img = $stmt->fetch();
            if ($img) {
                deleteUploadedFile($img['image_path']);
                $db->prepare("DELETE FROM venue_images WHERE id = ?")->execute([$image_id]);
                logActivity($current_user['id'], 'Deleted venue image', 'venue_images', $image_id, "Deleted image for venue: {$venue['name']}");
                $success_message = 'Image deleted successfully!';
            }
        } catch (Exception $e) {
            $error_message = 'Error deleting image: ' . $e->getMessage();
        }
    }
}

// Handle form submission
if (isset($_POST['update_venue']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $city_id = isset($_POST['city_id']) && is_numeric($_POST['city_id']) ? intval($_POST['city_id']) : null;
    $address = trim($_POST['address']);
    $description = trim($_POST['description']);
    $contact_phone = trim($_POST['contact_phone']);
    $contact_email = trim($_POST['contact_email']);
    $map_link = trim($_POST['map_link'] ?? '');
    $status = $_POST['status'];

    // Validation
    if (empty($name) || empty($city_id) || empty($contact_phone)) {
        $error_message = 'Please fill in all required fields.';
    } else {
        try {
            // Handle image upload
            $image_filename = $venue['image'];
            if (isset($_FILES['venue_image']) && $_FILES['venue_image']['error'] !== UPLOAD_ERR_NO_FILE) {
                $upload_result = handleImageUpload($_FILES['venue_image'], 'venue');
                
                if ($upload_result['success']) {
                    // Delete old image if exists
                    if (!empty($venue['image'])) {
                        deleteUploadedFile($venue['image']);
                    }
                    $image_filename = $upload_result['filename'];
                } else {
                    $error_message = $upload_result['message'];
                }
            }
            
            if (empty($error_message)) {
                $sql = "UPDATE venues SET 
                        name = ?,
                        city_id = ?,
                        address = ?,
                        description = ?,
                        image = ?,
                        contact_phone = ?,
                        contact_email = ?,
                        map_link = ?,
                        status = ?
                        WHERE id = ?";
                
                $stmt = $db->prepare($sql);
                $result = $stmt->execute([
                    $name,
                    $city_id,
                    $address,
                    $description,
                    $image_filename,
                    $contact_phone,
                    $contact_email,
                    $map_link ?: null,
                    $status,
                    $venue_id
                ]);

                if ($result) {
                    // Log activity
                    logActivity($current_user['id'], 'Updated venue', 'venues', $venue_id, "Updated venue: $name");
                    
                    $success_message = 'Venue updated successfully!';
                    
                    // Refresh venue data
                    $stmt = $db->prepare("SELECT * FROM venues WHERE id = ?");
                    $stmt->execute([$venue_id]);
                    $venue = $stmt->fetch();
                } else {
                    $error_message = 'Failed to update venue. Please try again.';
                }
            }
        } catch (Exception $e) {
            $error_message = 'Error: ' . $e->getMessage();
        }
    }
}

// Fetch venue images for display
try {
    $venue_images_stmt = $db->prepare("SELECT * FROM venue_images WHERE venue_id = ? ORDER BY is_primary DESC, display_order ASC");
    $venue_images_stmt->execute([$venue_id]);
    $venue_imgs = $venue_images_stmt->fetchAll();
} catch (Exception $e) {
    $venue_imgs = [];
}
?>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-edit"></i> Edit Venue</h5>
                <div>
                    <a href="view.php?id=<?php echo $venue_id; ?>" class="btn btn-info btn-sm">
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

                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name" class="form-label">Venue Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?php echo htmlspecialchars($venue['name']); ?>" 
                                       placeholder="e.g., Grand Palace Hotel" required>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="city_id" class="form-label">City <span class="text-danger">*</span></label>
                                <select class="form-select" id="city_id" name="city_id" required>
                                    <option value="">Select a city...</option>
                                    <?php foreach ($cities as $city): ?>
                                        <option value="<?php echo $city['id']; ?>"
                                            <?php echo $venue['city_id'] == $city['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($city['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="address" class="form-label">Full Address</label>
                        <textarea class="form-control" id="address" name="address" rows="2" 
                                  placeholder="Enter complete address..."><?php echo htmlspecialchars($venue['address']); ?></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="contact_phone" class="form-label">Contact Phone <span class="text-danger">*</span></label>
                                <input type="tel" class="form-control" id="contact_phone" name="contact_phone" 
                                       value="<?php echo htmlspecialchars($venue['contact_phone']); ?>" 
                                       placeholder="e.g., +977 9876543210" required>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="contact_email" class="form-label">Contact Email</label>
                                <input type="email" class="form-control" id="contact_email" name="contact_email" 
                                       value="<?php echo htmlspecialchars($venue['contact_email']); ?>" 
                                       placeholder="e.g., info@venue.com">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3" 
                                  placeholder="Describe the venue, its facilities, and unique features..."><?php echo htmlspecialchars($venue['description']); ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="map_link" class="form-label"><i class="fas fa-map-marker-alt"></i> Google Map Link (Optional)</label>
                        <input type="url" class="form-control" id="map_link" name="map_link" 
                               value="<?php echo htmlspecialchars($venue['map_link'] ?? ''); ?>" 
                               placeholder="e.g., https://maps.google.com/?q=...">
                        <small class="text-muted">Paste the Google Maps share link so users can view the exact location.</small>
                    </div>

                    <div class="mb-3">
                        <label for="venue_image" class="form-label">Main Venue Image</label>
                        <?php echo displayImagePreview($venue['image'], 'Current venue image'); ?>
                        <input type="file" class="form-control" id="venue_image" name="venue_image" accept="image/*">
                        <small class="text-muted">Upload a new image to replace the current one. JPG, PNG, GIF, or WebP. Max 5MB</small>
                    </div>

                    <div class="mb-3">
                        <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="active" <?php echo $venue['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $venue['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>

                    <div class="d-flex justify-content-between">
                        <form method="POST" action="delete.php" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this venue? This action cannot be undone.');">
                            <input type="hidden" name="id" value="<?php echo $venue_id; ?>">
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-trash"></i> Delete Venue
                            </button>
                        </form>
                        <div>
                            <a href="index.php" class="btn btn-secondary me-2">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                            <button type="submit" name="update_venue" class="btn btn-success">
                                <i class="fas fa-save"></i> Update Venue
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Venue Images Management -->
<div class="row mt-3">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-images"></i> Venue Images</h5>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="collapse" data-bs-target="#uploadVenueImageForm">
                    <i class="fas fa-upload"></i> Upload New Image
                </button>
            </div>
            <div class="card-body">
                <!-- Image Upload Form -->
                <div class="collapse mb-3" id="uploadVenueImageForm">
                    <div class="card">
                        <div class="card-body bg-light">
                            <h6 class="card-title">Upload New Image</h6>
                            <form method="POST" action="" enctype="multipart/form-data">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="venue_image_upload" class="form-label">Select Image <span class="text-danger">*</span></label>
                                            <input type="file" class="form-control" id="venue_image_upload" name="venue_image_upload" accept="image/*" required>
                                            <small class="text-muted">JPG, PNG, GIF, or WebP. Max 5MB</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label for="display_order" class="form-label">Display Order</label>
                                            <input type="number" class="form-control" id="display_order" name="display_order" value="0" min="0">
                                            <small class="text-muted">Lower numbers first</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label class="form-label d-block">&nbsp;</label>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="is_primary" name="is_primary" value="1">
                                                <label class="form-check-label" for="is_primary">
                                                    Set as Primary Image
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <button type="submit" name="upload_image" class="btn btn-success">
                                    <i class="fas fa-upload"></i> Upload Image
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Display Uploaded Images -->
                <?php if (!empty($venue_imgs)): ?>
                    <div class="row">
                        <?php foreach ($venue_imgs as $img): ?>
                            <div class="col-md-3 mb-3">
                                <div class="card h-100">
                                    <?php
                                    $img_url  = UPLOAD_URL . rawurlencode($img['image_path']);
                                    $img_file = UPLOAD_PATH . $img['image_path'];
                                    ?>
                                    <?php if (file_exists($img_file)): ?>
                                        <img src="<?php echo htmlspecialchars($img_url, ENT_QUOTES, 'UTF-8'); ?>" class="card-img-top" alt="Venue image" style="height: 200px; object-fit: cover;">
                                    <?php else: ?>
                                        <div class="card-img-top bg-secondary d-flex align-items-center justify-content-center" style="height: 200px;">
                                            <i class="fas fa-image fa-3x text-white"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div class="card-body text-center">
                                        <p class="mb-1 small text-muted">Order: <?php echo htmlspecialchars($img['display_order'], ENT_QUOTES, 'UTF-8'); ?></p>
                                        <?php if ($img['is_primary']): ?>
                                            <span class="badge bg-primary mb-2">Primary Image</span>
                                        <?php endif; ?>
                                        <div>
                                            <form method="POST" action="" style="display: inline;">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                                <input type="hidden" name="delete_image" value="<?php echo htmlspecialchars($img['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                                <button type="submit" class="btn btn-danger btn-sm"
                                                        onclick="return confirm('Are you sure you want to delete this image?')">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> No images uploaded yet. Click "Upload New Image" to add photos for this venue.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
