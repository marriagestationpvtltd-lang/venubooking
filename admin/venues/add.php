<?php
$page_title = 'Add New Venue';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();
$success_message = '';
$error_message = '';

// Fetch active cities for dropdown
$cities_stmt = $db->query("SELECT id, name FROM cities WHERE status = 'active' ORDER BY name");
$cities = $cities_stmt->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error_message = 'Invalid security token. Please try again.';
    } else {
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
            $image_filename = null;
            if (isset($_FILES['venue_image']) && $_FILES['venue_image']['error'] !== UPLOAD_ERR_NO_FILE) {
                $upload_result = handleImageUpload($_FILES['venue_image'], 'venue');
                
                if ($upload_result['success']) {
                    $image_filename = $upload_result['filename'];
                } else {
                    $error_message = $upload_result['message'];
                }
            }
            
            if (empty($error_message)) {
                $sql = "INSERT INTO venues (name, city_id, address, description, image, contact_phone, contact_email, map_link, status) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
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
                    $status
                ]);

                if ($result) {
                    $venue_id = $db->lastInsertId();

                    // Handle additional multi-image uploads
                    if (isset($_FILES['venue_images']) && is_array($_FILES['venue_images']['name'])) {
                        $files = $_FILES['venue_images'];
                        $count = count($files['name']);
                        for ($i = 0; $i < $count; $i++) {
                            if ($files['error'][$i] !== UPLOAD_ERR_NO_FILE) {
                                $single_file = [
                                    'name'     => $files['name'][$i],
                                    'type'     => $files['type'][$i],
                                    'tmp_name' => $files['tmp_name'][$i],
                                    'error'    => $files['error'][$i],
                                    'size'     => $files['size'][$i],
                                ];
                                $up = handleImageUpload($single_file, 'venue');
                                if ($up['success']) {
                                    $is_primary = ($i === 0 && empty($image_filename)) ? 1 : 0;
                                    $db->prepare("INSERT INTO venue_images (venue_id, image_path, is_primary, display_order) VALUES (?, ?, ?, ?)")
                                       ->execute([$venue_id, $up['filename'], $is_primary, $i]);
                                }
                            }
                        }
                    }

                    // Handle 360° panoramic image upload
                    if (isset($_FILES['venue_pano_image']) && $_FILES['venue_pano_image']['error'] !== UPLOAD_ERR_NO_FILE) {
                        $pano_result = handleImageUpload($_FILES['venue_pano_image'], 'venue_pano');
                        if ($pano_result['success']) {
                            try {
                                $db->prepare("UPDATE venues SET pano_image = ? WHERE id = ?")->execute([$pano_result['filename'], $venue_id]);
                                logActivity($current_user['id'], 'Uploaded venue pano image', 'venues', $venue_id, "Uploaded 360° pano for venue: $name");
                            } catch (Exception $e) {
                                deleteUploadedFile($pano_result['filename']);
                            }
                        }
                    }

                    // Log activity
                    logActivity($current_user['id'], 'Added new venue', 'venues', $venue_id, "Added venue: $name");
                    
                    $success_message = 'Venue added successfully!';
                    
                    // Clear form
                    $_POST = [];
                } else {
                    // Delete uploaded image if database insert fails
                    if ($image_filename) {
                        deleteUploadedFile($image_filename);
                    }
                    $error_message = 'Failed to add venue. Please try again.';
                }
            }
        } catch (Exception $e) {
            // Delete uploaded image on exception
            if (isset($image_filename) && $image_filename) {
                deleteUploadedFile($image_filename);
            }
            $error_message = 'Error adding venue. Please try again or contact support.';
        }
    }
    } // end CSRF-valid else
}
?>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-plus"></i> Add New Venue</h5>
                <a href="index.php" class="btn btn-secondary btn-sm">
                    <i class="fas fa-arrow-left"></i> Back to List
                </a>
            </div>
            <div class="card-body">
                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
                        <a href="index.php" class="alert-link">View all venues</a>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name" class="form-label">Venue Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" 
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
                                            <?php echo (isset($_POST['city_id']) && $_POST['city_id'] == $city['id']) ? 'selected' : ''; ?>>
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
                                  placeholder="Enter complete address..."><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="contact_phone" class="form-label">Contact Phone <span class="text-danger">*</span></label>
                                <input type="tel" class="form-control" id="contact_phone" name="contact_phone" 
                                       value="<?php echo isset($_POST['contact_phone']) ? htmlspecialchars($_POST['contact_phone']) : ''; ?>" 
                                       placeholder="e.g., +977 9876543210" required>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="contact_email" class="form-label">Contact Email</label>
                                <input type="email" class="form-control" id="contact_email" name="contact_email" 
                                       value="<?php echo isset($_POST['contact_email']) ? htmlspecialchars($_POST['contact_email']) : ''; ?>" 
                                       placeholder="e.g., info@venue.com">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3" 
                                  placeholder="Describe the venue, its facilities, and unique features..."><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="map_link" class="form-label"><i class="fas fa-map-marker-alt"></i> Google Map Link (Optional)</label>
                        <input type="url" class="form-control" id="map_link" name="map_link" 
                               value="<?php echo isset($_POST['map_link']) ? htmlspecialchars($_POST['map_link']) : ''; ?>" 
                               placeholder="e.g., https://maps.google.com/?q=...">
                        <small class="text-muted">Paste the Google Maps share link so users can view the exact location.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Venue Photos (Optional)</label>
                        <input type="file" class="form-control" id="venue_images" name="venue_images[]" accept="image/*" multiple>
                        <small class="text-muted">Upload one or more photos for this venue. JPG, PNG, GIF, or WebP. Max 5MB each. The first photo uploaded here will be set as the primary gallery image.</small>
                    </div>

                    <div class="mb-3">
                        <label for="venue_pano_image" class="form-label"><i class="fas fa-street-view text-primary"></i> 360° Panoramic Photo (Optional)</label>
                        <input type="file" class="form-control" id="venue_pano_image" name="venue_pano_image" accept="image/*">
                        <small class="text-muted">Upload an equirectangular (360°) panoramic photo for an immersive venue preview. JPG or PNG recommended. Max 5MB.</small>
                    </div>

                    <div class="mb-3">
                        <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="active" <?php echo (!isset($_POST['status']) || $_POST['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo (isset($_POST['status']) && $_POST['status'] == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> Add Venue
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
