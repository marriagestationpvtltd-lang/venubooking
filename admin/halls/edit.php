<?php
$page_title = 'Edit Hall';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();
$success_message = '';
$error_message = '';

// Get hall ID from URL
$hall_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($hall_id <= 0) {
    header('Location: index.php');
    exit;
}

// Fetch hall details
$stmt = $db->prepare("SELECT * FROM halls WHERE id = ?");
$stmt->execute([$hall_id]);
$hall = $stmt->fetch();

if (!$hall) {
    header('Location: index.php');
    exit;
}

// Fetch all venues for dropdown
$venues_stmt = $db->query("SELECT id, name FROM venues WHERE status = 'active' ORDER BY name");
$venues = $venues_stmt->fetchAll();

// Fetch all active menus for assignment
$available_menus = getAllActiveMenus();

// Fetch currently assigned menus for this hall
$assigned_menu_ids = getAssignedMenuIds($hall_id);

// Handle image upload
if (isset($_POST['upload_image']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['hall_image'])) {
        $upload_result = handleImageUpload($_FILES['hall_image'], 'hall');
        
        if ($upload_result['success']) {
            try {
                $is_primary = isset($_POST['is_primary']) ? 1 : 0;
                $display_order = intval($_POST['display_order']);
                
                // If this is primary, unset other primary images
                if ($is_primary) {
                    $db->prepare("UPDATE hall_images SET is_primary = 0 WHERE hall_id = ?")->execute([$hall_id]);
                }
                
                $sql = "INSERT INTO hall_images (hall_id, image_path, is_primary, display_order) VALUES (?, ?, ?, ?)";
                $stmt = $db->prepare($sql);
                $stmt->execute([$hall_id, $upload_result['filename'], $is_primary, $display_order]);
                
                logActivity($current_user['id'], 'Uploaded hall image', 'hall_images', $db->lastInsertId(), "Uploaded image for hall: {$hall['name']}");
                
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

// Handle image deletion via POST
if (isset($_POST['delete_image']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error_message = 'Invalid security token. Please try again.';
    } else {
        $image_id = intval($_POST['delete_image']);
        try {
            $stmt = $db->prepare("SELECT * FROM hall_images WHERE id = ? AND hall_id = ?");
            $stmt->execute([$image_id, $hall_id]);
            $image = $stmt->fetch();
            
            if ($image) {
                deleteUploadedFile($image['image_path']);
                $db->prepare("DELETE FROM hall_images WHERE id = ?")->execute([$image_id]);
                
                logActivity($current_user['id'], 'Deleted hall image', 'hall_images', $image_id, "Deleted image for hall: {$hall['name']}");
                
                $success_message = 'Image deleted successfully!';
            }
        } catch (Exception $e) {
            $error_message = 'Error deleting image: ' . $e->getMessage();
        }
    }
}

// Handle form submission
if (isset($_POST['update_hall']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $venue_id = intval($_POST['venue_id']);
    $name = trim($_POST['name']);
    $capacity = intval($_POST['capacity']);
    $hall_type = $_POST['hall_type'];
    $indoor_outdoor = $_POST['indoor_outdoor'];
    $base_price = floatval($_POST['base_price']);
    $description = trim($_POST['description']);
    $features = trim($_POST['features']);
    $status = $_POST['status'];

    // Validation
    if (empty($name) || $venue_id <= 0 || $capacity <= 0 || $base_price <= 0) {
        $error_message = 'Please fill in all required fields correctly.';
    } else {
        // Verify venue exists
        $venue_check = $db->prepare("SELECT id FROM venues WHERE id = ?");
        $venue_check->execute([$venue_id]);
        if (!$venue_check->fetch()) {
            $error_message = 'Selected venue does not exist.';
        } else {
        try {
            $sql = "UPDATE halls SET 
                    venue_id = ?,
                    name = ?,
                    capacity = ?,
                    hall_type = ?,
                    indoor_outdoor = ?,
                    base_price = ?,
                    description = ?,
                    features = ?,
                    status = ?
                    WHERE id = ?";
            
            $stmt = $db->prepare($sql);
            $result = $stmt->execute([
                $venue_id,
                $name,
                $capacity,
                $hall_type,
                $indoor_outdoor,
                $base_price,
                $description,
                $features,
                $status,
                $hall_id
            ]);

            if ($result) {
                // Handle menu assignments
                $selected_menus = isset($_POST['menus']) ? $_POST['menus'] : [];
                updateHallMenus($hall_id, $selected_menus);
                
                // Log activity
                logActivity($current_user['id'], 'Updated hall', 'halls', $hall_id, "Updated hall: $name");
                
                $success_message = 'Hall updated successfully!';
                
                // Refresh hall data
                $stmt = $db->prepare("SELECT * FROM halls WHERE id = ?");
                $stmt->execute([$hall_id]);
                $hall = $stmt->fetch();
                
                // Refresh assigned menus
                $assigned_menu_ids = getAssignedMenuIds($hall_id);
            } else {
                $error_message = 'Failed to update hall. Please try again.';
            }
        } catch (Exception $e) {
            $error_message = 'Error: ' . $e->getMessage();
        }
        }
    }
}
?>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-edit"></i> Edit Hall</h5>
                <a href="index.php" class="btn btn-secondary btn-sm">
                    <i class="fas fa-arrow-left"></i> Back to List
                </a>
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

                <form method="POST" action="">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="venue_id" class="form-label">Venue <span class="text-danger">*</span></label>
                                <select class="form-select" id="venue_id" name="venue_id" required>
                                    <option value="">Select Venue</option>
                                    <?php foreach ($venues as $venue): ?>
                                        <option value="<?php echo $venue['id']; ?>" <?php echo $venue['id'] == $hall['venue_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($venue['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name" class="form-label">Hall Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?php echo htmlspecialchars($hall['name']); ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="capacity" class="form-label">Capacity (Guests) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="capacity" name="capacity" 
                                       value="<?php echo $hall['capacity']; ?>" min="1" required>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="hall_type" class="form-label">Hall Type <span class="text-danger">*</span></label>
                                <select class="form-select" id="hall_type" name="hall_type" required>
                                    <option value="single" <?php echo $hall['hall_type'] == 'single' ? 'selected' : ''; ?>>Single</option>
                                    <option value="multiple" <?php echo $hall['hall_type'] == 'multiple' ? 'selected' : ''; ?>>Multiple</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="indoor_outdoor" class="form-label">Indoor/Outdoor <span class="text-danger">*</span></label>
                                <select class="form-select" id="indoor_outdoor" name="indoor_outdoor" required>
                                    <option value="indoor" <?php echo $hall['indoor_outdoor'] == 'indoor' ? 'selected' : ''; ?>>Indoor</option>
                                    <option value="outdoor" <?php echo $hall['indoor_outdoor'] == 'outdoor' ? 'selected' : ''; ?>>Outdoor</option>
                                    <option value="both" <?php echo $hall['indoor_outdoor'] == 'both' ? 'selected' : ''; ?>>Both</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="base_price" class="form-label">Base Price (<?php echo getSetting('currency', 'NPR'); ?>) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="base_price" name="base_price" 
                                       value="<?php echo $hall['base_price']; ?>" min="0" step="0.01" required>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="active" <?php echo $hall['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $hall['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($hall['description']); ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="features" class="form-label">Features</label>
                        <textarea class="form-control" id="features" name="features" rows="2" 
                                  placeholder="e.g., Air conditioning, Stage, Sound system, LED screens"><?php echo htmlspecialchars($hall['features']); ?></textarea>
                        <small class="text-muted">Separate features with commas</small>
                    </div>

                    <h6 class="text-muted border-bottom pb-2 mb-3 mt-4">Assign Menus to Hall</h6>
                    <div class="mb-3">
                        <label class="form-label">Select Menus Available for This Hall</label>
                        <small class="text-muted d-block mb-2">Choose which menus customers can select when booking this hall</small>
                        <?php if (empty($available_menus)): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> No active menus found. 
                                <a href="<?php echo BASE_URL; ?>/admin/menus/add.php" class="alert-link">Add menus</a> first to assign them to halls.
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($available_menus as $menu): ?>
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="menus[]" 
                                                   value="<?php echo $menu['id']; ?>" 
                                                   id="menu_<?php echo $menu['id']; ?>"
                                                   <?php echo in_array($menu['id'], $assigned_menu_ids) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="menu_<?php echo $menu['id']; ?>">
                                                <?php echo htmlspecialchars($menu['name']); ?> 
                                                <span class="text-muted">(<?php echo formatCurrency($menu['price_per_person']); ?>/person)</span>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="d-flex justify-content-between">
                        <button type="button" class="btn btn-danger" onclick="confirmDelete()">
                            <i class="fas fa-trash"></i> Delete Hall
                        </button>
                        <div>
                            <a href="index.php" class="btn btn-secondary me-2">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                            <button type="submit" name="update_hall" class="btn btn-success">
                                <i class="fas fa-save"></i> Update Hall
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
// Display linked menus
$menus_stmt = $db->prepare("SELECT m.* FROM menus m 
                            INNER JOIN hall_menus hm ON m.id = hm.menu_id 
                            WHERE hm.hall_id = ? 
                            AND hm.status = 'active'
                            ORDER BY m.name");
$menus_stmt->execute([$hall_id]);
$linked_menus = $menus_stmt->fetchAll();

if (count($linked_menus) > 0):
?>
<div class="row mt-3">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-utensils"></i> Linked Menus</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Menu Name</th>
                                <th>Price per Person</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($linked_menus as $menu): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($menu['name']); ?></td>
                                    <td><?php echo formatCurrency($menu['price_per_person']); ?></td>
                                    <td><span class="badge bg-<?php echo $menu['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                        <?php echo ucfirst($menu['status']); ?>
                                    </span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
// Display hall images
$images_stmt = $db->prepare("SELECT * FROM hall_images WHERE hall_id = ? ORDER BY is_primary DESC, display_order");
$images_stmt->execute([$hall_id]);
$images = $images_stmt->fetchAll();
?>
<div class="row mt-3">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-images"></i> Hall Images</h5>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="collapse" data-bs-target="#uploadImageForm">
                    <i class="fas fa-upload"></i> Upload New Image
                </button>
            </div>
            <div class="card-body">
                <!-- Image Upload Form -->
                <div class="collapse mb-3" id="uploadImageForm">
                    <div class="card">
                        <div class="card-body bg-light">
                            <h6 class="card-title">Upload New Image</h6>
                            <form method="POST" action="" enctype="multipart/form-data">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="hall_image" class="form-label">Select Image <span class="text-danger">*</span></label>
                                            <input type="file" class="form-control" id="hall_image" name="hall_image" accept="image/*" required>
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

                <!-- Display Images -->
                <?php if (count($images) > 0): ?>
                    <div class="row">
                        <?php foreach ($images as $image): ?>
                            <div class="col-md-3 mb-3">
                                <div class="card h-100">
                                    <?php 
                                    $image_url = UPLOAD_URL . rawurlencode($image['image_path']);
                                    $image_file = UPLOAD_PATH . $image['image_path'];
                                    ?>
                                    <?php if (file_exists($image_file)): ?>
                                        <img src="<?php echo htmlspecialchars($image_url, ENT_QUOTES, 'UTF-8'); ?>" class="card-img-top" alt="Hall image" style="height: 200px; object-fit: cover;">
                                    <?php else: ?>
                                        <div class="card-img-top bg-secondary d-flex align-items-center justify-content-center" style="height: 200px;">
                                            <i class="fas fa-image fa-3x text-white"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div class="card-body text-center">
                                        <p class="mb-1 small text-muted">Order: <?php echo htmlspecialchars($image['display_order'], ENT_QUOTES, 'UTF-8'); ?></p>
                                        <?php if ($image['is_primary']): ?>
                                            <span class="badge bg-primary mb-2">Primary Image</span>
                                        <?php endif; ?>
                                        <div>
                                            <form method="POST" action="" style="display: inline;">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                                <input type="hidden" name="delete_image" value="<?php echo htmlspecialchars($image['id'], ENT_QUOTES, 'UTF-8'); ?>">
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
                        <i class="fas fa-info-circle"></i> No images uploaded yet. Click "Upload New Image" to add images for this hall.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function confirmDelete() {
    if (confirm('Are you sure you want to delete this hall? This action cannot be undone.')) {
        window.location.href = 'edit.php?id=<?php echo intval($hall_id); ?>&action=delete';
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
