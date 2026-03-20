<?php
$page_title = 'Add New Service';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();
$success_message = '';
$error_message = '';

// Fetch vendor types for category dropdown
$vendor_types = getVendorTypes();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $price = floatval($_POST['price']);
    $category = trim($_POST['category']);
    $status = $_POST['status'];

    // Validation
    if (empty($name) || $price < 0) {
        $error_message = 'Please fill in all required fields correctly.';
    } else {
        // Handle photo upload
        $photo_filename = null;
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
            $upload_result = handleImageUpload($_FILES['photo'], 'service');
            if ($upload_result['success']) {
                $photo_filename = $upload_result['filename'];
            } else {
                $error_message = $upload_result['message'];
            }
        }

        if (empty($error_message)) {
            try {
                $sql = "INSERT INTO additional_services (name, description, price, category, photo, status) 
                        VALUES (?, ?, ?, ?, ?, ?)";
                
                $stmt = $db->prepare($sql);
                $result = $stmt->execute([
                    $name,
                    $description,
                    $price,
                    $category,
                    $photo_filename,
                    $status
                ]);

                if ($result) {
                    $service_id = $db->lastInsertId();
                    
                    // Log activity
                    logActivity($current_user['id'], 'Added new service', 'additional_services', $service_id, "Added service: $name");
                    
                    // Redirect to the service view page so admin can immediately configure
                    // sub-services and design photos to enable the visual selection flow.
                    $_SESSION['success_message'] = 'Service added successfully! You can now add sub-services and design photos below to enable the visual design selection flow for customers.';
                    header('Location: view.php?id=' . $service_id);
                    exit;
                } else {
                    $error_message = 'Failed to add service. Please try again.';
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
                <h5 class="mb-0"><i class="fas fa-plus"></i> Add New Service</h5>
                <a href="index.php" class="btn btn-secondary btn-sm">
                    <i class="fas fa-arrow-left"></i> Back to List
                </a>
            </div>
            <div class="card-body">
                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                        <a href="index.php" class="alert-link">View all services</a>
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
                                <label for="name" class="form-label">Service Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" 
                                       placeholder="e.g., Professional Photography" required>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="category" class="form-label">Category</label>
                                <?php $currentCategory = isset($_POST['category']) ? $_POST['category'] : ''; ?>
                                <select class="form-select" id="category" name="category">
                                    <option value="">— Select Vendor Type —</option>
                                    <?php foreach ($vendor_types as $vt): ?>
                                        <option value="<?php echo htmlspecialchars($vt['label']); ?>"
                                            <?php if ($currentCategory === $vt['label']) echo 'selected'; ?>>
                                            <?php echo htmlspecialchars($vt['label']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">Category is sourced from Vendor Types.</small>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="price" class="form-label">Price (<?php echo getSetting('currency', 'NPR'); ?>)</label>
                                <input type="number" class="form-control" id="price" name="price" 
                                       value="<?php echo isset($_POST['price']) ? htmlspecialchars($_POST['price'], ENT_QUOTES, 'UTF-8') : '0'; ?>" 
                                       min="0" step="0.01" placeholder="e.g., 25000.00">
                                <small class="text-muted">Set to 0 if pricing is managed per design (Visual Design Flow).</small>
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

                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3" 
                                          placeholder="Describe the service and what it includes..."><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="photo" class="form-label">Service Photo</label>
                                <input type="file" class="form-control" id="photo" name="photo"
                                       accept="image/jpeg,image/png,image/gif,image/webp">
                                <small class="text-muted">JPG, PNG, GIF, or WebP. Max 5MB.</small>
                                <div id="photoPreview" class="mt-2" style="display:none;">
                                    <img id="photoPreviewImg" src="" alt="Preview" class="img-thumbnail" style="max-height:150px;">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> Add Service
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('photo').addEventListener('change', function() {
    const file = this.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('photoPreviewImg').src = e.target.result;
            document.getElementById('photoPreview').style.display = 'block';
        };
        reader.readAsDataURL(file);
    } else {
        document.getElementById('photoPreview').style.display = 'none';
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
