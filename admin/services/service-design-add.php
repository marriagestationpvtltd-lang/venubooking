<?php
$page_title = 'Add Design';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();

$service_id = isset($_GET['service_id']) ? intval($_GET['service_id']) : 0;
if ($service_id <= 0) {
    header('Location: index.php');
    exit;
}

// Verify service exists
$stmt = $db->prepare("SELECT * FROM additional_services WHERE id = ?");
$stmt->execute([$service_id]);
$service = $stmt->fetch();
if (!$service) {
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
            $photo_filename = null;
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
                $upload_result = handleImageUpload($_FILES['photo'], 'design');
                if ($upload_result['success']) {
                    $photo_filename = $upload_result['filename'];
                } else {
                    $error_message = $upload_result['message'];
                }
            }

            if (empty($error_message)) {
                $stmt2 = $db->prepare(
                    "INSERT INTO service_designs (service_id, name, description, price, photo, display_order, status)
                     VALUES (?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt2->execute([$service_id, $name, $description, $price, $photo_filename, $display_order, $status]);
                $design_id = $db->lastInsertId();
                logActivity($current_user['id'], 'Added design', 'service_designs', $design_id, "Added design '$name' to service '{$service['name']}'");
                $success_message = 'Design added successfully!';
                $_POST = [];
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
                    <i class="fas fa-plus"></i> Add Design
                    <small class="text-muted ms-2">
                        for: <a href="view.php?id=<?php echo $service_id; ?>"><?php echo htmlspecialchars($service['name']); ?></a>
                    </small>
                </h5>
                <a href="view.php?id=<?php echo $service_id; ?>" class="btn btn-secondary btn-sm">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
            <div class="card-body">
                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                        <a href="view.php?id=<?php echo $service_id; ?>" class="alert-link">Back to service</a>
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
                                       value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>"
                                       placeholder="e.g., Royal Mandap, Classic Stage" required>
                            </div>
                            <div class="mb-3">
                                <label for="price" class="form-label">Price (<?php echo getSetting('currency', 'NPR'); ?>) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="price" name="price"
                                       value="<?php echo htmlspecialchars($_POST['price'] ?? ''); ?>"
                                       min="0" step="0.01" placeholder="e.g., 25000.00" required>
                            </div>
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3"
                                          placeholder="Describe this design..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="photo" class="form-label">Design Photo</label>
                                <input type="file" class="form-control" id="photo" name="photo"
                                       accept="image/jpeg,image/png,image/gif,image/webp">
                                <small class="text-muted">JPG, PNG, GIF, or WebP. Max 5MB.</small>
                                <div id="photoPreview" class="mt-2" style="display:none;">
                                    <img id="photoPreviewImg" src="" alt="Preview" class="img-thumbnail" style="max-height:200px;">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="display_order" class="form-label">Display Order</label>
                                <input type="number" class="form-control" id="display_order" name="display_order"
                                       value="<?php echo intval($_POST['display_order'] ?? 0); ?>" min="0">
                                <small class="text-muted">Lower numbers appear first.</small>
                            </div>
                            <div class="mb-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="active" <?php echo (($_POST['status'] ?? 'active') === 'active') ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo (($_POST['status'] ?? '') === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between">
                        <a href="view.php?id=<?php echo $service_id; ?>" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> Add Design
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
