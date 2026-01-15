<?php
$page_title = 'Add New Hall';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();
$success_message = '';
$error_message = '';

// Fetch all venues for dropdown
$venues_stmt = $db->query("SELECT id, name FROM venues WHERE status = 'active' ORDER BY name");
$venues = $venues_stmt->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
            $sql = "INSERT INTO halls (venue_id, name, capacity, hall_type, indoor_outdoor, base_price, description, features, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
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
                $status
            ]);

            if ($result) {
                $hall_id = $db->lastInsertId();
                
                // Handle image upload if provided
                if (isset($_FILES['hall_image']) && $_FILES['hall_image']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $upload_result = handleImageUpload($_FILES['hall_image'], 'hall');
                    
                    if ($upload_result['success']) {
                        try {
                            $sql = "INSERT INTO hall_images (hall_id, image_path, is_primary, display_order) VALUES (?, ?, 1, 0)";
                            $stmt = $db->prepare($sql);
                            $stmt->execute([$hall_id, $upload_result['filename']]);
                            
                            logActivity($current_user['id'], 'Uploaded hall image', 'hall_images', $db->lastInsertId(), "Uploaded image for hall: $name");
                        } catch (Exception $e) {
                            deleteUploadedFile($upload_result['filename']);
                            // Don't fail the whole operation if image upload fails
                        }
                    }
                }
                
                // Log activity
                logActivity($current_user['id'], 'Added new hall', 'halls', $hall_id, "Added hall: $name");
                
                $success_message = 'Hall added successfully!';
                
                // Clear form
                $_POST = [];
            } else {
                $error_message = 'Failed to add hall. Please try again.';
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
                <h5 class="mb-0"><i class="fas fa-plus"></i> Add New Hall</h5>
                <a href="index.php" class="btn btn-secondary btn-sm">
                    <i class="fas fa-arrow-left"></i> Back to List
                </a>
            </div>
            <div class="card-body">
                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                        <a href="index.php" class="alert-link">View all halls</a>
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
                                <label for="venue_id" class="form-label">Venue <span class="text-danger">*</span></label>
                                <select class="form-select" id="venue_id" name="venue_id" required>
                                    <option value="">Select Venue</option>
                                    <?php foreach ($venues as $venue): ?>
                                        <option value="<?php echo $venue['id']; ?>" <?php echo (isset($_POST['venue_id']) && $_POST['venue_id'] == $venue['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($venue['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">Select the venue this hall belongs to</small>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name" class="form-label">Hall Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" 
                                       placeholder="e.g., Grand Ballroom" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="capacity" class="form-label">Capacity (Guests) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="capacity" name="capacity" 
                                       value="<?php echo isset($_POST['capacity']) ? $_POST['capacity'] : ''; ?>" 
                                       min="1" placeholder="e.g., 500" required>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="hall_type" class="form-label">Hall Type <span class="text-danger">*</span></label>
                                <select class="form-select" id="hall_type" name="hall_type" required>
                                    <option value="single" <?php echo (!isset($_POST['hall_type']) || $_POST['hall_type'] == 'single') ? 'selected' : ''; ?>>Single</option>
                                    <option value="multiple" <?php echo (isset($_POST['hall_type']) && $_POST['hall_type'] == 'multiple') ? 'selected' : ''; ?>>Multiple</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="indoor_outdoor" class="form-label">Indoor/Outdoor <span class="text-danger">*</span></label>
                                <select class="form-select" id="indoor_outdoor" name="indoor_outdoor" required>
                                    <option value="indoor" <?php echo (!isset($_POST['indoor_outdoor']) || $_POST['indoor_outdoor'] == 'indoor') ? 'selected' : ''; ?>>Indoor</option>
                                    <option value="outdoor" <?php echo (isset($_POST['indoor_outdoor']) && $_POST['indoor_outdoor'] == 'outdoor') ? 'selected' : ''; ?>>Outdoor</option>
                                    <option value="both" <?php echo (isset($_POST['indoor_outdoor']) && $_POST['indoor_outdoor'] == 'both') ? 'selected' : ''; ?>>Both</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="base_price" class="form-label">Base Price (<?php echo getSetting('currency', 'NPR'); ?>) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="base_price" name="base_price" 
                                       value="<?php echo isset($_POST['base_price']) ? $_POST['base_price'] : ''; ?>" 
                                       min="0" step="0.01" placeholder="e.g., 150000.00" required>
                                <small class="text-muted">Base rental price for the hall</small>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="active" <?php echo (!isset($_POST['status']) || $_POST['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo (isset($_POST['status']) && $_POST['status'] == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3" 
                                  placeholder="Describe the hall, its ambiance, and what makes it special..."><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="features" class="form-label">Features</label>
                        <textarea class="form-control" id="features" name="features" rows="2" 
                                  placeholder="e.g., Air conditioning, Stage, Sound system, LED screens, Wi-Fi"><?php echo isset($_POST['features']) ? htmlspecialchars($_POST['features']) : ''; ?></textarea>
                        <small class="text-muted">Separate features with commas</small>
                    </div>

                    <div class="mb-3">
                        <label for="hall_image" class="form-label">Hall Image (Optional)</label>
                        <input type="file" class="form-control" id="hall_image" name="hall_image" accept="image/*">
                        <small class="text-muted">Upload a primary image for this hall. JPG, PNG, GIF, or WebP. Max 5MB</small>
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> Add Hall
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php if (count($venues) == 0): ?>
<div class="row mt-3">
    <div class="col-md-12">
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i> <strong>Note:</strong> No active venues found. 
            Please <a href="<?php echo BASE_URL; ?>/admin/venues/add.php" class="alert-link">add a venue</a> first before creating halls.
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
