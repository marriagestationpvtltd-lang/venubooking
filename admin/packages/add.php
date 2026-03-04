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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name          = trim($_POST['name'] ?? '');
    $category_id   = intval($_POST['category_id'] ?? 0);
    $description   = trim($_POST['description'] ?? '');
    $price         = floatval($_POST['price'] ?? 0);
    $display_order = intval($_POST['display_order'] ?? 0);
    $status        = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';
    // Features: array of non-empty lines
    $features_raw  = $_POST['features'] ?? [];
    $features      = array_values(array_filter(array_map('trim', $features_raw), 'strlen'));

    if (empty($name) || $category_id <= 0 || $price < 0) {
        $error_message = 'Please fill in all required fields correctly.';
    } else {
        try {
            $db->beginTransaction();

            $stmt = $db->prepare(
                "INSERT INTO service_packages (category_id, name, description, price, display_order, status)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([$category_id, $name, $description, $price, $display_order, $status]);
            $package_id = $db->lastInsertId();

            // Insert features
            $feat_stmt = $db->prepare(
                "INSERT INTO service_package_features (package_id, feature_text, display_order) VALUES (?, ?, ?)"
            );
            foreach ($features as $i => $feat) {
                $feat_stmt->execute([$package_id, $feat, $i + 1]);
            }

            $db->commit();

            logActivity($current_user['id'], 'Added service package', 'service_packages', $package_id, "Added package: $name");

            $success_message = 'Package added successfully!';
            $_POST = [];
            $preselect_cat = $category_id;
        } catch (Exception $e) {
            $db->rollBack();
            $error_message = 'Error: ' . $e->getMessage();
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
                <form method="POST" action="" id="packageForm">
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
                        <div id="featuresContainer">
                            <?php
                            $existing_features = $_POST['features'] ?? [''];
                            foreach ($existing_features as $fi => $fval):
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
