<?php
$page_title = 'Edit Service Package';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();
$success_message = '';
$error_message   = '';

$package_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($package_id <= 0) {
    header('Location: index.php');
    exit;
}

// Fetch package
$stmt = $db->prepare("SELECT * FROM service_packages WHERE id = ?");
$stmt->execute([$package_id]);
$package = $stmt->fetch();
if (!$package) {
    header('Location: index.php');
    exit;
}

// Load categories
$cat_stmt = $db->query("SELECT id, name FROM service_categories ORDER BY display_order, name");
$categories = $cat_stmt->fetchAll();

// Load all active services for the features checkboxes
$all_services = getActiveServices();

// Load existing features
$feat_stmt = $db->prepare(
    "SELECT id, feature_text FROM service_package_features WHERE package_id = ? ORDER BY display_order, id"
);
$feat_stmt->execute([$package_id]);
$existing_features = $feat_stmt->fetchAll();

// Load existing photos
$existing_photos = [];
try {
    $photo_load_stmt = $db->prepare(
        "SELECT id, image_path FROM service_package_photos WHERE package_id = ? ORDER BY display_order, id"
    );
    $photo_load_stmt->execute([$package_id]);
    $existing_photos = $photo_load_stmt->fetchAll();
} catch (Exception $e) {
    // table may not exist yet; silently continue
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name          = trim($_POST['name'] ?? '');
    $category_id   = intval($_POST['category_id'] ?? 0);
    $description   = trim($_POST['description'] ?? '');
    $price         = floatval($_POST['price'] ?? 0);
    $display_order = intval($_POST['display_order'] ?? 0);
    $status        = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';
    $features_raw  = $_POST['features'] ?? [];
    $features      = array_values(array_filter(array_map('trim', $features_raw), 'strlen'));
    // Photos to delete
    $delete_photo_ids = array_map('intval', $_POST['delete_photos'] ?? []);

    if (empty($name) || $category_id <= 0 || $price < 0) {
        $error_message = 'Please fill in all required fields correctly.';
    } else {
        // Handle new photo uploads before transaction
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
            foreach ($uploaded_photos as $f) { deleteUploadedFile($f); }
            $error_message = $photo_upload_error;
        } else {
            try {
                $db->beginTransaction();

                $upd = $db->prepare(
                    "UPDATE service_packages SET category_id=?, name=?, description=?, price=?, display_order=?, status=? WHERE id=?"
                );
                $upd->execute([$category_id, $name, $description, $price, $display_order, $status, $package_id]);

                // Replace features: delete old, insert new
                $db->prepare("DELETE FROM service_package_features WHERE package_id = ?")->execute([$package_id]);
                $feat_ins = $db->prepare(
                    "INSERT INTO service_package_features (package_id, feature_text, display_order) VALUES (?, ?, ?)"
                );
                foreach ($features as $i => $feat) {
                    $feat_ins->execute([$package_id, $feat, $i + 1]);
                }

                // Delete selected photos
                if (!empty($delete_photo_ids)) {
                    $del_stmt = $db->prepare("SELECT image_path FROM service_package_photos WHERE id = ? AND package_id = ?");
                    $rm_stmt  = $db->prepare("DELETE FROM service_package_photos WHERE id = ? AND package_id = ?");
                    foreach ($delete_photo_ids as $del_id) {
                        $del_stmt->execute([$del_id, $package_id]);
                        $del_row = $del_stmt->fetch();
                        if ($del_row) {
                            $rm_stmt->execute([$del_id, $package_id]);
                            deleteUploadedFile($del_row['image_path']);
                        }
                    }
                }

                // Insert new photos
                $photo_ins = $db->prepare(
                    "INSERT INTO service_package_photos (package_id, image_path, display_order) VALUES (?, ?, ?)"
                );
                foreach ($uploaded_photos as $i => $photo_path) {
                    $photo_ins->execute([$package_id, $photo_path, $i + 1]);
                }

                $db->commit();

                logActivity($current_user['id'], 'Updated service package', 'service_packages', $package_id, "Updated package: $name");

                $success_message = 'Package updated successfully!';

                // Refresh data
                $stmt->execute([$package_id]);
                $package = $stmt->fetch();
                $feat_stmt->execute([$package_id]);
                $existing_features = $feat_stmt->fetchAll();
                $photo_load_stmt->execute([$package_id]);
                $existing_photos = $photo_load_stmt->fetchAll();
            } catch (Exception $e) {
                $db->rollBack();
                foreach ($uploaded_photos as $f) { deleteUploadedFile($f); }
                $error_message = 'Error: ' . $e->getMessage();
            }
        }
    }
}

