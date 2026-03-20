<?php
$page_title = 'Edit Service';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();
$success_message = '';
$error_message = '';

// Fetch vendor types for category dropdown
$vendor_types = getVendorTypes();

// Get service ID from URL
$service_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($service_id <= 0) {
    header('Location: index.php');
    exit;
}

// Fetch service details
$stmt = $db->prepare("SELECT * FROM additional_services WHERE id = ?");
$stmt->execute([$service_id]);
$service = $stmt->fetch();

if (!$service) {
    header('Location: index.php');
    exit;
}

// Check if Visual Design Flow is active (service has sub-services)
$sub_services_with_designs = getServiceSubServicesWithDesigns($service_id);
$is_visual_design_flow = !empty($sub_services_with_designs);

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
        $photo_filename = $service['photo']; // keep existing photo by default
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
            $upload_result = handleImageUpload($_FILES['photo'], 'service');
            if ($upload_result['success']) {
                // Delete old photo if it exists
                if (!empty($service['photo'])) {
                    deleteUploadedFile($service['photo']);
                }
                $photo_filename = $upload_result['filename'];
            } else {
                $error_message = $upload_result['message'];
            }
        } elseif (isset($_POST['remove_photo']) && $_POST['remove_photo'] == '1') {
            // Admin explicitly removed photo
            if (!empty($service['photo'])) {
                deleteUploadedFile($service['photo']);
            }
            $photo_filename = null;
        }

        if (empty($error_message)) {
            try {
                $sql = "UPDATE additional_services SET 
                        name = ?,
                        description = ?,
                        price = ?,
                        category = ?,
                        photo = ?,
                        status = ?
                        WHERE id = ?";
                
                $stmt = $db->prepare($sql);
                $result = $stmt->execute([
                    $name,
                    $description,
                    $price,
                    $category,
                    $photo_filename,
                    $status,
                    $service_id
                ]);

                if ($result) {
                    // Log activity
                    logActivity($current_user['id'], 'Updated service', 'additional_services', $service_id, "Updated service: $name");
                    
                    $success_message = 'Service updated successfully!';
                    
                    // Refresh service data
                    $stmt = $db->prepare("SELECT * FROM additional_services WHERE id = ?");
                    $stmt->execute([$service_id]);
                    $service = $stmt->fetch();
                } else {
                    $error_message = 'Failed to update service. Please try again.';
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
                <h5 class="mb-0"><i class="fas fa-edit"></i> Edit Service</h5>
                <div>
                    <a href="view.php?id=<?php echo $service_id; ?>" class="btn btn-info btn-sm">
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
                                <label for="name" class="form-label">Service Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?php echo htmlspecialchars($service['name']); ?>" 
                                       placeholder="e.g., Professional Photography" required>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="category" class="form-label">Category</label>
                                <?php $currentCategory = isset($_POST['category']) ? $_POST['category'] : $service['category']; ?>
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
                                <input type="number" class="form-control <?php echo $is_visual_design_flow ? 'bg-light' : ''; ?>"
                                       id="price" name="price" 
                                       value="<?php echo $service['price']; ?>" 
                                       min="0" step="0.01" placeholder="e.g., 25000.00">
                                <?php if ($is_visual_design_flow): ?>
                                    <small class="text-info">
                                        <i class="fas fa-info-circle"></i>
                                        Visual Design Flow is active – price is managed per design below.
                                    </small>
                                <?php else: ?>
                                    <small class="text-muted">Price for this service. Set to 0 if pricing is managed per design (Visual Design Flow).</small>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="active" <?php echo $service['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $service['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3" 
                                          placeholder="Describe the service and what it includes..."><?php echo htmlspecialchars($service['description']); ?></textarea>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="photo" class="form-label">Service Photo</label>
                                <?php if (!empty($service['photo'])): ?>
                                    <div id="currentPhotoWrapper" class="mb-2">
                                        <img src="<?php echo UPLOAD_URL . htmlspecialchars($service['photo']); ?>"
                                             alt="Current photo" id="currentPhoto"
                                             class="img-thumbnail" style="max-height:120px;">
                                        <div class="form-check mt-1">
                                            <input class="form-check-input" type="checkbox" name="remove_photo" value="1" id="removePhoto">
                                            <label class="form-check-label text-danger small" for="removePhoto">Remove current photo</label>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <input type="file" class="form-control" id="photo" name="photo"
                                       accept="image/jpeg,image/png,image/gif,image/webp">
                                <small class="text-muted">JPG, PNG, GIF, or WebP. Max 5MB. Leave blank to keep current photo.</small>
                                <div id="photoPreview" class="mt-2" style="display:none;">
                                    <img id="photoPreviewImg" src="" alt="Preview" class="img-thumbnail" style="max-height:120px;">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between">
                        <form method="POST" action="delete.php" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this service? This action cannot be undone.');">
                            <input type="hidden" name="id" value="<?php echo $service_id; ?>">
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-trash"></i> Delete Service
                            </button>
                        </form>
                        <div>
                            <a href="index.php" class="btn btn-secondary me-2">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save"></i> Update Service
                            </button>
                        </div>
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
