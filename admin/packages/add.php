<?php
$page_title = 'Add Service Package';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();
$success_message = '';
$error_message   = '';

// Load categories for dropdown
$cat_stmt = $db->query("SELECT id, name FROM service_categories WHERE status = 'active' ORDER BY display_order, name");
$categories = $cat_stmt->fetchAll();

// Pre-select category if passed in URL
$preselect_cat = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;

// Load all active services for the features checkboxes
$all_services = getActiveServices();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name          = trim($_POST['name'] ?? '');
    $category_id   = intval($_POST['category_id'] ?? 0);
    $description   = trim($_POST['description'] ?? '');
    $price         = floatval($_POST['price'] ?? 0);
    $display_order = intval($_POST['display_order'] ?? 0);
    $status        = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';
    // Features: array of service IDs chosen from the checkbox list
    $features_raw = $_POST['features'] ?? [];
    $features     = array_values(array_filter(array_map('intval', $features_raw)));

    if (empty($name) || $category_id <= 0 || $price < 0) {
        $error_message = 'Please fill in all required fields correctly.';
    } else {
        // Handle photo uploads before transaction
        $uploaded_photos = [];
        $photo_upload_error = '';
        if (!empty($_FILES['photos']['name'][0])) {
            foreach ($_FILES['photos']['name'] as $i => $fname) {
                if ($_FILES['photos']['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
                $file = [
                    'name'     => $_FILES['photos']['name'][$i],
                    'type'     => $_FILES['photos']['type'][$i],
                    'tmp_name' => $_FILES['photos']['tmp_name'][$i],
                    'error'    => $_FILES['photos']['error'][$i],
                    'size'     => $_FILES['photos']['size'][$i],
                ];
                $upload = handleImageUpload($file, 'pkg');
                if ($upload['success']) {
                    $uploaded_photos[] = $upload['filename'];
                } else {
                    $photo_upload_error = $upload['message'];
                    break;
                }
            }
        }

        if ($photo_upload_error) {
            // Clean up any already-uploaded photos
            foreach ($uploaded_photos as $f) { deleteUploadedFile($f); }
            $error_message = $photo_upload_error;
        } else {
            try {
                $db->beginTransaction();

                $stmt = $db->prepare(
                    "INSERT INTO service_packages (category_id, name, description, price, display_order, status)
                     VALUES (?, ?, ?, ?, ?, ?)"
                );
                $stmt->execute([$category_id, $name, $description, $price, $display_order, $status]);
                $package_id = $db->lastInsertId();

                // Insert features – store both service_id and feature_text (name)
                $svc_map = [];
                foreach ($all_services as $svc) { $svc_map[(int)$svc['id']] = $svc['name']; }
                $feat_stmt = $db->prepare(
                    "INSERT INTO service_package_features (package_id, service_id, feature_text, display_order) VALUES (?, ?, ?, ?)"
                );
                foreach ($features as $i => $svc_id) {
                    $feat_name = $svc_map[$svc_id] ?? '';
                    if ($feat_name !== '') {
                        $feat_stmt->execute([$package_id, $svc_id, $feat_name, $i + 1]);
                    } else {
                        error_log("add package: unknown service_id $svc_id submitted for package $package_id; skipping feature.");
                    }
                }

                // Insert photos
                $photo_stmt = $db->prepare(
                    "INSERT INTO service_package_photos (package_id, image_path, display_order) VALUES (?, ?, ?)"
                );
                foreach ($uploaded_photos as $i => $photo_path) {
                    $photo_stmt->execute([$package_id, $photo_path, $i + 1]);
                }

                $db->commit();

                logActivity($current_user['id'], 'Added service package', 'service_packages', $package_id, "Added package: $name");

                $success_message = 'Package added successfully!';
                $_POST = [];
                $preselect_cat = $category_id;
            } catch (Exception $e) {
                $db->rollBack();
                foreach ($uploaded_photos as $f) { deleteUploadedFile($f); }
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
                <h5 class="mb-0"><i class="fas fa-plus"></i> Add New Package</h5>
                <a href="index.php" class="btn btn-secondary btn-sm">
                    <i class="fas fa-arrow-left"></i> Back to List
                </a>
            </div>
            <div class="card-body">
                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
                        <a href="index.php" class="alert-link">View all packages</a>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (empty($categories)): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        No active categories found.
                        <a href="categories.php" class="alert-link">Add a category first.</a>
                    </div>
                <?php else: ?>
                <form method="POST" action="" id="packageForm" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name" class="form-label">Package Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name"
                                       value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>"
                                       placeholder="e.g., Silver Package" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="category_id" class="form-label">Service Category <span class="text-danger">*</span></label>
                                <select class="form-select" id="category_id" name="category_id" required>
                                    <option value="">Select category...</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo (int)$cat['id']; ?>"
                                            <?php if ((int)($preselect_cat ?: ($_POST['category_id'] ?? 0)) === (int)$cat['id']) echo 'selected'; ?>>
                                            <?php echo htmlspecialchars($cat['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="price" class="form-label">Price (<?php echo htmlspecialchars(getSetting('currency', 'NPR')); ?>) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="price" name="price"
                                       value="<?php echo htmlspecialchars($_POST['price'] ?? ''); ?>"
                                       min="0" step="0.01" placeholder="e.g., 50000.00" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="display_order" class="form-label">Display Order</label>
                                <input type="number" class="form-control" id="display_order" name="display_order"
                                       value="<?php echo (int)($_POST['display_order'] ?? 0); ?>"
                                       min="0" placeholder="0">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="active"   <?php echo (($_POST['status'] ?? 'active') === 'active')   ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo (($_POST['status'] ?? '') === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"
                                  placeholder="Brief description of this package..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Package Features (checkmark list)</label>
                        <p class="text-muted small mb-2">Select services from the list below to include as features in this package.</p>
                        <?php
                        $selected_features = array_flip(array_map('intval', $_POST['features'] ?? []));
                        if (!empty($all_services)):
                            // Group services by category
                            $services_by_cat = [];
                            foreach ($all_services as $svc) {
                                $cat_label = $svc['vendor_type_label'] ?: 'Other';
                                $services_by_cat[$cat_label][] = $svc;
                            }
                        ?>
                        <div class="mb-2">
                            <input type="text" class="form-control form-control-sm" id="featureSearch"
                                   placeholder="Search services..." autocomplete="off">
                        </div>
                        <div id="featuresContainer" style="max-height:360px;overflow-y:auto;border:1px solid #dee2e6;border-radius:4px;padding:8px;">
                            <?php foreach ($services_by_cat as $cat_label => $svcs): ?>
                            <div class="feature-category-group mb-2">
                                <div class="fw-semibold text-secondary small text-uppercase px-1 mb-1"
                                     style="letter-spacing:.04em;"><?php echo htmlspecialchars($cat_label); ?></div>
                                <?php foreach ($svcs as $svc): ?>
                                <div class="feature-item form-check ms-2">
                                    <input class="form-check-input" type="checkbox" name="features[]"
                                           id="feat_<?php echo (int)$svc['id']; ?>"
                                           value="<?php echo (int)$svc['id']; ?>"
                                           <?php echo isset($selected_features[(int)$svc['id']]) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="feat_<?php echo (int)$svc['id']; ?>">
                                        <?php echo htmlspecialchars($svc['name']); ?>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-warning py-2">
                            <i class="fas fa-exclamation-triangle"></i>
                            No active services found. <a href="../services/index.php" class="alert-link">Add services first.</a>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Package Photos</label>
                        <input type="file" class="form-control" name="photos[]" accept="image/*" multiple>
                        <small class="text-muted">You can select multiple photos (JPG, PNG, GIF, WebP; max 5MB each).</small>
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> Add Package
                        </button>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Live search filter for services checkboxes
document.getElementById('featureSearch')?.addEventListener('input', function () {
    const q = this.value.trim().toLowerCase();
    document.querySelectorAll('#featuresContainer .feature-category-group').forEach(function (group) {
        let groupVisible = false;
        group.querySelectorAll('.feature-item').forEach(function (item) {
            const label = item.querySelector('label')?.textContent.toLowerCase() || '';
            const show = !q || label.includes(q);
            item.style.display = show ? '' : 'none';
            if (show) groupVisible = true;
        });
        group.style.display = groupVisible ? '' : 'none';
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
