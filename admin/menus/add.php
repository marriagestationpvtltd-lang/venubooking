<?php
$page_title = 'Add New Menu';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();
$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $price_per_person = floatval($_POST['price_per_person']);
    $status = $_POST['status'];

    // Validation
    if (empty($name) || $price_per_person <= 0) {
        $error_message = 'Please fill in all required fields correctly.';
    } else {
        try {
            // Handle image upload
            $image_filename = null;
            if (isset($_FILES['menu_image']) && $_FILES['menu_image']['error'] !== UPLOAD_ERR_NO_FILE) {
                $upload_result = handleImageUpload($_FILES['menu_image'], 'menu');
                
                if ($upload_result['success']) {
                    $image_filename = $upload_result['filename'];
                } else {
                    $error_message = $upload_result['message'];
                }
            }
            
            if (empty($error_message)) {
                $sql = "INSERT INTO menus (name, description, price_per_person, image, status) 
                        VALUES (?, ?, ?, ?, ?)";
                
                $stmt = $db->prepare($sql);
                $result = $stmt->execute([
                    $name,
                    $description,
                    $price_per_person,
                    $image_filename,
                    $status
                ]);

                if ($result) {
                    $menu_id = $db->lastInsertId();
                    
                    // Log activity
                    logActivity($current_user['id'], 'Added new menu', 'menus', $menu_id, "Added menu: $name");
                    
                    $success_message = 'Menu added successfully!';
                    
                    // Clear form
                    $_POST = [];
                } else {
                    // Delete uploaded image if database insert fails
                    if ($image_filename) {
                        deleteUploadedFile($image_filename);
                    }
                    $error_message = 'Failed to add menu. Please try again.';
                }
            }
        } catch (Exception $e) {
            // Delete uploaded image on exception
            if (isset($image_filename) && $image_filename) {
                deleteUploadedFile($image_filename);
            }
            $error_message = 'Error: ' . $e->getMessage();
        }
    }
}
?>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-plus"></i> Add New Menu</h5>
                <a href="index.php" class="btn btn-secondary btn-sm">
                    <i class="fas fa-arrow-left"></i> Back to List
                </a>
            </div>
            <div class="card-body">
                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                        <a href="index.php" class="alert-link">View all menus</a>
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
                                <label for="name" class="form-label">Menu Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" 
                                       placeholder="e.g., Premium Wedding Package" required>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="price_per_person" class="form-label">Price per Person (<?php echo getSetting('currency', 'NPR'); ?>) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="price_per_person" name="price_per_person" 
                                       value="<?php echo isset($_POST['price_per_person']) ? $_POST['price_per_person'] : ''; ?>" 
                                       min="0" step="0.01" placeholder="e.g., 1500.00" required>
                                <small class="text-muted">Price charged per guest</small>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3" 
                                  placeholder="Describe the menu, its items, and what makes it special..."><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="menu_image" class="form-label">Menu Image (Optional)</label>
                        <input type="file" class="form-control" id="menu_image" name="menu_image" accept="image/*">
                        <small class="text-muted">Upload an image for this menu. JPG, PNG, GIF, or WebP. Max 5MB</small>
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
                            <i class="fas fa-save"></i> Add Menu
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row mt-3">
    <div class="col-md-12">
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> <strong>Note:</strong> After creating the menu, you can add menu items to it from the menu list page.
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
