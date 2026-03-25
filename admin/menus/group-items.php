<?php
$page_title = 'Manage Group Items';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();
$group_id   = isset($_GET['group_id'])   ? intval($_GET['group_id'])   : 0;
$section_id = isset($_GET['section_id']) ? intval($_GET['section_id']) : 0;
$menu_id    = isset($_GET['menu_id'])    ? intval($_GET['menu_id'])    : 0;

if ($group_id <= 0) {
    header('Location: index.php');
    exit;
}

// Fetch group, section, menu info
$stmt = $db->prepare(
    "SELECT mg.*, ms.section_name, ms.menu_id, m.name as menu_name
     FROM menu_groups mg
     JOIN menu_sections ms ON ms.id = mg.menu_section_id
     JOIN menus m ON m.id = ms.menu_id
     WHERE mg.id = ?"
);
$stmt->execute([$group_id]);
$group = $stmt->fetch();
if (!$group) {
    header('Location: index.php');
    exit;
}
$section_id = intval($group['menu_section_id']);
$menu_id    = intval($group['menu_id']);

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $item_name     = trim($_POST['item_name'] ?? '');
        $sub_category  = trim($_POST['sub_category'] ?? '') ?: null;
        $extra_charge  = floatval($_POST['extra_charge'] ?? 0);
        $display_order = intval($_POST['display_order'] ?? 0);

        if (empty($item_name)) {
            $error = 'Item name is required.';
        } else {
            try {
                $db->prepare("INSERT INTO menu_group_items (menu_group_id, item_name, sub_category, extra_charge, display_order) VALUES (?, ?, ?, ?, ?)")
                   ->execute([$group_id, $item_name, $sub_category, $extra_charge, $display_order]);
                $success = 'Item added successfully.';
            } catch (\Throwable $e) {
                error_log("Add group item error: " . $e->getMessage());
                $error = 'Failed to add item.';
            }
        }
    } elseif ($action === 'edit') {
        $item_id       = intval($_POST['item_id'] ?? 0);
        $item_name     = trim($_POST['item_name'] ?? '');
        $sub_category  = trim($_POST['sub_category'] ?? '') ?: null;
        $extra_charge  = floatval($_POST['extra_charge'] ?? 0);
        $display_order = intval($_POST['display_order'] ?? 0);
        $status        = in_array($_POST['status'] ?? '', ['active','inactive']) ? $_POST['status'] : 'active';

        if ($item_id <= 0 || empty($item_name)) {
            $error = 'Invalid data.';
        } else {
            try {
                $db->prepare("UPDATE menu_group_items SET item_name=?, sub_category=?, extra_charge=?, display_order=?, status=? WHERE id=? AND menu_group_id=?")
                   ->execute([$item_name, $sub_category, $extra_charge, $display_order, $status, $item_id, $group_id]);
                $success = 'Item updated successfully.';
            } catch (\Throwable $e) {
                error_log("Edit group item error: " . $e->getMessage());
                $error = 'Failed to update item.';
            }
        }
    } elseif ($action === 'delete') {
        $item_id = intval($_POST['item_id'] ?? 0);
        if ($item_id > 0) {
            try {
                $db->prepare("DELETE FROM menu_group_items WHERE id=? AND menu_group_id=?")
                   ->execute([$item_id, $group_id]);
                $success = 'Item deleted.';
            } catch (\Throwable $e) {
                error_log("Delete group item error: " . $e->getMessage());
                $error = 'Failed to delete item.';
            }
        }
    }
}

// Fetch items
$items_stmt = $db->prepare("SELECT * FROM menu_group_items WHERE menu_group_id = ? ORDER BY display_order, id");
$items_stmt->execute([$group_id]);
$items = $items_stmt->fetchAll();
?>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle"></i> <?php echo sanitize($success); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-circle"></i> <?php echo sanitize($error); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4><i class="fas fa-list-ul"></i> Items in Group: <strong><?php echo sanitize($group['group_name']); ?></strong></h4>
    <div>
        <a href="groups.php?section_id=<?php echo $section_id; ?>&menu_id=<?php echo $menu_id; ?>" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Back to Groups</a>
    </div>
