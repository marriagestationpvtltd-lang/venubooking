<?php
$page_title = 'Edit Design';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();

$design_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($design_id <= 0) {
    header('Location: index.php');
    exit;
}

// Fetch design with its parent service (direct design: service_id set)
$stmt = $db->prepare(
    "SELECT d.*, s.id AS service_id, s.name AS service_name
     FROM service_designs d
     JOIN additional_services s ON s.id = d.service_id
     WHERE d.id = ? AND d.service_id IS NOT NULL"
);
$stmt->execute([$design_id]);
$design = $stmt->fetch();
if (!$design) {
    header('Location: index.php');
    exit;
}

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $display_order = intval($_POST['display_order'] ?? 0);
    $status = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';

    if (empty($name) || $price < 0) {
        $error_message = 'Design name and a valid price are required.';
    } else {
        try {
            $photo_filename = $design['photo'];
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
                $upload_result = handleImageUpload($_FILES['photo'], 'design');
                if ($upload_result['success']) {
                    // Delete old photo
                    if (!empty($design['photo'])) {
                        deleteUploadedFile($design['photo']);
                    }
                    $photo_filename = $upload_result['filename'];
                } else {
                    $error_message = $upload_result['message'];
                }
            }

            if (empty($error_message)) {
                $stmt2 = $db->prepare(
                    "UPDATE service_designs SET name=?, description=?, price=?, photo=?, display_order=?, status=?, updated_at=NOW()
                     WHERE id = ?"
                );
                $stmt2->execute([$name, $description, $price, $photo_filename, $display_order, $status, $design_id]);
                logActivity($current_user['id'], 'Updated design', 'service_designs', $design_id, "Updated design '$name'");
                $success_message = 'Design updated successfully!';
                // Refresh design data
                $stmt->execute([$design_id]);
                $design = $stmt->fetch();
            }
        } catch (Exception $e) {
            $error_message = 'Error: ' . $e->getMessage();
        }
    }
}
?>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-edit"></i> Edit Design
                    <small class="text-muted ms-2">
                        for: <a href="view.php?id=<?php echo $design['service_id']; ?>"><?php echo htmlspecialchars($design['service_name']); ?></a>
                    </small>
                </h5>
                <a href="view.php?id=<?php echo $design['service_id']; ?>" class="btn btn-secondary btn-sm">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
            <div class="card-body">
                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                        <a href="view.php?id=<?php echo $design['service_id']; ?>" class="alert-link">Back to service</a>
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
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="name" class="form-label">Design Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name"
                                       value="<?php echo htmlspecialchars($_POST['name'] ?? $design['name']); ?>"
                                       required>
                            </div>
                            <div class="mb-3">
                                <label for="price" class="form-label">Price (<?php echo getSetting('currency', 'NPR'); ?>) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="price" name="price"
                                       value="<?php echo htmlspecialchars($_POST['price'] ?? $design['price']); ?>"
                                       min="0" step="0.01" required>
                            </div>
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($_POST['description'] ?? $design['description']); ?></textarea>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="photo" class="form-label">Design Photo</label>
                                <?php if (!empty($design['photo'])): ?>
                                    <div class="mb-2">
                                        <img src="<?php echo UPLOAD_URL . htmlspecialchars($design['photo']); ?>"
                                             alt="Current photo" class="img-thumbnail" style="max-height:150px;">
                                        <small class="d-block text-muted">Upload a new photo to replace this one.</small>
                                    </div>
                                <?php endif; ?>
                                <input type="file" class="form-control" id="photo" name="photo"
                                       accept="image/jpeg,image/png,image/gif,image/webp">
                                <small class="text-muted">JPG, PNG, GIF, or WebP. Max 5MB.</small>
                                <div id="photoPreview" class="mt-2" style="display:none;">
                                    <img id="photoPreviewImg" src="" alt="Preview" class="img-thumbnail" style="max-height:150px;">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="display_order" class="form-label">Display Order</label>
                                <input type="number" class="form-control" id="display_order" name="display_order"
                                       value="<?php echo intval($_POST['display_order'] ?? $design['display_order']); ?>" min="0">
                                <small class="text-muted">Lower numbers appear first.</small>
                            </div>
                            <div class="mb-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="active" <?php echo (($_POST['status'] ?? $design['status']) === 'active') ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo (($_POST['status'] ?? $design['status']) === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between">
                        <a href="view.php?id=<?php echo $design['service_id']; ?>" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> Save Changes
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
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
