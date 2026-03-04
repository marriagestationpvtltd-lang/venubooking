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

// Load existing features
$feat_stmt = $db->prepare(
    "SELECT id, feature_text FROM service_package_features WHERE package_id = ? ORDER BY display_order, id"
);
$feat_stmt->execute([$package_id]);
$existing_features = $feat_stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name          = trim($_POST['name'] ?? '');
    $category_id   = intval($_POST['category_id'] ?? 0);
    $description   = trim($_POST['description'] ?? '');
    $price         = floatval($_POST['price'] ?? 0);
    $display_order = intval($_POST['display_order'] ?? 0);
    $status        = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';
    $features_raw  = $_POST['features'] ?? [];
    $features      = array_values(array_filter(array_map('trim', $features_raw), 'strlen'));

    if (empty($name) || $category_id <= 0 || $price < 0) {
        $error_message = 'Please fill in all required fields correctly.';
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

            $db->commit();

            logActivity($current_user['id'], 'Updated service package', 'service_packages', $package_id, "Updated package: $name");

            $success_message = 'Package updated successfully!';

            // Refresh data
            $stmt->execute([$package_id]);
            $package = $stmt->fetch();
            $feat_stmt->execute([$package_id]);
            $existing_features = $feat_stmt->fetchAll();
        } catch (Exception $e) {
            $db->rollBack();
            $error_message = 'Error: ' . $e->getMessage();
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

                <form method="POST" action="">
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
                        <div id="featuresContainer">
                            <?php
                            $feat_list = !empty($display_features) ? $display_features : [''];
                            foreach ($feat_list as $fval):
                            ?>
                            <div class="input-group mb-2 feature-row">
                                <span class="input-group-text text-success"><i class="fas fa-check"></i></span>
                                <input type="text" class="form-control" name="features[]"
                                       value="<?php echo htmlspecialchars($fval); ?>"
                                       placeholder="e.g., Free decoration">
                                <button type="button" class="btn btn-outline-danger remove-feature">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="btn btn-outline-success btn-sm mt-1" id="addFeature">
                            <i class="fas fa-plus"></i> Add Feature
                        </button>
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
document.getElementById('addFeature')?.addEventListener('click', function () {
    const container = document.getElementById('featuresContainer');
    const row = document.createElement('div');
    row.className = 'input-group mb-2 feature-row';
    row.innerHTML = `
        <span class="input-group-text text-success"><i class="fas fa-check"></i></span>
        <input type="text" class="form-control" name="features[]" placeholder="e.g., Free decoration">
        <button type="button" class="btn btn-outline-danger remove-feature">
            <i class="fas fa-times"></i>
        </button>`;
    container.appendChild(row);
});

document.addEventListener('click', function (e) {
    if (e.target.closest('.remove-feature')) {
        e.target.closest('.feature-row').remove();
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