</div>
<p class="text-muted">
    <a href="view.php?id=<?php echo $menu_id; ?>"><?php echo sanitize($group['menu_name']); ?></a>
    &rsaquo; <a href="sections.php?menu_id=<?php echo $menu_id; ?>">Sections</a>
    &rsaquo; <?php echo sanitize($group['section_name']); ?>
    &rsaquo; <?php echo sanitize($group['group_name']); ?>
    <?php if ($group['choose_limit'] !== null): ?>
        <span class="badge bg-info ms-2">Choose up to <?php echo intval($group['choose_limit']); ?></span>
    <?php endif; ?>
</p>

<!-- Add Item Form -->
<div class="card mb-4">
    <div class="card-header bg-white"><h6 class="mb-0"><i class="fas fa-plus"></i> Add New Item</h6></div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Item Name <span class="text-danger">*</span></label>
                    <input type="text" name="item_name" class="form-control" placeholder="e.g. Veg Clear Soup" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Sub Category <small class="text-muted">(display only)</small></label>
                    <input type="text" name="sub_category" class="form-control" placeholder="e.g. Paneer Snacks">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Extra Charge</label>
                    <div class="input-group">
                        <span class="input-group-text">Rs.</span>
                        <input type="number" name="extra_charge" class="form-control" value="0" min="0" step="0.01">
                    </div>
                </div>
                <div class="col-md-1">
                    <label class="form-label">Order</label>
                    <input type="number" name="display_order" class="form-control" value="0" min="0">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-success w-100"><i class="fas fa-plus"></i> Add Item</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Items List -->
<div class="card">
    <div class="card-header bg-white"><h6 class="mb-0"><i class="fas fa-list"></i> Items (<?php echo count($items); ?>)</h6></div>
    <div class="card-body p-0">
        <?php if (empty($items)): ?>
            <div class="p-3"><div class="alert alert-info mb-0"><i class="fas fa-info-circle"></i> No items yet. Add the first item above.</div></div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Order</th>
                        <th>Item Name</th>
                        <th>Sub Category</th>
                        <th>Extra Charge</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                    <tr>
                        <td><?php echo intval($item['display_order']); ?></td>
                        <td><strong><?php echo sanitize($item['item_name']); ?></strong></td>
                        <td><?php echo $item['sub_category'] ? sanitize($item['sub_category']) : '<em class="text-muted">–</em>'; ?></td>
                        <td><?php echo floatval($item['extra_charge']) > 0 ? '<span class="badge bg-warning text-dark">' . formatCurrency($item['extra_charge']) . '</span>' : '<em class="text-muted">Included</em>'; ?></td>
                        <td><span class="badge bg-<?php echo $item['status'] === 'active' ? 'success' : 'secondary'; ?>"><?php echo ucfirst(sanitize($item['status'])); ?></span></td>
                        <td>
                            <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editItemModal<?php echo $item['id']; ?>"><i class="fas fa-edit"></i></button>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this item?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>

                    <!-- Edit Modal -->
                    <div class="modal fade" id="editItemModal<?php echo $item['id']; ?>" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Edit Item</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <form method="POST">
                                    <div class="modal-body">
                                        <input type="hidden" name="action" value="edit">
                                        <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                        <div class="mb-3">
                                            <label class="form-label">Item Name <span class="text-danger">*</span></label>
                                            <input type="text" name="item_name" class="form-control" value="<?php echo sanitize($item['item_name']); ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Sub Category</label>
                                            <input type="text" name="sub_category" class="form-control" value="<?php echo sanitize($item['sub_category'] ?? ''); ?>">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Extra Charge</label>
                                            <div class="input-group">
                                                <span class="input-group-text">Rs.</span>
                                                <input type="number" name="extra_charge" class="form-control" value="<?php echo floatval($item['extra_charge']); ?>" min="0" step="0.01">
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Display Order</label>
                                            <input type="number" name="display_order" class="form-control" value="<?php echo intval($item['display_order']); ?>" min="0">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Status</label>
                                            <select name="status" class="form-select">
                                                <option value="active" <?php echo $item['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                                <option value="inactive" <?php echo $item['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-warning">Save Changes</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