// Use POST values if a validation error occurred
$form = $_SERVER['REQUEST_METHOD'] === 'POST' && $error_message ? $_POST : $package;
$display_features = ($_SERVER['REQUEST_METHOD'] === 'POST' && $error_message)
    ? array_filter(array_map('trim', $_POST['features'] ?? []), 'strlen')
    : array_column($existing_features, 'feature_text');
?>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-edit"></i> Edit Package</h5>
                <div>
                    <a href="view.php?id=<?php echo $package_id; ?>" class="btn btn-info btn-sm">
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
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
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
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name" class="form-label">Package Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name"
                                       value="<?php echo htmlspecialchars($form['name'] ?? ''); ?>"
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
                                            <?php if ((int)($form['category_id'] ?? 0) === (int)$cat['id']) echo 'selected'; ?>>
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
                                       value="<?php echo htmlspecialchars($form['price'] ?? ''); ?>"
                                       min="0" step="0.01" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="display_order" class="form-label">Display Order</label>
                                <input type="number" class="form-control" id="display_order" name="display_order"
                                       value="<?php echo (int)($form['display_order'] ?? 0); ?>" min="0">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="active"   <?php echo (($form['status'] ?? '') === 'active')   ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo (($form['status'] ?? '') === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($form['description'] ?? ''); ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Package Features (checkmark list)</label>
                        <p class="text-muted small mb-2">Select services from the list below to include as features in this package.</p>
                        <?php
                        $selected_features = array_flip(array_map('trim', $display_features ?? []));
                        if (!empty($all_services)):
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
                                           value="<?php echo htmlspecialchars($svc['name']); ?>"
                                           <?php echo isset($selected_features[$svc['name']]) ? 'checked' : ''; ?>>
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
                        <?php if (!empty($existing_photos)): ?>
                            <div class="row g-2 mb-2" id="existingPhotos">
                                <?php foreach ($existing_photos as $photo): ?>
                                    <?php $photo_url = UPLOAD_URL . htmlspecialchars($photo['image_path']); ?>
                                    <div class="col-auto position-relative" id="photo-<?php echo (int)$photo['id']; ?>">
                                        <img src="<?php echo $photo_url; ?>" alt="Package photo"
                                             style="width:100px;height:80px;object-fit:cover;border-radius:4px;border:1px solid #dee2e6;">
                                        <div class="form-check position-absolute top-0 end-0 m-1">
                                            <input class="form-check-input" type="checkbox"
                                                   name="delete_photos[]"
                                                   value="<?php echo (int)$photo['id']; ?>"
                                                   id="delPhoto<?php echo (int)$photo['id']; ?>"
                                                   title="Mark for deletion"
                                                   onchange="this.closest('.col-auto').style.opacity = this.checked ? '0.4' : '1'">
                                        </div>
                                        <label class="d-block text-center mt-1" for="delPhoto<?php echo (int)$photo['id']; ?>"
                                               style="font-size:0.7rem;color:#dc3545;cursor:pointer;">
                                            <i class="fas fa-trash-alt"></i> Remove
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <small class="text-muted d-block mb-2">Check the checkbox on a photo to remove it on save.</small>
                        <?php endif; ?>
                        <input type="file" class="form-control" name="photos[]" accept="image/*" multiple>
                        <small class="text-muted">Upload additional photos (JPG, PNG, GIF, WebP; max 5MB each).</small>
                    </div>

                    <div class="d-flex justify-content-between">
                        <form method="POST" action="delete.php" style="display:inline;"
                              onsubmit="return confirm('Delete this package and all its features?');">
                            <input type="hidden" name="id" value="<?php echo $package_id; ?>">
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-trash"></i> Delete Package
                            </button>
                        </form>
                        <div>
                            <a href="index.php" class="btn btn-secondary me-2">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save"></i> Update Package
                            </button>
                        </div>
                    </div>
                </form>
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
