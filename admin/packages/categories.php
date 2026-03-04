<?php
$page_title = 'Manage Service Categories';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();
$success_message = '';
$error_message   = '';

// Handle add/edit/delete POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name          = trim($_POST['name'] ?? '');
        $description   = trim($_POST['description'] ?? '');
        $display_order = intval($_POST['display_order'] ?? 0);
        $status        = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';

        if (empty($name)) {
            $error_message = 'Category name is required.';
        } else {
            try {
                $db->prepare(
                    "INSERT INTO service_categories (name, description, display_order, status) VALUES (?, ?, ?, ?)"
                )->execute([$name, $description, $display_order, $status]);
                logActivity($current_user['id'], 'Added service category', 'service_categories', $db->lastInsertId(), "Added category: $name");
                $success_message = 'Category added successfully!';
            } catch (Exception $e) {
                $error_message = 'Error: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'edit') {
        $id            = intval($_POST['id'] ?? 0);
        $name          = trim($_POST['name'] ?? '');
        $description   = trim($_POST['description'] ?? '');
        $display_order = intval($_POST['display_order'] ?? 0);
        $status        = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';

        if ($id <= 0 || empty($name)) {
            $error_message = 'Invalid data.';
        } else {
            try {
                $db->prepare(
                    "UPDATE service_categories SET name=?, description=?, display_order=?, status=? WHERE id=?"
                )->execute([$name, $description, $display_order, $status, $id]);
                logActivity($current_user['id'], 'Updated service category', 'service_categories', $id, "Updated category: $name");
                $success_message = 'Category updated successfully!';
            } catch (Exception $e) {
                $error_message = 'Error: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) {
            $error_message = 'Invalid category ID.';
        } else {
            try {
                // Check for packages under this category
                $check = $db->prepare("SELECT COUNT(*) FROM service_packages WHERE category_id = ?");
                $check->execute([$id]);
                if ($check->fetchColumn() > 0) {
                    $error_message = 'Cannot delete category with existing packages. Delete packages first or set category to inactive.';
                } else {
                    $cat_stmt = $db->prepare("SELECT name FROM service_categories WHERE id = ?");
                    $cat_stmt->execute([$id]);
                    $cat_name = $cat_stmt->fetchColumn();
                    $db->prepare("DELETE FROM service_categories WHERE id = ?")->execute([$id]);
                    logActivity($current_user['id'], 'Deleted service category', 'service_categories', $id, "Deleted category: $cat_name");
                    $success_message = 'Category deleted successfully!';
                }
            } catch (Exception $e) {
                $error_message = 'Error: ' . $e->getMessage();
            }
        }
    }
}

$categories = $db->query(
    "SELECT sc.*, COUNT(sp.id) as package_count
     FROM service_categories sc
     LEFT JOIN service_packages sp ON sp.category_id = sc.id
     GROUP BY sc.id
     ORDER BY sc.display_order, sc.name"
)->fetchAll();
?>

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

<div class="row">
    <!-- Category List -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-layer-group"></i> Service Categories</h5>
                <a href="index.php" class="btn btn-secondary btn-sm">
                    <i class="fas fa-arrow-left"></i> Back to Packages
                </a>
            </div>
            <div class="card-body">
                <?php if (empty($categories)): ?>
                    <p class="text-muted"><em>No categories found. Add your first category →</em></p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Packages</th>
                                    <th>Order</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categories as $cat): ?>
                                    <tr>
                                        <td><?php echo (int)$cat['id']; ?></td>
                                        <td><?php echo htmlspecialchars($cat['name']); ?></td>
                                        <td><?php echo (int)$cat['package_count']; ?></td>
                                        <td><?php echo (int)$cat['display_order']; ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $cat['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                                <?php echo ucfirst($cat['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-warning edit-cat-btn"
                                                    data-id="<?php echo (int)$cat['id']; ?>"
                                                    data-name="<?php echo htmlspecialchars($cat['name'], ENT_QUOTES); ?>"
                                                    data-description="<?php echo htmlspecialchars($cat['description'] ?? '', ENT_QUOTES); ?>"
                                                    data-order="<?php echo (int)$cat['display_order']; ?>"
                                                    data-status="<?php echo htmlspecialchars($cat['status'], ENT_QUOTES); ?>"
                                                    title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <form method="POST" action="" style="display:inline;"
                                                  onsubmit="return confirm('Delete this category? All packages must be removed first.');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo (int)$cat['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add / Edit Category Form -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0" id="formTitle"><i class="fas fa-plus"></i> Add Category</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="" id="catForm">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="id" id="formId" value="">

                    <div class="mb-3">
                        <label for="catName" class="form-label">Category Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="catName" name="name"
                               placeholder="e.g., विवाह" required>
                    </div>
                    <div class="mb-3">
                        <label for="catDescription" class="form-label">Description</label>
                        <textarea class="form-control" id="catDescription" name="description" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="catOrder" class="form-label">Display Order</label>
                        <input type="number" class="form-control" id="catOrder" name="display_order" value="0" min="0">
                    </div>
                    <div class="mb-3">
                        <label for="catStatus" class="form-label">Status</label>
                        <select class="form-select" id="catStatus" name="status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>

                    <div class="d-flex justify-content-between">
                        <button type="button" class="btn btn-secondary btn-sm" id="resetForm">
                            <i class="fas fa-undo"></i> Reset
                        </button>
                        <button type="submit" class="btn btn-success" id="formSubmit">
                            <i class="fas fa-save"></i> Save Category
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.edit-cat-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
        document.getElementById('formTitle').innerHTML = '<i class="fas fa-edit"></i> Edit Category';
        document.getElementById('formAction').value = 'edit';
        document.getElementById('formId').value = this.dataset.id;
        document.getElementById('catName').value = this.dataset.name;
        document.getElementById('catDescription').value = this.dataset.description;
        document.getElementById('catOrder').value = this.dataset.order;
        document.getElementById('catStatus').value = this.dataset.status;
        document.getElementById('formSubmit').innerHTML = '<i class="fas fa-save"></i> Update Category';
        document.getElementById('catForm').scrollIntoView({ behavior: 'smooth' });
    });
});

document.getElementById('resetForm').addEventListener('click', function () {
    document.getElementById('formTitle').innerHTML = '<i class="fas fa-plus"></i> Add Category';
    document.getElementById('formAction').value = 'add';
    document.getElementById('formId').value = '';
    document.getElementById('catName').value = '';
    document.getElementById('catDescription').value = '';
    document.getElementById('catOrder').value = '0';
    document.getElementById('catStatus').value = 'active';
    document.getElementById('formSubmit').innerHTML = '<i class="fas fa-save"></i> Save Category';
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
